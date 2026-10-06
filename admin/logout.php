<?php
declare(strict_types=1);
require __DIR__ . '/_layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (current_admin()) log_activity('logout');
    $_SESSION = [];
    session_regenerate_id(true);
}
redirect('login.php');
