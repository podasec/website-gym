<?php
/**
 * Power Gym – Admin Gestione Utenti
 * CRUD utenti con paginazione (20 per pagina), soft-delete
 * Solo admin
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../middleware/csrf.php';

requireAdmin(false);

$db   = getDB();
$user = currentUser();

$action  = $_GET['action']  ?? 'list';
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$message = '';
$error   = '';

// -------------------------------------------------------
// Azioni POST
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Valida CSRF
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';
    if (empty($stored) || !hash_equals($stored, $submitted)) {
        $error = 'Token CSRF non valido. Ricaricare la pagina.';
    } else {
        unset($_SESSION['csrf_token']);
        $postAction = $_POST['action'] ?? '';

        if ($postAction === 'update') {
            $uid     = (int) ($_POST['user_id'] ?? 0);
            $nome    = trim(htmlspecialchars($_POST['nome']    ?? '', ENT_QUOTES, 'UTF-8'));
            $cognome = trim(htmlspecialchars($_POST['cognome'] ?? '', ENT_QUOTES, 'UTF-8'));
            $ruolo   = in_array($_POST['ruolo'] ?? '', ['admin','istruttore','cliente']) ? $_POST['ruolo'] : 'cliente';
            $stato   = in_array($_POST['stato'] ?? '', ['attivo','inattivo','sospeso'])  ? $_POST['stato'] : 'attivo';
            $note    = trim(htmlspecialchars($_POST['note']    ?? '', ENT_QUOTES, 'UTF-8'));

            if ($uid > 0 && strlen($nome) >= 2 && strlen($cognome) >= 2) {
                $db->prepare(
                    "UPDATE utenti SET nome=?, cognome=?, ruolo=?, stato=?, note=?, updated_at=NOW() WHERE id=?"
                )->execute([$nome, $cognome, $ruolo, $stato, $note ?: null, $uid]);
                $message = 'Utente aggiornato con successo.';
            } else {
                $error = 'Dati non validi.';
            }

        } elseif ($postAction === 'soft_delete') {
            $uid = (int) ($_POST['user_id'] ?? 0);
            if ($uid > 0 && $uid !== (int) $user['id']) {
                $db->prepare(
                    "UPDATE utenti SET stato='inattivo', updated_at=NOW() WHERE id=?"
                )->execute([$uid]);
                $message = 'Utente disattivato.';
            } else {
                $error = 'Operazione non consentita.';
            }
        }
    }
    // Redirect PRG
    $msgParam = $message ? '&msg=' . urlencode($message) : ($error ? '&err=' . urlencode($error) : '');
    header('Location: utenti.php?page=' . $page . $msgParam);
    exit;
}

// Messaggi da redirect
if (!empty($_GET['msg'])) $message = htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8');
if (!empty($_GET['err'])) $error   = htmlspecialchars($_GET['err'], ENT_QUOTES, 'UTF-8');

// -------------------------------------------------------
// Vista dettaglio utente
// -------------------------------------------------------
$detailUser = null;
if ($action === 'edit') {
    $uid = (int) ($_GET['id'] ?? 0);
    if ($uid > 0) {
        $s = $db->prepare('SELECT * FROM utenti WHERE id = ? LIMIT 1');
        $s->execute([$uid]);
        $detailUser = $s->fetch();
    }
}

// -------------------------------------------------------
// Lista utenti con paginazione
// -------------------------------------------------------
$stmtTotal = $db->query("SELECT COUNT(*) AS cnt FROM utenti");
$total     = (int) $stmtTotal->fetch()['cnt'];
$pages     = (int) ceil($total / $perPage);

$stmtList = $db->prepare(
    "SELECT id, nome, cognome, email, ruolo, stato, telefono, created_at
       FROM utenti
      ORDER BY id DESC
      LIMIT ? OFFSET ?"
);
$stmtList->execute([$perPage, $offset]);
$utenti = $stmtList->fetchAll();

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestione Utenti – Power Gym Admin</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: system-ui, sans-serif; background: #0f0f0f; color: #e0e0e0; }
    .topbar { background: #1a1a1a; border-bottom: 1px solid #2a2a2a; padding: .75rem 2rem; display: flex; align-items: center; justify-content: space-between; }
    .topbar a { color: #e63946; text-decoration: none; font-weight: 700; }
    .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
    h1 { font-size: 1.4rem; margin-bottom: 1.5rem; color: #fff; }
    .alert { padding: .75rem 1rem; border-radius: 8px; margin-bottom: 1rem; font-size: .85rem; }
    .alert-success { background: rgba(34,197,94,.12); color: #4ade80; border: 1px solid rgba(34,197,94,.2); }
    .alert-error   { background: rgba(239,68,68,.12);  color: #f87171; border: 1px solid rgba(239,68,68,.2); }
    table { width: 100%; border-collapse: collapse; font-size: .85rem; }
    th { text-align: left; padding: .6rem .8rem; background: #1a1a1a; color: #888; font-size: .75rem; text-transform: uppercase; }
    td { padding: .65rem .8rem; border-top: 1px solid #222; color: #ccc; }
    .badge { display: inline-block; padding: .2rem .5rem; border-radius: 4px; font-size: .7rem; font-weight: 700; }
    .badge-admin { background: rgba(230,57,70,.15); color: #e63946; }
    .badge-istruttore { background: rgba(244,162,97,.15); color: #f4a261; }
    .badge-cliente { background: rgba(34,197,94,.1); color: #4ade80; }
    .badge-attivo { background: rgba(34,197,94,.1); color: #4ade80; }
    .badge-inattivo, .badge-sospeso { background: rgba(239,68,68,.1); color: #f87171; }
    .btn { display: inline-block; padding: .35rem .8rem; border-radius: 6px; font-size: .78rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
    .btn-primary { background: #e63946; color: #fff; }
    .btn-secondary { background: #2a2a2a; color: #ccc; border: 1px solid #333; }
    .btn-danger { background: rgba(239,68,68,.15); color: #f87171; border: 1px solid rgba(239,68,68,.3); }
    .pagination { display: flex; gap: .5rem; margin-top: 1.5rem; flex-wrap: wrap; }
    .pagination a { padding: .4rem .8rem; border-radius: 6px; background: #1a1a1a; border: 1px solid #2a2a2a; color: #ccc; text-decoration: none; font-size: .82rem; }
    .pagination a.active { background: #e63946; color: #fff; border-color: #e63946; }
    .back { margin-bottom: 1.5rem; }
    .edit-form { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 12px; padding: 1.5rem; max-width: 600px; }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: .35rem; }
    label { font-size: .78rem; color: #888; }
    input, select, textarea { background: #111; border: 1px solid #333; border-radius: 6px; padding: .5rem .75rem; color: #e0e0e0; font-size: .85rem; width: 100%; }
    input:focus, select:focus { outline: none; border-color: #e63946; }
    nav.topnav { display: flex; gap: 1rem; margin-bottom: 2rem; }
    nav.topnav a { color: #e63946; text-decoration: none; font-size: .85rem; }
    nav.topnav a::before { content: '← '; }
  </style>
</head>
<body>

<div class="topbar">
  <a href="dashboard.php">&#9651; Power Gym Admin</a>
  <span style="font-size:.8rem;color:#888;">
    <?= htmlspecialchars($user['nome'] . ' ' . $user['cognome'], ENT_QUOTES, 'UTF-8') ?>
  </span>
</div>

<div class="container">

  <nav class="topnav">
    <a href="dashboard.php">Dashboard</a>
    <a href="corsi.php">Corsi</a>
  </nav>

  <?php if ($message): ?>
    <div class="alert alert-success"><?= $message ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="alert alert-error"><?= $error ?></div>
  <?php endif; ?>

  <?php if ($action === 'edit' && $detailUser): ?>
    <!-- ─── FORM MODIFICA UTENTE ─── -->
    <h1>Modifica Utente #<?= (int) $detailUser['id'] ?></h1>

    <div class="edit-form">
      <form method="POST" action="utenti.php">
        <input type="hidden" name="action"     value="update">
        <input type="hidden" name="user_id"    value="<?= (int) $detailUser['id'] ?>">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-row">
          <div class="form-group">
            <label>Nome</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($detailUser['nome'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <div class="form-group">
            <label>Cognome</label>
            <input type="text" name="cognome" value="<?= htmlspecialchars($detailUser['cognome'], ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label>Ruolo</label>
            <select name="ruolo">
              <?php foreach (['admin','istruttore','cliente'] as $r): ?>
                <option value="<?= $r ?>" <?= $detailUser['ruolo'] === $r ? 'selected' : '' ?>><?= ucfirst($r) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Stato</label>
            <select name="stato">
              <?php foreach (['attivo','inattivo','sospeso'] as $s): ?>
                <option value="<?= $s ?>" <?= $detailUser['stato'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <div class="form-group" style="margin-bottom:1rem;">
          <label>Note interne</label>
          <textarea name="note" rows="3"><?= htmlspecialchars($detailUser['note'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div style="display:flex;gap:.75rem;">
          <button type="submit" class="btn btn-primary">Salva Modifiche</button>
          <a href="utenti.php" class="btn btn-secondary">Annulla</a>
        </div>
      </form>
    </div>

  <?php else: ?>
    <!-- ─── LISTA UTENTI ─── -->
    <h1>Gestione Utenti <small style="font-size:.9rem;color:#666;">(<?= $total ?> totali)</small></h1>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nome</th>
          <th>Email</th>
          <th>Telefono</th>
          <th>Ruolo</th>
          <th>Stato</th>
          <th>Registrato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($utenti as $u): ?>
        <tr>
          <td><?= (int) $u['id'] ?></td>
          <td><?= htmlspecialchars($u['nome'] . ' ' . $u['cognome'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($u['telefono'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td><span class="badge badge-<?= htmlspecialchars($u['ruolo'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($u['ruolo'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><span class="badge badge-<?= htmlspecialchars($u['stato'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($u['stato'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td><?= htmlspecialchars(substr($u['created_at'] ?? '', 0, 10), ENT_QUOTES, 'UTF-8') ?></td>
          <td style="display:flex;gap:.5rem;align-items:center;">
            <a href="utenti.php?action=edit&id=<?= (int) $u['id'] ?>" class="btn btn-secondary">Modifica</a>
            <?php if ($u['id'] !== $user['id'] && $u['stato'] !== 'inattivo'): ?>
            <form method="POST" action="utenti.php" style="display:inline;" onsubmit="return confirm('Disattivare utente?')">
              <input type="hidden" name="action"     value="soft_delete">
              <input type="hidden" name="user_id"    value="<?= (int) $u['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="btn btn-danger">Disattiva</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($utenti)): ?>
          <tr><td colspan="8" style="text-align:center;color:#555;padding:2rem;">Nessun utente.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

    <!-- Paginazione -->
    <?php if ($pages > 1): ?>
    <div class="pagination">
      <?php for ($p = 1; $p <= $pages; $p++): ?>
        <a href="utenti.php?page=<?= $p ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

  <?php endif; ?>

</div>
</body>
</html>
