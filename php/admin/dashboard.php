<?php
/**
 * Power Gym – Admin Dashboard
 * Richiede: sessione admin
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth_check.php';

// Verifica ruolo admin (redirect, non JSON)
requireAdmin(false);

$db = getDB();

// -------------------------------------------------------
// Statistiche
// -------------------------------------------------------

// Totale iscritti (clienti attivi)
$stmtIscritti = $db->query(
    "SELECT COUNT(*) AS cnt FROM utenti WHERE ruolo = 'cliente' AND stato = 'attivo'"
);
$totaleIscritti = (int) $stmtIscritti->fetch()['cnt'];

// Iscrizioni attive ai corsi
$stmtIscrizioniAttive = $db->query(
    "SELECT COUNT(*) AS cnt FROM iscrizioni WHERE stato = 'attiva'"
);
$iscrizioniAttive = (int) $stmtIscrizioniAttive->fetch()['cnt'];

// Messaggi non letti
$stmtMessaggi = $db->query(
    "SELECT COUNT(*) AS cnt FROM contatti WHERE stato = 'nuovo'"
);
$messaggiNonLetti = (int) $stmtMessaggi->fetch()['cnt'];

// Entrate mensili (iscrizioni create questo mese)
$stmtEntrate = $db->query(
    "SELECT COALESCE(SUM(prezzo_pagato), 0) AS totale
       FROM iscrizioni
      WHERE stato = 'attiva'
        AND MONTH(created_at) = MONTH(CURDATE())
        AND YEAR(created_at)  = YEAR(CURDATE())"
);
$entrateMensili = (float) $stmtEntrate->fetch()['totale'];

// Ultime 10 iscrizioni
$stmtUltimeIscrizioni = $db->query(
    "SELECT
         i.id,
         i.data_iscrizione,
         i.stato,
         i.prezzo_pagato,
         CONCAT(u.nome, ' ', u.cognome) AS utente,
         u.email,
         c.nome AS corso
       FROM iscrizioni i
       JOIN utenti u ON u.id = i.utente_id
       JOIN corsi  c ON c.id = i.corso_id
      ORDER BY i.id DESC
      LIMIT 10"
);
$ultimeIscrizioni = $stmtUltimeIscrizioni->fetchAll();

// Ultimi 10 messaggi di contatto
$stmtUltimiMessaggi = $db->query(
    "SELECT id, nome, cognome, email, oggetto, stato, created_at
       FROM contatti
      ORDER BY id DESC
      LIMIT 10"
);
$ultimiMessaggi = $stmtUltimiMessaggi->fetchAll();

$user = currentUser();
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin – Power Gym</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, sans-serif; background: #0f0f0f; color: #e0e0e0; min-height: 100vh; }
    .topbar { background: #1a1a1a; border-bottom: 1px solid #2a2a2a; padding: .75rem 2rem; display: flex; align-items: center; justify-content: space-between; }
    .topbar a { color: #e63946; text-decoration: none; font-weight: 700; font-size: 1.2rem; }
    .topbar-user { font-size: .85rem; color: #aaa; }
    .topbar-user a { color: #e63946; margin-left: .5rem; font-size: .8rem; }
    .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
    h1 { font-size: 1.6rem; margin-bottom: 2rem; color: #fff; }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.5rem; margin-bottom: 3rem; }
    .stat-card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 12px; padding: 1.5rem; }
    .stat-card .label { font-size: .8rem; color: #888; text-transform: uppercase; letter-spacing: .05em; margin-bottom: .5rem; }
    .stat-card .value { font-size: 2.2rem; font-weight: 700; color: #e63946; }
    .stat-card .sub { font-size: .75rem; color: #666; margin-top: .25rem; }
    .section { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; }
    .section h2 { font-size: 1rem; margin-bottom: 1rem; color: #ccc; }
    table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    th { text-align: left; padding: .6rem .8rem; background: #222; color: #888; font-size: .75rem; text-transform: uppercase; }
    td { padding: .65rem .8rem; border-top: 1px solid #222; color: #ccc; }
    .badge { display: inline-block; padding: .2rem .55rem; border-radius: 4px; font-size: .7rem; font-weight: 700; }
    .badge-attiva, .badge-nuovo { background: rgba(34,197,94,.15); color: #4ade80; }
    .badge-scaduta, .badge-letto { background: rgba(107,114,128,.15); color: #9ca3af; }
    .badge-cancellata, .badge-archiviato { background: rgba(239,68,68,.15); color: #f87171; }
    .badge-risposto { background: rgba(59,130,246,.15); color: #60a5fa; }
    .nav-links { display: flex; gap: 1rem; margin-bottom: 2rem; flex-wrap: wrap; }
    .nav-links a { background: #1a1a1a; border: 1px solid #2a2a2a; color: #ccc; text-decoration: none; padding: .5rem 1rem; border-radius: 8px; font-size: .85rem; transition: border-color .2s; }
    .nav-links a:hover { border-color: #e63946; color: #e63946; }
  </style>
</head>
<body>

<div class="topbar">
  <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/index.html">&#9651; Power Gym</a>
  <div class="topbar-user">
    Admin: <?= htmlspecialchars($user['nome'] . ' ' . $user['cognome'], ENT_QUOTES, 'UTF-8') ?>
    <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/php/auth/logout.php">Esci</a>
  </div>
</div>

<div class="container">
  <h1>Dashboard Amministratore</h1>

  <div class="nav-links">
    <a href="utenti.php">Gestione Utenti</a>
    <a href="corsi.php">Gestione Corsi</a>
    <a href="<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>/contatti.html">Sito</a>
  </div>

  <!-- Statistiche -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="label">Clienti Attivi</div>
      <div class="value"><?= $totaleIscritti ?></div>
      <div class="sub">Utenti con stato attivo</div>
    </div>
    <div class="stat-card">
      <div class="label">Iscrizioni Attive</div>
      <div class="value"><?= $iscrizioniAttive ?></div>
      <div class="sub">Ai corsi correnti</div>
    </div>
    <div class="stat-card">
      <div class="label">Messaggi Non Letti</div>
      <div class="value"><?= $messaggiNonLetti ?></div>
      <div class="sub">Dal form contatti</div>
    </div>
    <div class="stat-card">
      <div class="label">Entrate Mensili</div>
      <div class="value">&euro;<?= number_format($entrateMensili, 2, ',', '.') ?></div>
      <div class="sub">Mese corrente</div>
    </div>
  </div>

  <!-- Ultime iscrizioni -->
  <div class="section">
    <h2>Ultime Iscrizioni ai Corsi</h2>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Utente</th>
          <th>Email</th>
          <th>Corso</th>
          <th>Data</th>
          <th>Prezzo</th>
          <th>Stato</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ultimeIscrizioni as $row): ?>
        <tr>
          <td><?= (int) $row['id'] ?></td>
          <td><?= htmlspecialchars($row['utente'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($row['email'],  ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($row['corso'],  ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($row['data_iscrizione'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>&euro;<?= number_format((float) $row['prezzo_pagato'], 2, ',', '.') ?></td>
          <td><span class="badge badge-<?= htmlspecialchars($row['stato'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['stato'], ENT_QUOTES, 'UTF-8') ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($ultimeIscrizioni)): ?>
        <tr><td colspan="7" style="text-align:center;color:#555;padding:2rem;">Nessuna iscrizione ancora.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Ultimi messaggi -->
  <div class="section">
    <h2>Ultimi Messaggi di Contatto</h2>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nome</th>
          <th>Email</th>
          <th>Oggetto</th>
          <th>Data</th>
          <th>Stato</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ultimiMessaggi as $row): ?>
        <tr>
          <td><?= (int) $row['id'] ?></td>
          <td><?= htmlspecialchars($row['nome'] . ' ' . $row['cognome'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($row['email'],   ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($row['oggetto'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($row['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge badge-<?= htmlspecialchars($row['stato'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($row['stato'], ENT_QUOTES, 'UTF-8') ?></span></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($ultimiMessaggi)): ?>
        <tr><td colspan="6" style="text-align:center;color:#555;padding:2rem;">Nessun messaggio ancora.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

</div>
</body>
</html>
