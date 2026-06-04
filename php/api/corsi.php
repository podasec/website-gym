<?php
/**
 * Power Gym – API Corsi
 *
 * GET /php/api/corsi.php          → lista tutti i corsi attivi
 * GET /php/api/corsi.php?id=1     → dettaglio corso
 * GET /php/api/corsi.php?livello= → filtra per livello
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(false, 'Metodo non consentito.', [], 405);
}

$db = getDB();

// -------------------------------------------------------
// Parametri filtro
// -------------------------------------------------------
$id      = filter_input(INPUT_GET, 'id',      FILTER_VALIDATE_INT);
$livello = filter_input(INPUT_GET, 'livello', FILTER_SANITIZE_SPECIAL_CHARS);

// -------------------------------------------------------
// Cache semplice basata su sessione (30 secondi)
// -------------------------------------------------------
$cacheKey = 'corsi_cache_' . md5(serialize([$id, $livello]));

if (
    isset($_SESSION[$cacheKey], $_SESSION[$cacheKey . '_ts']) &&
    (time() - $_SESSION[$cacheKey . '_ts']) < 30
) {
    echo $_SESSION[$cacheKey];
    exit;
}

// -------------------------------------------------------
// Query: dettaglio singolo corso
// -------------------------------------------------------
if ($id !== false && $id !== null) {
    $stmt = $db->prepare(
        "SELECT
            c.id,
            c.nome,
            c.descrizione,
            c.descrizione_breve,
            c.livello,
            c.durata_minuti,
            c.max_partecipanti,
            c.prezzo_mensile,
            c.immagine_url,
            c.stato,
            d.nome      AS disciplina,
            d.icona_svg AS disciplina_icona,
            CONCAT(u.nome, ' ', u.cognome) AS istruttore,
            (
                SELECT COUNT(*)
                FROM iscrizioni i
                WHERE i.corso_id = c.id AND i.stato = 'attiva'
            ) AS iscritti_attivi,
            (
                SELECT JSON_ARRAYAGG(
                    JSON_OBJECT(
                        'giorno', o.giorno_settimana,
                        'ora_inizio', o.ora_inizio,
                        'ora_fine', o.ora_fine,
                        'sala', o.sala
                    )
                )
                FROM orari_corsi o
                WHERE o.corso_id = c.id AND o.attivo = 1
            ) AS orari
         FROM corsi c
         LEFT JOIN discipline d ON d.id = c.disciplina_id
         LEFT JOIN utenti     u ON u.id = c.istruttore_id
         WHERE c.id = ?
         LIMIT 1"
    );
    $stmt->execute([$id]);
    $corso = $stmt->fetch();

    if (!$corso) {
        jsonResponse(false, 'Corso non trovato.', [], 404);
    }

    // Decodifica orari JSON
    $corso['orari'] = json_decode($corso['orari'] ?? '[]', true);

    $response = json_encode(['success' => true, 'data' => $corso], JSON_UNESCAPED_UNICODE);
    $_SESSION[$cacheKey]        = $response;
    $_SESSION[$cacheKey . '_ts'] = time();
    echo $response;
    exit;
}

// -------------------------------------------------------
// Query: lista corsi (con filtro opzionale per livello)
// -------------------------------------------------------
$livelliValidi = ['principiante', 'intermedio', 'avanzato', 'tutti'];

$sql    = "SELECT
               c.id,
               c.nome,
               c.descrizione_breve,
               c.livello,
               c.durata_minuti,
               c.max_partecipanti,
               c.prezzo_mensile,
               c.immagine_url,
               d.nome      AS disciplina,
               d.icona_svg AS disciplina_icona,
               CONCAT(u.nome, ' ', u.cognome) AS istruttore,
               (
                   SELECT COUNT(*)
                   FROM iscrizioni i
                   WHERE i.corso_id = c.id AND i.stato = 'attiva'
               ) AS iscritti_attivi
           FROM corsi c
           LEFT JOIN discipline d ON d.id = c.disciplina_id
           LEFT JOIN utenti     u ON u.id = c.istruttore_id
           WHERE c.stato = 'attivo'";

$params = [];

if ($livello && in_array($livello, $livelliValidi, true)) {
    $sql    .= ' AND c.livello = ?';
    $params[] = $livello;
}

$sql .= ' ORDER BY c.id ASC';

$stmt = $db->prepare($sql);
$stmt->execute($params);
$corsi = $stmt->fetchAll();

$response = json_encode(['success' => true, 'data' => $corsi, 'count' => count($corsi)], JSON_UNESCAPED_UNICODE);
$_SESSION[$cacheKey]        = $response;
$_SESSION[$cacheKey . '_ts'] = time();
echo $response;
exit;
