<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/autoload.php';
require_once __DIR__ . '/../src/Helpers/functions.php';
require_once __DIR__ . '/helpers/TestDatabase.php';

error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SESSION = [];
$_SERVER['SERVER_PORT'] = 80;
$_SERVER['HTTPS'] = 'off';

TestDatabase::getConnection();
TestDatabase::migrate();
TestDatabase::seed();
