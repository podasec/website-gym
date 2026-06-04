<?php
/**
 * Power Gym - Registrazione nuovo utente
 * POST: nome, cognome, email, password, telefono, data_nascita, corso_interesse
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metodo non consentito.', [], 405);
}

// -------------------------------------------------------
// Leggi input
// -------------------------------------------------------
$nome             = trim(htmlspecialchars($_POST['nome']             ?? '', ENT_QUOTES, 'UTF-8'));
$cognome          = trim(htmlspecialchars($_POST['cognome']          ?? '', ENT_QUOTES, 'UTF-8'));
$email            = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?? '');
$password         = $_POST['password']         ?? '';
$telefono         = trim(htmlspecialchars($_POST['telefono']         ?? '', ENT_QUOTES, 'UTF-8'));
$data_nascita     = trim($_POST['data_nascita']     ?? '');
$corso_interesse  = trim(htmlspecialchars($_POST['corso_interesse']  ?? '', ENT_QUOTES, 'UTF-8'));

// -------------------------------------------------------
// Validazione server-side
// -------------------------------------------------------
$errors = [];

if (strlen($nome) < 2 || strlen($nome) > 100) {
    $errors[] = 'Il nome deve essere tra 2 e 100 caratteri.';
}

if (strlen($cognome) < 2 || strlen($cognome) > 100) {
    $errors[] = 'Il cognome deve essere tra 2 e 100 caratteri.';
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Indirizzo email non valido.';
}

if (strlen($password) < 8) {
    $errors[] = 'La password deve essere di almeno 8 caratteri.';
} elseif (!preg_match('/[A-Z]/', $password)) {
    $errors[] = 'La password deve contenere almeno una lettera maiuscola.';
} elseif (!preg_match('/[0-9]/', $password)) {
    $errors[] = 'La password deve contenere almeno un numero.';
}

if (!empty($telefono) && !preg_match('/^\+?[\d\s\-()]{7,20}$/', $telefono)) {
    $errors[] = 'Numero di telefono non valido.';
}

if (!empty($data_nascita)) {
    $d = DateTime::createFromFormat('Y-m-d', $data_nascita);
    if (!$d || $d->format('Y-m-d') !== $data_nascita) {
        $errors[] = 'Data di nascita non valida (formato: YYYY-MM-DD).';
    } elseif ($d > new DateTime('-14 years')) {
        $errors[] = 'Devi avere almeno 14 anni per registrarti.';
    }
}

if (!empty($errors)) {
    jsonResponse(false, implode(' ', $errors));
}

// -------------------------------------------------------
// Verifica email univoca
// -------------------------------------------------------
$db = getDB();

$stmtCheck = $db->prepare('SELECT id FROM utenti WHERE email = ? LIMIT 1');
$stmtCheck->execute([$email]);

if ($stmtCheck->fetch()) {
    jsonResponse(false, 'Questa email è già registrata. Accedi o usa il recupero password.');
}

// -------------------------------------------------------
// Hash password e inserimento nel DB
// -------------------------------------------------------
$passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

$stmtInsert = $db->prepare(
    "INSERT INTO utenti (nome, cognome, email, password_hash, telefono, data_nascita, ruolo, stato, note)
     VALUES (?, ?, ?, ?, ?, ?, 'cliente', 'attivo', ?)"
);

$notaCorso = !empty($corso_interesse) ? 'Interesse corso: ' . $corso_interesse : null;

$stmtInsert->execute([
    $nome,
    $cognome,
    $email,
    $passwordHash,
    $telefono ?: null,
    !empty($data_nascita) ? $data_nascita : null,
    $notaCorso,
]);

$newUserId = (int) $db->lastInsertId();

// -------------------------------------------------------
// Invia email di benvenuto (best-effort)
// -------------------------------------------------------
$subject = '=?UTF-8?B?' . base64_encode('Benvenuto in Power Gym, ' . $nome . '!') . '?=';
$body    = "Ciao {$nome},\n\n"
         . "Benvenuto in Power Gym! Il tuo account è stato creato con successo.\n\n"
         . "Email: {$email}\n\n"
         . "Accedi alla tua area riservata: " . BASE_URL . "/login.html\n\n"
         . "A presto in palestra!\n"
         . "Il Team Power Gym";

$headers = "From: " . APP_NAME . " <" . APP_EMAIL . ">\r\n"
         . "Reply-To: " . APP_EMAIL . "\r\n"
         . "Content-Type: text/plain; charset=UTF-8\r\n"
         . "X-Mailer: PHP/" . phpversion();

@mail($email, $subject, $body, $headers);

// -------------------------------------------------------
// Crea sessione automatica dopo registrazione
// -------------------------------------------------------
session_regenerate_id(true);
$_SESSION['user_id'] = $newUserId;
$_SESSION['nome']    = $nome;
$_SESSION['cognome'] = $cognome;
$_SESSION['email']   = $email;
$_SESSION['ruolo']   = 'cliente';

logAccess($newUserId, 'register');

jsonResponse(true, 'Registrazione completata! Benvenuto in Power Gym.', [
    'redirect' => BASE_URL . '/index.html',
]);
