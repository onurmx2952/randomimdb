<?php

function roll_config(): array
{
    return require __DIR__ . '/config.php';
}

function roll_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = roll_config();
    $dir = dirname($config['db_path']);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $pdo = new PDO('sqlite:' . $config['db_path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS roll_events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id TEXT NOT NULL,
            movie_id TEXT NOT NULL,
            movie_name TEXT NOT NULL,
            imdb_url TEXT NOT NULL,
            page_url TEXT NOT NULL DEFAULT "",
            user_agent TEXT NOT NULL DEFAULT "",
            ip_hash TEXT NOT NULL DEFAULT "",
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_roll_events_created_at ON roll_events(created_at)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_roll_events_user_id ON roll_events(user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_roll_events_movie_id ON roll_events(movie_id)');

    return $pdo;
}

function roll_text($value, int $limit): string
{
    $text = trim((string) $value);
    if (function_exists('mb_substr')) {
        return mb_substr($text, 0, $limit, 'UTF-8');
    }
    return substr($text, 0, $limit);
}

function roll_h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

