<?php
/**
 * Power Gym - Configurazione Database e Applicazione
 */

// -------------------------------------------------------
// Configurazione Database MySQL
// -------------------------------------------------------
define('DB_HOST',    'localhost');
define('DB_NAME',    'powergym_db');
define('DB_USER',    'root');
define('DB_PASS',    '');
define('DB_CHARSET', 'utf8mb4');

// -------------------------------------------------------
// Configurazione Applicazione
// -------------------------------------------------------
define('APP_NAME',   'Power Gym');
define('APP_EMAIL',  'info@powergym.it');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('BASE_URL',   'http://localhost/website-gym');

// -------------------------------------------------------
// Configurazione Sessione sicura
// -------------------------------------------------------
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure',   0); // mettere 1 in produzione con HTTPS
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    session_start();
}

// -------------------------------------------------------
// Singleton PDO
// -------------------------------------------------------
function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log errore senza esporre dettagli all'utente
            error_log('DB connection error: ' . $e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Errore di connessione al database.']);
            exit;
        }
    }

    return $pdo;
}

// -------------------------------------------------------
// Headers di sicurezza comuni
// -------------------------------------------------------
function setSecurityHeaders(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// -------------------------------------------------------
// Helper: risposta JSON
// -------------------------------------------------------
function jsonResponse(bool $success, string $message, array $data = [], int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// -------------------------------------------------------
// Helper: log accesso utente
// -------------------------------------------------------
function logAccess(int $userId, string $action): void
{
    try {
        $db = getDB();
        $stmt = $db->prepare(
            'INSERT INTO access_log (user_id, ip_address, user_agent, action) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            $action,
        ]);
    } catch (PDOException $e) {
        error_log('logAccess error: ' . $e->getMessage());
    }
}

setSecurityHeaders();
