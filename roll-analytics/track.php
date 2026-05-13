<?php

require __DIR__ . '/storage.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$userId = roll_text($data['user_id'] ?? '', 80);
$movieId = roll_text($data['movie_id'] ?? '', 24);
$movieName = roll_text($data['movie_name'] ?? '', 180);
$imdbUrl = roll_text($data['imdb_url'] ?? '', 240);
$pageUrl = roll_text($data['page_url'] ?? '', 240);

if ($userId === '' || !preg_match('/^tt\d+$/', $movieId)) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'error' => 'invalid_payload']);
    exit;
}

if ($imdbUrl === '') {
    $imdbUrl = 'https://www.imdb.com/title/' . $movieId . '/';
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$ua = roll_text($_SERVER['HTTP_USER_AGENT'] ?? '', 240);
$ipHash = $ip !== '' ? hash('sha256', $ip . '|' . $userId) : '';

$stmt = roll_db()->prepare(
    'INSERT INTO roll_events (user_id, movie_id, movie_name, imdb_url, page_url, user_agent, ip_hash)
     VALUES (:user_id, :movie_id, :movie_name, :imdb_url, :page_url, :user_agent, :ip_hash)'
);
$stmt->execute([
    ':user_id' => $userId,
    ':movie_id' => $movieId,
    ':movie_name' => $movieName,
    ':imdb_url' => $imdbUrl,
    ':page_url' => $pageUrl,
    ':user_agent' => $ua,
    ':ip_hash' => $ipHash,
]);

echo json_encode(['ok' => true]);

