<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';

header('Location: ' . (Auth::currentUser() ? '/lmln/dashboard.php' : '/lmln/login.php'));
exit;
