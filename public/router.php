<?php
/**
 * Router untuk PHP built-in server (development saja).
 *
 * Jalankan dari root project:
 *     php -S localhost:8000 -t public public/router.php
 *
 * Melayani file statis (CSS, JS, gambar) apa adanya, selain itu
 * meneruskan request ke front controller (index.php).
 *
 * Di production gunakan Apache/Nginx dengan document root ke folder
 * public/ — .htaccess sudah menangani rewrite ke index.php.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . $path;

// Serve aset statis yang benar-benar ada sebagai file
if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
