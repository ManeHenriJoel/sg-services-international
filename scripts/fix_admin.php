<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/../config/database.php';

$email=strtolower((string)($argv[1] ?? 'admin@sgservices.ci'));
$name=trim((string)($argv[2] ?? 'SG Services Admin'));
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || $name==='' || strlen($name)>120) {
    fwrite(STDERR,"Usage: php scripts/fix_admin.php [email] [\"Nom administrateur\"]\n");
    exit(1);
}

$password='Admin2026!';
$hash=password_hash($password,PASSWORD_BCRYPT);
$existing=db()->prepare('SELECT id FROM utilisateurs WHERE email=:email LIMIT 1');
$existing->execute(['email'=>$email]);
if ($existing->fetchColumn()) {
    fwrite(STDERR,"Ce compte existe déjà ; aucun changement de mot de passe n’a été effectué.\n");
    exit(2);
}
$stmt=db()->prepare('INSERT INTO utilisateurs (nom,email,mot_de_passe,role) VALUES (:nom,:email,:hash,\'super_admin\')');
$stmt->execute(['nom'=>$name,'email'=>$email,'hash'=>$hash]);
fwrite(STDOUT,"Compte super administrateur initialisé pour ".$email.". Le mot de passe de bootstrap est connu : remplacez-le immédiatement.\n");