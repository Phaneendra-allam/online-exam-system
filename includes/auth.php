<?php
/**
 * Session, CSRF and authentication helpers.
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';

/**
 * Start a secure session and initialize CSRF token.
 */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    );

    session_name(SESSION_NAME);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $isHttps,
    ]);

    session_start();

    if (!isset($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
}

/**
 * Generate CSRF token.
 */
function csrf_token(): string
{
    start_session();

    return $_SESSION[CSRF_TOKEN_KEY];
}

/**
 * Generate hidden CSRF input field.
 */
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' .
        htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') .
        '">';
}

/**
 * Verify submitted CSRF token.
 */
function verify_csrf(): void
{
    start_session();

    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION[CSRF_TOKEN_KEY] ?? '', $token)
    ) {
        http_response_code(419);
        die('Invalid or expired CSRF token. Please go back and try again.');
    }
}

// ---------- User authentication ----------

function current_user(): ?array
{
    start_session();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    try {
        $stmt = db()->prepare(
            'SELECT id, name, email, gender, college, mobile, class_level, role, created_at
FROM users WHERE id = ? LIMIT 1'
        );

        $stmt->execute([$_SESSION['user_id']]);

        $user = $stmt->fetch();

        return $user ?: null;

    } catch (Throwable $e) {
        return null;
    }
}

function require_login(string $redirect = 'login.php'): array
{
    $user = current_user();

    if (!$user) {
        header('Location: ' . base_url($redirect));
        exit;
    }

    return $user;
}

function require_admin(string $redirect = 'admin/login.php'): array
{
    $user = require_login($redirect);

    if (($user['role'] ?? '') !== 'admin') {
        header('Location: ' . base_url($redirect));
        exit;
    }

    return $user;
}

function login_user(int $userId): void
{
    start_session();

    session_regenerate_id(true);

    $_SESSION['user_id'] = $userId;
    $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
}

function logout_user(): void
{
    start_session();

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

// ---------- Helper functions ----------

function base_url(string $path = ''): string
{
    $scheme = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
    ) ? 'https' : 'http';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $script = $_SERVER['SCRIPT_NAME'] ?? '';

    $base = rtrim(
        str_replace('\\', '/', dirname($script)),
        '/'
    );

    if (str_ends_with($base, '/admin')) {
        $base = substr($base, 0, -6);
    }

    return $scheme . '://' . $host . $base . '/' . ltrim($path, '/');
}

function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $value = null): ?string
{
    start_session();

    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    $value = $_SESSION['_flash'][$key] ?? null;

    if ($value !== null) {
        unset($_SESSION['_flash'][$key]);
    }

    return $value;
}

function redirect(string $path): void
{
    header('Location: ' . base_url($path));
    exit;
}
