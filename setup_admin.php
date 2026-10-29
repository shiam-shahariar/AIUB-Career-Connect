<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/Model/Database.php';

$name = getenv('INITIAL_ADMIN_NAME') ?: '';
$email = getenv('INITIAL_ADMIN_EMAIL') ?: '';
$password = getenv('INITIAL_ADMIN_PASSWORD') ?: '';
$length = preg_match_all('/./us', $name);

if ($name === '' || $length === false || $length > 150
    || filter_var($email, FILTER_VALIDATE_EMAIL) === false
    || strlen($password) < 12 || strlen($password) > 72) {
    fwrite(STDERR, "Set INITIAL_ADMIN_NAME, INITIAL_ADMIN_EMAIL, and INITIAL_ADMIN_PASSWORD (12-72 characters) in the environment.\n");
    exit(1);
}

try {
    $pdo = (new Database())->getConnection();
    $adminExists = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE role = "admin"')->fetchColumn() > 0;
    if ($adminExists) {
        fwrite(STDERR, "An administrator account already exists. Initial setup is locked.\n");
        exit(1);
    }
    $statement = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, status, department_id) '
        . 'VALUES (:name, :email, :password_hash, "admin", "active", NULL)'
    );
    $statement->execute([
        ':name' => $name,
        ':email' => $email,
        ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    fwrite(STDOUT, "Administrator account created. Remove the setup environment variables.\n");
} catch (Throwable $error) {
    error_log('AIUB Career Connect initial administrator setup failed.');
    fwrite(STDERR, "Administrator setup failed. Check the database connection and application logs.\n");
    exit(1);
}
