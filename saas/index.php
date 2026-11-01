<?php
// Stary panel SaaS (warsztaty) – dostępny tylko po zalogowaniu do panelu ELOK
define('ELOK', 1);
require __DIR__ . '/../lib.php';
start_session();
if (!logged_in()) { header('Location: ../'); exit; }
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
readfile(__DIR__ . '/app.html');
