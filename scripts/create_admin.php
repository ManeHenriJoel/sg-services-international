<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../config/database.php';
$email = strtolower((string)($argv[1] ?? 'admin@sgservices.ci'));
$name = trim((string)($argv[2] ?? 'SG Services Admin'));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $name === '' || strlen($name) > 120) {
    fwrite(STDERR, "Usage: php scripts/create_admin.php [email@example.com] [\"Nom complet\"]\n");
    exit(1);
}
fwrite(STDOUT, 'Mot de passe initial (12 caractères minimum) : ');
$password = trim((string) fgets(STDIN));
if (strlen($password) < 12) {
    fwrite(STDERR, "Mot de passe trop court.\n");
    exit(1);
}
$stmt = db()->prepare('INSERT INTO utilisateurs (nom, email, mot_de_passe, role) VALUES (:nom, :email, :password, \'super_admin\')');
$stmt->execute(['nom'=>$name, 'email'=>$email, 'password'=>password_hash($password, PASSWORD_DEFAULT)]);
fwrite(STDOUT, "Compte super administrateur créé.\n");