<?php
require_once 'core/core.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();

session_unset();
session_destroy();
redirect('index.php');
?>
