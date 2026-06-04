<?php
/**
 * Power Gym – API Iscrizione a un corso
 * POST: corso_id, note
 * Richiede sessione attiva (utente_id preso dalla sessione)
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../middleware/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metodo non consentito.', [], 405);
}

// Verifica sessione
requireLogin();

// Valida CSRF
validateCsrfToken();

// -------------------------------------------------------
// Input
// -------------------------------------------------------
$corso_id  = filter_input(INPUT_POST, 'corso_id', FILTER_VALIDATE_INT);
$note      = trim(htmlspecialchars($_POST['note'] ?? '', ENT_QUOTES, 'UTF-8'));
$utente_id = (int) $_SESSION['user_id'];

if (!$corso_id || $corso_id <= 0) {
    jsonResponse(false, 'ID corso non valido.');
}

$db = getDB();

// -------------------------------------------------------
// Verifica esistenza e stato del corso
// -------------------------------------------------------
$stmtCorso = $db->prepare(
    "SELECT id, nome, max_partecipanti, prezzo_mensile, stato
       FROM corsi
      WHERE id = ?
      LIMIT 1"
);
$stmtCorso->execute([$corso_id]);
$corso = $stmtCorso->fetch();

if (!$corso) {
    jsonResponse(false, 'Corso non trovato.', [], 404);
}

if ($corso['stato'] !== 'attivo') {
    jsonResponse(false, 'Il corso selezionato non è attualmente disponibile.');
}

// -------------------------------------------------------
// Verifica iscrizione duplicata (stessa utente+corso attiva)
// -------------------------------------------------------
$stmtDup = $db->prepare(
    "SELECT id FROM iscrizioni
      WHERE utente_id = ? AND corso_id = ? AND stato = 'attiva'
      LIMIT 1"
);
$stmtDup->execute([$utente_id, $corso_id]);

if ($stmtDup->fetch()) {
    jsonResponse(false, 'Sei già iscritto a questo corso.');
}

// -------------------------------------------------------
// Verifica posti disponibili
// -------------------------------------------------------
$stmtIscritti = $db->prepare(
    "SELECT COUNT(*) AS cnt FROM iscrizioni
      WHERE corso_id = ? AND stato = 'attiva'"
);
$stmtIscritti->execute([$corso_id]);
$iscritti = (int) $stmtIscritti->fetch()['cnt'];

if ($corso['max_partecipanti'] > 0 && $iscritti >= $corso['max_partecipanti']) {
    jsonResponse(false, 'Spiacenti, il corso è al completo. Contattaci per essere messo in lista d\'attesa.');
}

// -------------------------------------------------------
// Inserisci iscrizione
// -------------------------------------------------------
$oggi         = date('Y-m-d');
$data_scadenza = date('Y-m-d', strtotime('+30 days'));

$stmtIns = $db->prepare(
    "INSERT INTO iscrizioni
         (utente_id, corso_id, data_iscrizione, data_scadenza, stato, prezzo_pagato, metodo_pagamento, note)
     VALUES (?, ?, ?, ?, 'attiva', ?, 'contante', ?)"
);
$stmtIns->execute([
    $utente_id,
    $corso_id,
    $oggi,
    $data_scadenza,
    $corso['prezzo_mensile'],
    $note ?: null,
]);

$iscrizione_id = (int) $db->lastInsertId();

// -------------------------------------------------------
// Log accesso
// -------------------------------------------------------
logAccess($utente_id, 'iscrizione_corso_' . $corso_id);

jsonResponse(true, 'Iscrizione al corso "' . htmlspecialchars($corso['nome'], ENT_QUOTES, 'UTF-8') . '" completata con successo!', [
    'iscrizione_id' => $iscrizione_id,
    'corso'         => $corso['nome'],
    'scadenza'      => $data_scadenza,
]);
