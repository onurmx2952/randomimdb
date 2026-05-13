<?php

require __DIR__ . '/storage.php';

$config = roll_config();
$token = (string) ($_GET['token'] ?? '');
if (!hash_equals($config['admin_token'], $token)) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$db = roll_db();
$selectedUser = roll_text($_GET['user'] ?? '', 80);

$totals = $db->query(
    'SELECT
        COUNT(*) AS rolls,
        COUNT(DISTINCT user_id) AS users,
        COUNT(DISTINCT movie_id) AS movies
     FROM roll_events'
)->fetch(PDO::FETCH_ASSOC);

$users = $db->query(
    'SELECT user_id, COUNT(*) AS rolls, MAX(created_at) AS last_roll
     FROM roll_events
     GROUP BY user_id
     ORDER BY last_roll DESC
     LIMIT 200'
)->fetchAll(PDO::FETCH_ASSOC);

$popular = $db->query(
    'SELECT movie_id, movie_name, imdb_url, COUNT(*) AS rolls
     FROM roll_events
     GROUP BY movie_id, movie_name, imdb_url
     ORDER BY rolls DESC, movie_name ASC
     LIMIT 50'
)->fetchAll(PDO::FETCH_ASSOC);

if ($selectedUser !== '') {
    $stmt = $db->prepare(
        'SELECT *
         FROM roll_events
         WHERE user_id = :user_id
         ORDER BY created_at DESC
         LIMIT 500'
    );
    $stmt->execute([':user_id' => $selectedUser]);
    $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);
} else {
    $recent = $db->query(
        'SELECT *
         FROM roll_events
         ORDER BY created_at DESC
         LIMIT 200'
    )->fetchAll(PDO::FETCH_ASSOC);
}

$self = 'admin.php?token=' . rawurlencode($config['admin_token']);
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>IMDbRoll Roll Analytics</title>
<style>
  :root { color-scheme: dark; --bg:#080a0e; --panel:#10141b; --line:rgba(255,255,255,.09); --text:#eef0f5; --muted:#8990a3; --accent:#e63946; }
  * { box-sizing: border-box; }
  body { margin:0; background:var(--bg); color:var(--text); font:14px/1.45 system-ui,-apple-system,Segoe UI,sans-serif; }
  main { width:min(1180px, calc(100% - 32px)); margin:28px auto 60px; }
  header { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; margin-bottom:22px; }
  h1 { margin:0; font-size:24px; font-weight:650; }
  a { color:inherit; }
  .muted { color:var(--muted); }
  .grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:12px; margin-bottom:18px; }
  .card { background:var(--panel); border:1px solid var(--line); border-radius:10px; padding:16px; }
  .metric { font-size:34px; font-weight:720; margin-top:6px; }
  .section { margin-top:18px; }
  .section-head { display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px; }
  table { width:100%; border-collapse:collapse; background:var(--panel); border:1px solid var(--line); border-radius:10px; overflow:hidden; }
  th, td { padding:10px 12px; border-bottom:1px solid var(--line); text-align:left; vertical-align:top; }
  th { color:var(--muted); font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:.04em; }
  tr:last-child td { border-bottom:0; }
  .pill { display:inline-flex; padding:5px 8px; border:1px solid var(--line); border-radius:999px; color:var(--muted); text-decoration:none; }
  .movie { max-width:360px; }
  .nowrap { white-space:nowrap; }
  @media (max-width: 760px) { .grid { grid-template-columns:1fr; } table { font-size:12px; } th,td{ padding:8px; } }
</style>
</head>
<body>
<main>
  <header>
    <div>
      <h1>IMDbRoll Roll Analytics</h1>
      <div class="muted">Anonim kullanıcı bazlı Roll geçmişi</div>
    </div>
    <?php if ($selectedUser !== ''): ?>
      <a class="pill" href="<?= roll_h($self) ?>">Tüm roll kayıtları</a>
    <?php endif; ?>
  </header>

  <section class="grid">
    <div class="card"><div class="muted">Roll</div><div class="metric"><?= roll_h($totals['rolls'] ?? 0) ?></div></div>
    <div class="card"><div class="muted">Kullanıcı</div><div class="metric"><?= roll_h($totals['users'] ?? 0) ?></div></div>
    <div class="card"><div class="muted">Film</div><div class="metric"><?= roll_h($totals['movies'] ?? 0) ?></div></div>
  </section>

  <section class="section">
    <div class="section-head">
      <h2><?= $selectedUser !== '' ? 'Seçili Kullanıcının Roll Geçmişi' : 'Son Roll Kayıtları' ?></h2>
      <?php if ($selectedUser !== ''): ?><span class="pill"><?= roll_h($selectedUser) ?></span><?php endif; ?>
    </div>
    <table>
      <thead><tr><th>Zaman</th><th>Kullanıcı</th><th>Film</th><th>IMDb</th></tr></thead>
      <tbody>
      <?php foreach ($recent as $row): ?>
        <tr>
          <td class="nowrap"><?= roll_h($row['created_at']) ?></td>
          <td><a class="pill" href="<?= roll_h($self . '&user=' . rawurlencode($row['user_id'])) ?>"><?= roll_h(substr($row['user_id'], 0, 18)) ?></a></td>
          <td class="movie"><?= roll_h($row['movie_name']) ?> <span class="muted"><?= roll_h($row['movie_id']) ?></span></td>
          <td><a class="pill" href="<?= roll_h($row['imdb_url']) ?>" target="_blank" rel="noopener noreferrer">IMDb</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="section">
    <div class="section-head"><h2>Kullanıcılar</h2></div>
    <table>
      <thead><tr><th>Kullanıcı</th><th>Roll</th><th>Son Roll</th></tr></thead>
      <tbody>
      <?php foreach ($users as $row): ?>
        <tr>
          <td><a class="pill" href="<?= roll_h($self . '&user=' . rawurlencode($row['user_id'])) ?>"><?= roll_h(substr($row['user_id'], 0, 18)) ?></a></td>
          <td><?= roll_h($row['rolls']) ?></td>
          <td class="nowrap"><?= roll_h($row['last_roll']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="section">
    <div class="section-head"><h2>En Çok Roll Edilenler</h2></div>
    <table>
      <thead><tr><th>Roll</th><th>Film</th><th>IMDb</th></tr></thead>
      <tbody>
      <?php foreach ($popular as $row): ?>
        <tr>
          <td><?= roll_h($row['rolls']) ?></td>
          <td class="movie"><?= roll_h($row['movie_name']) ?> <span class="muted"><?= roll_h($row['movie_id']) ?></span></td>
          <td><a class="pill" href="<?= roll_h($row['imdb_url']) ?>" target="_blank" rel="noopener noreferrer">IMDb</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</main>
</body>
</html>

