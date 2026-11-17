
<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        DB_HOST,
        DB_PORT,
        DB_NAME,
        DB_CHARSET
    );

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND =>
            "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];

    if (DB_SSL_CA !== '') {
        $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    } catch (PDOException $e) {
        error_log('Database connection error: ' . $e->getMessage());
        http_response_code(500);

        if (APP_DEBUG) {
            die('Database connection failed: ' .
                htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
        }

        die('Database unavailable. Please try again later.');
    }

    return $pdo;
}
