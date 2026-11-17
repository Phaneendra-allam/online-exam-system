
<?php
declare(strict_types=1);

// Database settings from Render environment variables
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'online_exam_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');
define('DB_SSL_CA', getenv('DB_SSL_CA') ?: '');

// Application settings
define('APP_NAME', 'Online Exam System');
define('APP_TAGLINE', 'A modern, secure platform for college quizzes & exams');
define('APP_TIMEZONE', 'Asia/Kolkata');
define('APP_DEBUG', filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('SESSION_NAME', 'oes_session');
define('SESSION_LIFETIME', 60 * 60 * 4);

// Security
define('PASSWORD_ALGO', PASSWORD_BCRYPT);
define('CSRF_TOKEN_KEY', 'csrf_token');

date_default_timezone_set(APP_TIMEZONE);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
}
