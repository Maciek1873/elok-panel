<?php
// ============================================================
//  ELOK PANEL – KONFIGURACJA
//  Uzupełnij 4 linijki bazy danych (LH.pl → Bazy danych MySQL)
// ============================================================
if (!defined('ELOK')) { http_response_code(403); exit; }

define('DB_HOST', 'localhost');
define('DB_NAME', 'WPISZ_NAZWE_BAZY');
define('DB_USER', 'WPISZ_UZYTKOWNIKA');
define('DB_PASS', 'WPISZ_HASLO');

// Adres panelu (bez "/" na końcu)
define('BASE_URL', 'https://panel.elok-agency.pl');

// Tajny klucz do crona – nie udostępniaj
define('CRON_KEY', 'WPISZ_LOSOWY_KLUCZ');
