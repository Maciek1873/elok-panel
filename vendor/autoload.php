<?php
// Autoloader bibliotek do PDF (dompdf 2.0.8 + zależności) – bez Composera.
if (!defined('ELOK')) { http_response_code(403); exit; }
spl_autoload_register(function ($cls) {
    static $map = null;
    if ($map === null) {
        $b = __DIR__;
        $map = array(
            'Dompdf\\' => $b . '/dompdf/src/',
            'FontLib\\' => $b . '/php-font-lib/src/FontLib/',
            'Svg\\' => $b . '/php-svg-lib/src/Svg/',
            'Masterminds\\' => $b . '/html5/src/',
            'Sabberworm\\CSS\\' => $b . '/css-parser/src/',
        );
    }
    if ($cls === 'Dompdf\\Cpdf') { require_once __DIR__ . '/dompdf/lib/Cpdf.php'; return; }
    foreach ($map as $pre => $dir) {
        if (strncmp($cls, $pre, strlen($pre)) === 0) {
            $f = $dir . str_replace('\\', '/', substr($cls, strlen($pre))) . '.php';
            if (is_file($f)) require_once $f;
            return;
        }
    }
});
