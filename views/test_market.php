<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "1<br>";
require_once __DIR__ . '/../core/core.php';
echo "2 core ok<br>";

require_once __DIR__ . '/../controllers/auction_controller.php';
echo "3 controller ok<br>";

echo "user: ";
var_dump(function_exists('current_user_id') ? current_user_id() : 'MISSING');
echo "<br>url: ";
var_dump(function_exists('url'));
echo "<br>get_categories: ";
var_dump(function_exists('get_categories_ctr'));