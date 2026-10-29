<?php
class Database
{
    public function getConnection(): PDO
    {
        if (!class_exists('PDO')) {
            throw new RuntimeException('PDO is required to connect to MySQL.');
        }

        $configPath = dirname(__DIR__) . '/config.php';
        $config = is_file($configPath) ? require $configPath : [];
        if (!is_array($config)) {
            throw new RuntimeException('The local database configuration must return an array.');
        }
        $setting = static function (string $key, string $default) use ($config): string {
            $environmentValue = getenv($key);
            $value = $environmentValue !== false ? $environmentValue : ($config[$key] ?? $default);
            if (!is_string($value)) {
                throw new RuntimeException('Database configuration values must be strings.');
            }
            return $value;
        };
        $host = $setting('DB_HOST', 'localhost');
        $db = $setting('DB_NAME', 'aiub_career_connect');
        $user = $setting('DB_USER', 'root');
        $pass = $setting('DB_PASS', '');

        try {
            return new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $error) {
            error_log('AIUB Career Connect could not connect to the configured MySQL database.');
            throw new RuntimeException('The MySQL database is unavailable.', 0, $error);
        }
    }
}
