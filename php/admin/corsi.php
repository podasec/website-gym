<?php
/**
 * Power Gym – Admin Gestione Corsi
 * CRUD corsi: lista, aggiungi, modifica, elimina
 * Solo admin
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../middleware/auth_check.php';
require_once __DIR__ . '/../middleware/csrf.php';

requireAdmin(false);

$db   = getDB();
$user = currentUser();

$action  = $_GET['action'] ?? 'list';
$message = '';
$error   = '';

// -------------------------------------------------------
// Azioni POST (aggiungi, modifica, elimina)
// -------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = $_POST['csrf_token'] ?? '';
    $stored    = $_SESSION['csrf_token'] ?? '';

    if (empty($stored) || !hash_equals($stored, $submitted)) {
        $error = 'Token CSRF non valido.';
    } else {
        unset($_SESSION['csrf_token']);
        $postAction = $_POST['action'] ?? '';

        // Raccoglie campi comuni
        $nome             = trim(htmlspecialchars($_POST['nome']             ?? '', ENT_QUOTES, 'UTF-8'));
        $descrizione      = trim(htmlspecialchars($_POST['descrizione']      ?? '', ENT_QUOTES, 'UTF-8'));
        $descrizione_breve = trim(htmlspecialchars($_POST['descrizione_breve'] ?? '', ENT_QUOTES, 'UTF-8'));
        $disciplina_id    = filter_var($_POST['disciplina_id']  ?? null, FILTER_VALIDATE_INT) ?: null;
        $istruttore_id    = filter_var($_POST['istruttore_id']  ?? null, FILTER_VALIDATE_INT) ?: null;
        $livello          = in_array($_POST['livello'] ?? '', ['principiante','intermedio','avanzato','tutti']) ? $_POST['livello'] : 'tutti';
        $durata_minuti    = (int) ($_POST['durata_minuti']    ?? 60);
        $max_partecipanti = (int) ($_POST['max_partecipanti'] ?? 20);
        $prezzo_mensile   = filter_var($_POST['prezzo_mensile'] ?? '0', FILTER_VALIDATE_FLOAT) ?: 0.0;
        $stato            = in_array($_POST['stato'] ?? '', ['attivo','sospeso','terminato']) ? $_POST['stato'] : 'attivo';
        $immagine_url     = trim(htmlspecialchars($_POST['immagine_url'] ?? '', ENT_QUOTES, 'UTF-8'));

        if ($postAction === 'insert') {
            if (strlen($nome) >= 3) {
                $db->prepare(
                    "INSERT INTO corsi
                         (nome, descrizione, descrizione_breve, disciplina_id, istruttore_id,
                          livello, durata_minuti, max_partecipanti, prezzo_mensile, stato, immagine_url)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?)"
                )->execute([
                    $nome, $descrizione, $descrizione_breve ?: null, $disciplina_id, $istruttore_id,
                    $livello, $durata_minuti, $max_partecipanti, $prezzo_mensile, $stato, $immagine_url ?: null,
                ]);
                $message = 'Corso aggiunto con successo.';
            } else {
                $error = 'Il nome del corso deve essere di almeno 3 caratteri.';
            }

        } elseif ($postAction === 'update') {
            $cid = (int) ($_POST['corso_id'] ?? 0);
            if ($cid > 0 && strlen($nome) >= 3) {
                $db->prepare(
                    "UPDATE corsi SET
                         nome=?, descrizione=?, descrizione_breve=?,
                         disciplina_id=?, istruttore_id=?,
                         livello=?, durata_minuti=?, max_partecipanti=?,
                         prezzo_mensile=?, stato=?, immagine_url=?, updated_at=NOW()
                     WHERE id=?"
                )->execute([
                    $nome, $descrizione, $descrizione_breve ?: null, $disciplina_id, $istruttore_id,
                    $livello, $durata_minuti, $max_partecipanti, $prezzo_mensile, $stato,
                    $immagine_url ?: null, $cid,
                ]);
                $message = 'Corso aggiornato con successo.';
            } else {
                $error = 'Dati non validi.';
            }

        } elseif ($postAction === 'delete') {
            $cid = (int) ($_POST['corso_id'] ?? 0);
            if ($cid > 0) {
                // Soft-delete: imposta stato = 'terminato'
                $db->prepare("UPDATE corsi SET stato='terminato', updated_at=NOW() WHERE id=?")->execute([$cid]);
                $message = 'Corso terminato (soft-delete).';
            }
        }
    }

    $msgParam = $message ? '&msg=' . urlencode($message) : ($error ? '&err=' . urlencode($error) : '');
    header('Location: corsi.php' . $msgParam);
    exit;
}

if (!empty($_GET['msg'])) $message = htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8');
if (!empty($_GET['err'])) $error   = htmlspecialchars($_GET['err'], ENT_QUOTES, 'UTF-8');

// -------------------------------------------------------
// Dati per select
// -------------------------------------------------------
$discipline  = $db->query("SELECT id, nome FROM discipline ORDER BY nome")->fetchAll();
$istruttori  = $db->query(
    "SELECT id, CONCAT(nome,' ',cognome) AS nome_completo
       FROM utenti WHERE ruolo IN ('admin','istruttore') AND stato='attivo' ORDER BY cognome"
)->fetchAll();

// -------------------------------------------------------
// Vista edit
// -------------------------------------------------------
$editCorso = null;
if ($action === 'edit') {
    $cid = (int) ($_GET['id'] ?? 0);
    if ($cid > 0) {
        $s = $db->prepare('SELECT * FROM corsi WHERE id = ? LIMIT 1');
        $s->execute([$cid]);
        $editCorso = $s->fetch();
    }
}

// -------------------------------------------------------
// Lista corsi
// -------------------------------------------------------
$corsi = $db->query(
    "SELECT c.id, c.nome, c.livello, c.stato, c.prezzo_mensile, c.max_partecipanti,
            d.nome AS disciplina,
            CONCAT(u.nome,' ',u.cognome) AS istruttore,
            (SELECT COUNT(*) FROM iscrizioni i WHERE i.corso_id=c.id AND i.stato='attiva') AS iscritti
       FROM corsi c
       LEFT JOIN discipline d ON d.id=c.disciplina_id
       LEFT JOIN utenti u     ON u.id=c.istruttore_id
      ORDER BY c.id DESC"
)->fetchAll();

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="it">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestione Corsi – Power Gym Admin</title>
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
    .badge-attivo    { background: rgba(34,197,94,.1);  color: #4ade80; }
    .badge-sospeso   { background: rgba(244,162,97,.1); color: #f4a261; }
    .badge-terminato { background: rgba(239,68,68,.1);  color: #f87171; }
    .btn { display: inline-block; padding: .35rem .8rem; border-radius: 6px; font-size: .78rem; font-weight: 600; cursor: pointer; border: none; text-decoration: none; }
    .btn-primary   { background: #e63946; color: #fff; }
    .btn-secondary { background: #2a2a2a; color: #ccc; border: 1px solid #333; }
    .btn-danger    { background: rgba(239,68,68,.15); color: #f87171; border: 1px solid rgba(239,68,68,.3); }
    .card { background: #1a1a1a; border: 1px solid #2a2a2a; border-radius: 12px; padding: 1.5rem; max-width: 700px; margin-bottom: 2rem; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: .35rem; }
    .form-group.full { grid-column: 1 / -1; }
    label { font-size: .78rem; color: #888; }
    input, select, textarea { background: #111; border: 1px solid #333; border-radius: 6px; padding: .5rem .75rem; color: #e0e0e0; font-size: .85rem; width: 100%; }
    input:focus, select:focus, textarea:focus { outline: none; border-color: #e63946; }
    nav.topnav { display: flex; gap: 1rem; margin-bottom: 2rem; }
    nav.topnav a { color: #e63946; text-decoration: none; font-size: .85rem; }
    nav.topnav a::before { content: '← '; }
  </style>
</head>
<body>

<div class="topbar">
  <a href="dashboard.php">&#9651; Power Gym Admin</a>
  <span style="font-size:.8rem;color:#888;"><?= htmlspecialchars($user['nome'] . ' ' . $user['cognome'], ENT_QUOTES, 'UTF-8') ?></span>
</div>

<div class="container">

  <nav class="topnav">
    <a href="dashboard.php">Dashboard</a>
    <a href="utenti.php">Utenti</a>
  </nav>

  <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
  <?php if ($error):   ?><div class="alert alert-error"><?= $error ?></div><?php endif; ?>

  <?php if ($action === 'new' || ($action === 'edit' && $editCorso)): ?>
    <!-- ─── FORM AGGIUNGI / MODIFICA CORSO ─── -->
    <h1><?= $action === 'edit' ? 'Modifica Corso #' . (int) $editCorso['id'] : 'Aggiungi Nuovo Corso' ?></h1>

    <div class="card">
      <form method="POST" action="corsi.php">
        <input type="hidden" name="action"     value="<?= $action === 'edit' ? 'update' : 'insert' ?>">
        <?php if ($action === 'edit'): ?>
          <input type="hidden" name="corso_id" value="<?= (int) $editCorso['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-grid">
          <div class="form-group full">
            <label>Nome Corso *</label>
            <input type="text" name="nome" value="<?= htmlspecialchars($editCorso['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
          </div>
          <div class="form-group full">
            <label>Descrizione Breve (max 500 car.)</label>
            <input type="text" name="descrizione_breve" value="<?= htmlspecialchars($editCorso['descrizione_breve'] ?? '', ENT_QUOTES, 'UTF-8') ?>" maxlength="500">
          </div>
          <div class="form-group full">
            <label>Descrizione Completa</label>
            <textarea name="descrizione" rows="4"><?= htmlspecialchars($editCorso['descrizione'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
          </div>
          <div class="form-group">
            <label>Disciplina</label>
            <select name="disciplina_id">
              <option value="">— Seleziona —</option>
              <?php foreach ($discipline as $d): ?>
                <option value="<?= (int) $d['id'] ?>" <?= ($editCorso['disciplina_id'] ?? '') == $d['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($d['nome'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Istruttore</label>
            <select name="istruttore_id">
              <option value="">— Seleziona —</option>
              <?php foreach ($istruttori as $i): ?>
                <option value="<?= (int) $i['id'] ?>" <?= ($editCorso['istruttore_id'] ?? '') == $i['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($i['nome_completo'], ENT_QUOTES, 'UTF-8') ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Livello</label>
            <select name="livello">
              <?php foreach (['principiante','intermedio','avanzato','tutti'] as $lv): ?>
                <option value="<?= $lv ?>" <?= ($editCorso['livello'] ?? 'tutti') === $lv ? 'selected' : '' ?>><?= ucfirst($lv) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Stato</label>
            <select name="stato">
              <?php foreach (['attivo','sospeso','terminato'] as $st): ?>
                <option value="<?= $st ?>" <?= ($editCorso['stato'] ?? 'attivo') === $st ? 'selected' : '' ?>><?= ucfirst($st) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Durata (minuti)</label>
            <input type="number" name="durata_minuti" value="<?= (int) ($editCorso['durata_minuti'] ?? 60) ?>" min="15" max="300">
          </div>
          <div class="form-group">
            <label>Max Partecipanti</label>
            <input type="number" name="max_partecipanti" value="<?= (int) ($editCorso['max_partecipanti'] ?? 20) ?>" min="1" max="200">
          </div>
          <div class="form-group">
            <label>Prezzo Mensile (€)</label>
            <input type="number" name="prezzo_mensile" step="0.01" value="<?= number_format((float) ($editCorso['prezzo_mensile'] ?? 0), 2, '.', '') ?>" min="0">
          </div>
          <div class="form-group">
            <label>URL Immagine</label>
            <input type="url" name="immagine_url" value="<?= htmlspecialchars($editCorso['immagine_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
          </div>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:.5rem;">
          <button type="submit" class="btn btn-primary"><?= $action === 'edit' ? 'Salva Modifiche' : 'Aggiungi Corso' ?></button>
          <a href="corsi.php" class="btn btn-secondary">Annulla</a>
        </div>
      </form>
    </div>

  <?php else: ?>
    <!-- ─── LISTA CORSI ─── -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
      <h1>Gestione Corsi <small style="font-size:.9rem;color:#666;">(<?= count($corsi) ?> totali)</small></h1>
      <a href="corsi.php?action=new" class="btn btn-primary">+ Nuovo Corso</a>
    </div>

    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Nome</th>
          <th>Disciplina</th>
          <th>Istruttore</th>
          <th>Livello</th>
          <th>Prezzo</th>
          <th>Iscritti</th>
          <th>Stato</th>
          <th>Azioni</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($corsi as $c): ?>
        <tr>
          <td><?= (int) $c['id'] ?></td>
          <td><?= htmlspecialchars($c['nome'], ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($c['disciplina'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($c['istruttore'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars($c['livello'], ENT_QUOTES, 'UTF-8') ?></td>
          <td>&euro;<?= number_format((float) $c['prezzo_mensile'], 2, ',', '.') ?></td>
          <td><?= (int) $c['iscritti'] ?> / <?= (int) $c['max_partecipanti'] ?></td>
          <td><span class="badge badge-<?= htmlspecialchars($c['stato'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($c['stato'], ENT_QUOTES, 'UTF-8') ?></span></td>
          <td style="display:flex;gap:.5rem;align-items:center;">
            <a href="corsi.php?action=edit&id=<?= (int) $c['id'] ?>" class="btn btn-secondary">Modifica</a>
            <?php if ($c['stato'] !== 'terminato'): ?>
            <form method="POST" action="corsi.php" style="display:inline;" onsubmit="return confirm('Terminare questo corso?')">
              <input type="hidden" name="action"     value="delete">
              <input type="hidden" name="corso_id"   value="<?= (int) $c['id'] ?>">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCsrfToken(), ENT_QUOTES, 'UTF-8') ?>">
              <button type="submit" class="btn btn-danger">Termina</button>
            </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($corsi)): ?>
          <tr><td colspan="9" style="text-align:center;color:#555;padding:2rem;">Nessun corso.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>

  <?php endif; ?>

</div>
</body>
</html>
