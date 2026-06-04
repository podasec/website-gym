<?php
/**
 * Power Gym – API Newsletter
 * POST: email, nome (opzionale)
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metodo non consentito.', [], 405);
}

// -------------------------------------------------------
// Input e validazione
// -------------------------------------------------------
$email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
$nome  = trim(htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8'));

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Indirizzo email non valido.');
}

if (strlen($email) > 255) {
    jsonResponse(false, 'Indirizzo email troppo lungo.');
}

$db = getDB();

// -------------------------------------------------------
// Controlla se già iscritto
// -------------------------------------------------------
$stmtCheck = $db->prepare(
    "SELECT id, stato FROM newsletter WHERE email = ? LIMIT 1"
);
$stmtCheck->execute([$email]);
$existing = $stmtCheck->fetch();

if ($existing) {
    if ($existing['stato'] === 'attivo') {
        jsonResponse(true, 'Sei già iscritto alla nostra newsletter!');
    }

    // Era disiscritto: riattiva
    $db->prepare(
        "UPDATE newsletter SET stato = 'attivo', nome = ? WHERE id = ?"
    )->execute([$nome ?: null, $existing['id']]);

    jsonResponse(true, 'Bentornato! La tua iscrizione alla newsletter è stata riattivata.');
}

// -------------------------------------------------------
// Genera token di disiscrizione
// -------------------------------------------------------
$token = bin2hex(random_bytes(32));

// -------------------------------------------------------
// Inserisci nella tabella newsletter
// -------------------------------------------------------
$stmtIns = $db->prepare(
    "INSERT INTO newsletter (email, nome, stato, token_disiscrizione)
     VALUES (?, ?, 'attivo', ?)"
);
$stmtIns->execute([
    $email,
    $nome ?: null,
    $token,
]);

// -------------------------------------------------------
// Email di conferma (best-effort)
// -------------------------------------------------------
$saluto  = $nome ? "Ciao {$nome}," : 'Ciao,';
$subject = '=?UTF-8?B?' . base64_encode('Iscrizione confermata – Power Gym Newsletter') . '?=';
$body    = "{$saluto}\n\n"
         . "Sei ora iscritto alla newsletter di Power Gym!\n\n"
         . "Riceverai aggiornamenti su nuovi corsi, eventi speciali e offerte riservate ai nostri atleti.\n\n"
         . "Per disiscriverti in qualsiasi momento: "
         . BASE_URL . "/php/api/newsletter.php?unsubscribe=" . urlencode($token) . "\n\n"
         . "Il Team Power Gym\n"
         . "Via Roma 123, 20121 Milano";

$headers = "From: " . APP_NAME . " <" . APP_EMAIL . ">\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "X-Mailer: PHP/" . phpversion();

@mail($email, $subject, $body, $headers);

jsonResponse(true, 'Iscrizione alla newsletter completata! Controlla la tua email per la conferma.');
