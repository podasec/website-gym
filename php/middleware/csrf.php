<?php
/**
 * Power Gym - Protezione CSRF
 */

require_once __DIR__ . '/../config.php';

/**
 * Genera (o restituisce esistente) un token CSRF salvato in sessione.
 */
function generateCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Valida il token CSRF ricevuto nel POST.
 * Termina l'esecuzione con errore 403 se non valido.
 */
function validateCsrfToken(): void
{
    $submitted = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';

    if (empty($stored) || !hash_equals($stored, $submitted)) {
        jsonResponse(false, 'Token CSRF non valido. Ricaricare la pagina e riprovare.', [], 403);
    }

    // Rigenera token dopo uso (double-submit pattern)
    unset($_SESSION['csrf_token']);
}
