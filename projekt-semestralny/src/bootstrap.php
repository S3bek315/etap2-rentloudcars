<?php
// Wspólny start każdej strony: konfiguracja, obsługa błędów, sesja i funkcje pomocnicze.

$plikConfig = __DIR__ . '/config.php';
if (!is_file($plikConfig)) {
    http_response_code(500);
    exit('Brak pliku src/config.php. Skopiuj src/config.example.php jako src/config.php i uzupełnij dane bazy.');
}
$GLOBALS['config'] = require $plikConfig;

error_reporting(E_ALL);
ini_set('display_errors', !empty($GLOBALS['config']['debug']) ? '1' : '0');

require __DIR__ . '/pomocnicze.php';
require __DIR__ . '/db.php';
require __DIR__ . '/walidacja.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/layout.php';

// Użytkownik nie widzi szczegółów technicznych, błąd trafia tylko do logu serwera.
set_exception_handler(function (Throwable $ex) {
    error_log((string) $ex);
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8"><title>Błąd</title></head>'
       . '<body style="font-family:sans-serif;padding:40px"><h1>Coś poszło nie tak</h1>'
       . '<p>Spróbuj ponownie za chwilę.</p><p><a href="Strona-glowna.php">Wróć na stronę główną</a></p>';
    if (!empty($GLOBALS['config']['debug'])) {
        echo '<pre>' . e((string) $ex) . '</pre>';
    }
    echo '</body></html>';
});

header('Cache-Control: no-store, max-age=0');   // po wylogowaniu przycisk „Wstecz” nie pokaże panelu
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Bezpieczna sesja: ciasteczko niedostępne dla JavaScriptu, tylko przez własną domenę.
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'secure'   => $https,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_start();

// Wygaśnięcie sesji po bezczynności.
if (isset($_SESSION['uid'])) {
    if (time() - ($_SESSION['ostatnia'] ?? 0) > SESJA_CZAS) {
        wyczysc_sesje();
        flash('err', 'Sesja wygasła z powodu bezczynności. Zaloguj się ponownie.');
    } else {
        $_SESSION['ostatnia'] = time();
    }
}
