<?php
/**
 * Power Gym - API Contatti
 * POST: nome, cognome, email, telefono, oggetto, messaggio, csrf_token
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metodo non consentito.', [], 405);
}

// -------------------------------------------------------
// Valida token CSRF
// -------------------------------------------------------
validateCsrfToken();

// -------------------------------------------------------
// Sanitizza input
// -------------------------------------------------------
$nome     = trim(htmlspecialchars($_POST['nome']     ?? '', ENT_QUOTES, 'UTF-8'));
$cognome  = trim(htmlspecialchars($_POST['cognome']  ?? '', ENT_QUOTES, 'UTF-8'));
$email    = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
$telefono = trim(htmlspecialchars($_POST['telefono'] ?? '', ENT_QUOTES, 'UTF-8'));
$oggetto  = trim(htmlspecialchars($_POST['oggetto']  ?? '', ENT_QUOTES, 'UTF-8'));
$messaggio = trim(htmlspecialchars($_POST['messaggio'] ?? '', ENT_QUOTES, 'UTF-8'));

// -------------------------------------------------------
// Validazione
// -------------------------------------------------------
$errors = [];

if (strlen($nome) < 2)    $errors[] = 'Il nome deve avere almeno 2 caratteri.';
if (strlen($cognome) < 2) $errors[] = 'Il cognome deve avere almeno 2 caratteri.';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Indirizzo email non valido.';
}

if (!empty($telefono) && !preg_match('/^\+?[\d\s\-()]{7,20}$/', $telefono)) {
    $errors[] = 'Numero di telefono non valido.';
}

if (empty($oggetto)) {
    $errors[] = 'Seleziona un oggetto per il messaggio.';
}

if (strlen($messaggio) < 20) {
    $errors[] = 'Il messaggio deve avere almeno 20 caratteri.';
}

if (strlen($messaggio) > 5000) {
    $errors[] = 'Il messaggio non può superare 5000 caratteri.';
}

if (!empty($errors)) {
    jsonResponse(false, implode(' ', $errors));
}

// -------------------------------------------------------
// Salva nel DB
// -------------------------------------------------------
$db = getDB();

$stmt = $db->prepare(
    "INSERT INTO contatti (nome, cognome, email, telefono, oggetto, messaggio, ip_address, user_agent)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

$stmt->execute([
    $nome,
    $cognome,
    $email,
    $telefono ?: null,
    $oggetto,
    $messaggio,
    $_SERVER['REMOTE_ADDR']  ?? '',
    substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
]);

// -------------------------------------------------------
// Notifica email all'admin
// -------------------------------------------------------
$adminSubject = '=?UTF-8?B?' . base64_encode('[Power Gym] Nuovo messaggio da ' . $nome . ' ' . $cognome) . '?=';
$adminBody    = "Nuovo messaggio ricevuto dal sito Power Gym.\n\n"
              . "Nome: {$nome} {$cognome}\n"
              . "Email: {$email}\n"
              . "Telefono: " . ($telefono ?: 'Non fornito') . "\n"
              . "Oggetto: {$oggetto}\n\n"
              . "Messaggio:\n{$messaggio}\n\n"
              . "---\n"
              . "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '') . "\n"
              . "Data: " . date('d/m/Y H:i');

$headers = "From: " . APP_NAME . " <" . APP_EMAIL . ">\r\n"
         . "Reply-To: {$email}\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "X-Mailer: PHP/" . phpversion();

@mail(APP_EMAIL, $adminSubject, $adminBody, $headers);

// Email di conferma al mittente
$userSubject = '=?UTF-8?B?' . base64_encode('Abbiamo ricevuto il tuo messaggio – Power Gym') . '?=';
$userBody    = "Ciao {$nome},\n\n"
             . "Abbiamo ricevuto il tuo messaggio e ti risponderemo entro 24 ore lavorative.\n\n"
             . "Riepilogo del tuo messaggio:\n"
             . "Oggetto: {$oggetto}\n"
             . "Messaggio: {$messaggio}\n\n"
             . "Per urgenze chiamaci: +39 02 1234567\n\n"
             . "Il Team Power Gym\n"
             . "Via Roma 123, 20121 Milano";

@mail($email, $userSubject, $userBody, $headers);

jsonResponse(true, 'Messaggio inviato con successo! Ti risponderemo entro 24 ore lavorative.');
