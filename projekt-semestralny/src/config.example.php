<?php
// Skopiuj ten plik jako src/config.php i wpisz własne dane.
// src/config.php jest w .gitignore, więc hasła nie trafiają do repozytorium.
return [
    'db_host'  => 'localhost',
    'db_name'  => 'Wypozyczalnia',
    'db_user'  => 'root',
    'db_pass'  => '',        // XAMPP: puste hasło
    'debug'    => false,     // true tylko lokalnie: pokazuje błędy PHP
];
