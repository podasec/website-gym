<?php
/**
 * Power Gym - Logout utente
 */

require_once __DIR__ . '/../config.php';

$userId = $_SESSION['user_id'] ?? null;

// Logga logout prima di distruggere la sessione
if ($userId) {
    logAccess($userId, 'logout');
}

// -------------------------------------------------------
// Cancella token remember_me dal DB
// -------------------------------------------------------
if (!empty($_COOKIE['remember_token'])) {
    try {
        $db = getDB();
        $db->prepare(
            'DELETE FROM remember_tokens WHERE token = ?'
        )->execute([hash('sha256', $_COOKIE['remember_token'])]);
    } catch (PDOException $e) {
        error_log('Logout DB error: ' . $e->getMessage());
    }

    // Cancella cookie
    setcookie('remember_token', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure'   => false,
    ]);
}

// -------------------------------------------------------
// Distruggi sessione
// -------------------------------------------------------
$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

// -------------------------------------------------------
// Redirect alla homepage
// -------------------------------------------------------
header('Location: ' . BASE_URL . '/index.html');
exit;
