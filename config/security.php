<?php
// Shared by every HTTP entry point, before authentication or mutations.
function kidora_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') return true;
    $trusted = array_filter(array_map('trim', explode(',', getenv('KIDORA_TRUSTED_PROXIES') ?: '')));
    return in_array($_SERVER['REMOTE_ADDR'] ?? '', $trusted, true)
        && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function kidora_cookie_options(int $expires = 0): array {
    return ['expires' => $expires, 'path' => BASE_PATH ?: '/', 'secure' => kidora_https()
        || str_starts_with(KIDORA_APP_URL, 'https://'), 'httponly' => true, 'samesite' => 'Lax'];
}

function csrf_token(): string {
    return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

if (PHP_SAPI !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        $options = kidora_cookie_options();
        unset($options['expires']);
        $options['lifetime'] = 0;
        session_set_cookie_params($options);
        session_start();
    }
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header('X-Frame-Options: SAMEORIGIN');
    header('Cache-Control: private, no-store');
    if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'HEAD', 'OPTIONS'], true)) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
        if (!is_string($token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            exit(json_encode(['ok' => false, 'error' => 'Invalid CSRF token. Reload the page.']));
        }
    }
    csrf_token();
}

function kidora_authenticate_session(PDO $pdo): void {
    if (empty($_SESSION['child_id'])) return;
    $st = $pdo->prepare('SELECT auth_version FROM children WHERE id = ?');
    $st->execute([$_SESSION['child_id']]);
    $version = $st->fetchColumn();
    if ($version === false || !isset($_SESSION['auth_version']) || (int)$version !== (int)$_SESSION['auth_version']) {
        unset($_SESSION['child_id'], $_SESSION['child_name'], $_SESSION['auth_version']);
        session_regenerate_id(true);
    }
}

// Database counters are shared across sessions/processes; IP and account limits
// are independent, so changing either alone does not bypass throttling.
function kidora_login_allowed(PDO $pdo, string $scope, string $email): bool {
    $now = time();
    $keys = [hash('sha256', $scope . ':ip:' . ($_SERVER['REMOTE_ADDR'] ?? 'cli')) => 40,
        hash('sha256', $scope . ':email:' . strtolower($email)) => 10];
    foreach ($keys as $key => $limit) {
        $sql = DB_DRIVER === 'mysql'
            ? 'INSERT INTO auth_attempts (attempt_key, attempts, window_start) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts = IF(window_start < ?,1,attempts+1), window_start = IF(window_start < ?,VALUES(window_start),window_start)'
            : 'INSERT INTO auth_attempts (attempt_key, attempts, window_start) VALUES (?,1,?) ON CONFLICT(attempt_key) DO UPDATE SET attempts = CASE WHEN window_start < ? THEN 1 ELSE attempts+1 END, window_start = CASE WHEN window_start < ? THEN excluded.window_start ELSE window_start END';
        $pdo->prepare($sql)->execute([$key, $now, $now - 900, $now - 900]);
        $st = $pdo->prepare('SELECT attempts FROM auth_attempts WHERE attempt_key = ?');
        $st->execute([$key]);
        if ((int)$st->fetchColumn() > $limit) {
            http_response_code(429);
            header('Retry-After: 900');
            return false;
        }
    }
    return true;
}
