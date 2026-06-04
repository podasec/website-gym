<?php
/**
 * Power Gym - Middleware autenticazione
 */

require_once __DIR__ . '/../config.php';

/**
 * Verifica che l'utente sia autenticato.
 * Se non lo è, risponde con 401 JSON oppure redirige, in base al contesto.
 */
function requireLogin(bool $jsonResponse = true): void
{
    if (empty($_SESSION['user_id'])) {
        if ($jsonResponse) {
            jsonResponse(false, 'Accesso negato. Effettua il login.', [], 401);
        } else {
            header('Location: ' . BASE_URL . '/login.html');
            exit;
        }
    }
}

/**
 * Verifica che l'utente autenticato abbia ruolo 'admin'.
 */
function requireAdmin(bool $jsonResponse = true): void
{
    requireLogin($jsonResponse);

    if (($_SESSION['ruolo'] ?? '') !== 'admin') {
        if ($jsonResponse) {
            jsonResponse(false, 'Accesso negato. Area riservata agli amministratori.', [], 403);
        } else {
            header('Location: ' . BASE_URL . '/index.html');
            exit;
        }
    }
}

/**
 * Restituisce i dati dell'utente corrente dalla sessione.
 */
function currentUser(): array
{
    return [
        'id'      => $_SESSION['user_id']   ?? null,
        'nome'    => $_SESSION['nome']       ?? '',
        'cognome' => $_SESSION['cognome']    ?? '',
        'email'   => $_SESSION['email']      ?? '',
        'ruolo'   => $_SESSION['ruolo']      ?? 'cliente',
    ];
}
