<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
if (empty($_SESSION['admin_logged']) || empty($_SESSION['admin_id']) || (int)($_SESSION['user']['id'] ?? 0)!==(int)$_SESSION['admin_id'] || !in_array($_SESSION['user']['role'] ?? '',['admin','super_admin'],true)) {
	header('Location: login.php',true,303);
	exit;
}