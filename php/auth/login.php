<?php
/**
 * Power Gym - Login utente
 * POST: email, password, remember_me
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/csrf.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, 'Metodo non consentito.', [], 405);
}

// -------------------------------------------------------
// Leggi e sanitizza input
// -------------------------------------------------------
$email       = trim(filter_input(INPUT_POST, 'email',       FILTER_SANITIZE_EMAIL) ?? '');
$password    = $_POST['password']    ?? '';
$remember_me = !empty($_POST['remember_me']);

// -------------------------------------------------------
// Validazione base
// -------------------------------------------------------
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    jsonResponse(false, 'Indirizzo email non valido.');
}

if (strlen($password) < 6) {
    jsonResponse(false, 'Password non valida.');
}

$db         = getDB();
$ip_address = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// -------------------------------------------------------
// Rate limiting: max 5 tentativi falliti in 15 minuti
// -------------------------------------------------------
$stmtAttempts = $db->prepare(
    "SELECT COUNT(*) AS cnt
       FROM login_attempts
      WHERE ip_address = ?
        AND success    = 0
        AND attempted_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
);
$stmtAttempts->execute([$ip_address]);
$attempts = (int) $stmtAttempts->fetch()['cnt'];

if ($attempts >= 5) {
    jsonResponse(false, 'Troppi tentativi falliti. Riprova tra 15 minuti.', [], 429);
}

// -------------------------------------------------------
// Cerca utente nel DB
// -------------------------------------------------------
$stmtUser = $db->prepare(
    "SELECT id, nome, cognome, email, password_hash, ruolo, stato
       FROM utenti
      WHERE email = ?
      LIMIT 1"
);
$stmtUser->execute([$email]);
$user = $stmtUser->fetch();

$loginOk = false;

if ($user && password_verify($password, $user['password_hash'])) {
    if ($user['stato'] !== 'attivo') {
        // Registra tentativo fallito (account sospeso)
        $db->prepare(
            'INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, 0)'
        )->execute([$ip_address, $email]);

        jsonResponse(false, 'Account sospeso o disattivato. Contatta l\'amministratore.');
    }

    $loginOk = true;
}

// -------------------------------------------------------
// Registra tentativo
// -------------------------------------------------------
$db->prepare(
    'INSERT INTO login_attempts (ip_address, email, success) VALUES (?, ?, ?)'
)->execute([$ip_address, $email, $loginOk ? 1 : 0]);

if (!$loginOk) {
    jsonResponse(false, 'Credenziali non valide. Controlla email e password.');
}

// -------------------------------------------------------
// Crea sessione sicura
// -------------------------------------------------------
session_regenerate_id(true);

$_SESSION['user_id'] = $user['id'];
$_SESSION['nome']    = $user['nome'];
$_SESSION['cognome'] = $user['cognome'];
$_SESSION['email']   = $user['email'];
$_SESSION['ruolo']   = $user['ruolo'];

// -------------------------------------------------------
// Remember me: cookie sicuro 30 giorni
// -------------------------------------------------------
if ($remember_me) {
    $token      = bin2hex(random_bytes(32));
    $expires_at = date('Y-m-d H:i:s', strtotime('+30 days'));

    // Elimina token precedenti per questo utente
    $db->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$user['id']]);

    // Salva nuovo token
    $db->prepare(
        'INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)'
    )->execute([$user['id'], hash('sha256', $token), $expires_at]);

    setcookie(
        'remember_token',
        $token,
        [
            'expires'  => strtotime('+30 days'),
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
            'secure'   => false, // true in produzione HTTPS
        ]
    );
}

// -------------------------------------------------------
// Log accesso
// -------------------------------------------------------
logAccess($user['id'], 'login');

// -------------------------------------------------------
// Risposta
// -------------------------------------------------------
$redirect = ($user['ruolo'] === 'admin')
    ? BASE_URL . '/php/admin/dashboard.php'
    : BASE_URL . '/index.html';

jsonResponse(true, 'Login effettuato con successo.', ['redirect' => $redirect]);
