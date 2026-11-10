<?php
declare(strict_types=1);

require __DIR__ . '/check-auth.php';
header('Location: ../index.php?page=admin',true,303);
exit;