<?php $flash = get_flash(); $activeLogo = site_setting('logo_path'); ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#102b35">
  <title><?= e($title) ?> | SG Services International</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
  <link rel="stylesheet" href="assets/site.css">
  <link rel="stylesheet" href="assets/glass.css">
  <?php if ($page === 'home'): ?><link rel="stylesheet" href="assets/home.css"><?php endif; ?>
  <?php if ($page === 'alertes' || $page === 'admin'): ?><link rel="stylesheet" href="assets/notifications.css"><?php endif; ?>
  <?php if ($page === 'terrains'): ?><link rel="stylesheet" href="assets/catalog-search.css"><?php endif; ?>
  <?php if ($page === 'admin'): ?><link rel="stylesheet" href="assets/admin.css"><link rel="stylesheet" href="assets/review-admin.css"><link rel="stylesheet" href="assets/admin-home.css"><link rel="stylesheet" href="assets/admin-home-forms.css"><?php endif; ?>
  <?php if ($page === 'terrain' || ($page === 'admin' && (isset($_GET['edit']) || isset($_GET['new'])))): ?><link rel="stylesheet" href="assets/terrain-detail.css"><?php endif; ?>
</head>
<body>
<header class="topbar">
  <a class="brand" href="?page=home" aria-label="SG Services, accueil"><span class="brand-mark"><?php if ($activeLogo): ?><img src="?page=logo&amp;v=<?= e(substr($activeLogo,0,8)) ?>" alt="Logo SG Services"><?php else: ?>SG<?php endif; ?></span><span>SG SERVICES<small>INTERNATIONAL SARL</small></span></a>
  <button class="menu-toggle" aria-label="Ouvrir le menu" aria-expanded="false">☰</button>
  <nav class="main-nav"><a href="?page=terrains">Nos terrains</a><a href="?page=services">Services</a><a href="?page=alertes">Alertes</a><a href="?page=avis">Avis</a><a href="?page=about">L'entreprise</a><a href="?page=vendre">Vendre un bien</a><a class="nav-contact" href="?page=contact">Nous contacter <span aria-hidden="true">↗</span></a></nav>
</header>
<?php if ($flash): ?><div class="flash flash-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<main>