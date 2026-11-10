<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

if (isset($_SESSION['user']['id']) && in_array($_SESSION['user']['role'] ?? '', ['admin','super_admin'], true)) {
    header('Location: ../index.php?page=admin', true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email=strtolower(trim((string)($_POST['email'] ?? '')));
    $password=(string)($_POST['password'] ?? '');
    if (mb_strlen($email)>190 || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
        flash('error','E-mail ou mot de passe incorrect.');
        header('Location: login.php',true,303);
        exit;
    }
    $ipHash=hash('sha256',(string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
    $attemptsQuery=db()->prepare('SELECT echecs FROM tentatives_connexion WHERE email=:email AND empreinte_ip=:ip AND derniere_tentative > (NOW() - INTERVAL 15 MINUTE)');
    $attemptsQuery->execute(['email'=>$email,'ip'=>$ipHash]);
    if ((int)($attemptsQuery->fetchColumn() ?: 0)>=5) {
        flash('error','Trop de tentatives. Réessayez dans quelques minutes.');
        header('Location: login.php',true,303);
        exit;
    }
    $query=db()->prepare('SELECT id,nom,email,mot_de_passe,role FROM utilisateurs WHERE email=:email LIMIT 1');
    $query->execute(['email'=>$email]);
    $user=$query->fetch();
    if ($user && in_array($user['role'],['admin','super_admin'],true) && password_verify($password,$user['mot_de_passe'])) {
        session_regenerate_id(true);
        unset($user['mot_de_passe']);
        $_SESSION['user']=$user;
        $_SESSION['admin_logged']=true;
        $_SESSION['admin_id']=(int)$user['id'];
        db()->prepare('DELETE FROM tentatives_connexion WHERE email=:email AND empreinte_ip=:ip')->execute(['email'=>$email,'ip'=>$ipHash]);
        header('Location: dashboard.php',true,303);
        exit;
    }
    db()->prepare('INSERT INTO tentatives_connexion (email,empreinte_ip,echecs) VALUES (:email,:ip,1) ON DUPLICATE KEY UPDATE echecs=IF(derniere_tentative>(NOW()-INTERVAL 15 MINUTE),echecs+1,1),derniere_tentative=NOW()')->execute(['email'=>$email,'ip'=>$ipHash]);
    flash('error','E-mail ou mot de passe incorrect.');
    header('Location: login.php',true,303);
    exit;
}

$flash=get_flash();
?>
<!doctype html>
<html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Connexion administration | SG Services</title><link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"><link rel="stylesheet" href="../assets/site.css"><link rel="stylesheet" href="../assets/glass.css"><style>.admin-login-page{min-height:100vh;display:grid;place-items:center;padding:24px;background:#f8fafc}.admin-login-card{width:min(100%,430px);padding:30px;background:rgba(255,255,255,.88);border:1px solid #ffffff;box-shadow:0 20px 60px #102b3518;backdrop-filter:blur(16px);border-radius:16px}.admin-login-card h1{font:700 26px var(--display);margin:10px 0}.admin-login-card form{display:grid;gap:14px;margin-top:22px}.admin-login-card label{display:grid;gap:6px;font-size:12px;font-weight:600}.admin-login-card input{min-height:44px;border:1px solid #d5ddda;padding:10px;font:12px var(--body)}.admin-login-card .brand-mark{margin-bottom:20px}.admin-login-back{display:inline-block;margin-top:18px;font-size:11px;color:#60716d}</style></head>
<body><main class="admin-login-page"><section class="admin-login-card"><span class="brand-mark">SG</span><span class="eyebrow">ESPACE SÉCURISÉ</span><h1>Connexion administration</h1><p class="muted-copy">Accès réservé à l’équipe habilitée de SG Services International.</p><?php if ($flash): ?><div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?><form method="post" action="login.php"><?= csrf_field() ?><label>Adresse e-mail<input type="email" name="email" maxlength="190" autocomplete="username" required></label><label>Mot de passe<input type="password" name="password" autocomplete="current-password" required></label><button class="button button-green full-width">Se connecter <span>↗</span></button></form><a class="admin-login-back" href="../index.php">← Retour au site</a></section></main></body></html>