<?php
// Uwierzytelnianie, sesje, role i kontrola dostępu.

const MAX_PROB   = 5;     // tyle błędnych haseł blokuje konto
const BLOKADA_MIN = 15;   // na tyle minut
const SESJA_CZAS = 1800;  // wylogowanie po 30 minutach bezczynności

// Atrapa hasha: porównujemy z nią, gdy e-maila nie ma w bazie,
// żeby czas odpowiedzi nie zdradzał, czy konto istnieje.
const DUMMY_HASH = '$2y$10$gpKoVlT3.th9OqILInd6KuewTmI/2jIBj52IEWqE55s6ITjiZ/tGm';

function panel_roli(string $rola): string
{
    return match ($rola) {
        'administrator' => 'panel-admina.php',
        'pracownik'     => 'panel-pracownika.php',
        default         => 'panel-klienta.php',
    };
}

// Czyści dane sesji i nadaje nowy identyfikator (stary przestaje działać).
function wyczysc_sesje(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

// Zwraca zalogowanego użytkownika z bazy albo null.
// Przy każdym żądaniu sprawdzamy w bazie, czy konto nadal istnieje i jest aktywne,
// a rola pochodzi z bazy, nie z danych, które można podrobić.
function aktualny_uzytkownik(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    $u = null;
    if (!empty($_SESSION['uid'])) {
        $st = db()->prepare(
            'SELECT id, imie, nazwisko, email, numer_telefonu, rola
             FROM uzytkownicy WHERE id = ? AND aktywne = 1'
        );
        $st->execute([$_SESSION['uid']]);
        $u = $st->fetch() ?: null;
        if ($u === null) {
            wyczysc_sesje();
            flash('err', 'Twoje konto jest nieaktywne lub nie istnieje.');
        } else {
            $_SESSION['rola']     = $u['rola'];
            $_SESSION['imie']     = $u['imie'];
            $_SESSION['nazwisko'] = $u['nazwisko'];
        }
    }
    return $u;
}

function wymagaj_logowania(): array
{
    $u = aktualny_uzytkownik();
    if ($u === null) {
        flash('err', 'Zaloguj się, aby zobaczyć tę stronę.');
        redirect('logowanie.php');
    }
    return $u;
}

// Wpuszcza tylko podane role. Reszta dostaje 403, także przy ręcznym wpisaniu adresu URL.
function wymagaj_roli(string ...$role): array
{
    $u = wymagaj_logowania();
    if (!in_array($u['rola'], $role, true)) {
        http_response_code(403);
        render_naglowek('Brak dostępu – RentLoudCars');
        echo '<main id="main" class="block"><div class="wrap auth-card">'
           . '<h1>Brak dostępu</h1><p>Nie masz uprawnień do tej strony.</p>'
           . '<p><a class="btn" href="' . e(panel_roli($u['rola'])) . '">Wróć do mojego konta</a></p>'
           . '</div></main>';
        render_stopka();
        exit;
    }
    return $u;
}

// Logowanie. Zwraca ['ok' => true, 'rola' => ...] albo ['blad' => 'komunikat'].
function zaloguj(string $email, string $haslo): array
{
    $blad = 'Nieprawidłowy e-mail lub hasło.';   // jeden komunikat, żeby nie zdradzać, czy e-mail istnieje

    $st = db()->prepare(
        'SELECT id, imie, nazwisko, haslo, rola, aktywne, nieudane_proby,
                (zablokowany_do IS NOT NULL AND zablokowany_do > NOW()) AS zablokowane,
                TIMESTAMPDIFF(SECOND, NOW(), zablokowany_do) AS sekundy
         FROM uzytkownicy WHERE email = ?'
    );
    $st->execute([$email]);
    $u = $st->fetch();

    if (!$u) {
        password_verify($haslo, DUMMY_HASH);
        return ['blad' => $blad];
    }

    if ($u['zablokowane']) {
        $min = max(1, (int) ceil($u['sekundy'] / 60));
        return ['blad' => "Konto jest tymczasowo zablokowane po zbyt wielu błędnych próbach. Spróbuj za około {$min} min."];
    }

    if (!password_verify($haslo, $u['haslo'])) {
        if ($u['nieudane_proby'] + 1 >= MAX_PROB) {
            $sql = 'UPDATE uzytkownicy SET nieudane_proby = 0,
                    zablokowany_do = DATE_ADD(NOW(), INTERVAL ' . BLOKADA_MIN . ' MINUTE) WHERE id = ?';
            db()->prepare($sql)->execute([$u['id']]);
        } else {
            db()->prepare('UPDATE uzytkownicy SET nieudane_proby = nieudane_proby + 1 WHERE id = ?')
                ->execute([$u['id']]);
        }
        return ['blad' => $blad];
    }

    if (!$u['aktywne']) {
        return ['blad' => 'To konto jest nieaktywne. Skontaktuj się z obsługą.'];
    }

    // Hasło poprawne: zerujemy licznik prób i ewentualnie odświeżamy hash do aktualnego algorytmu.
    db()->prepare('UPDATE uzytkownicy SET nieudane_proby = 0, zablokowany_do = NULL WHERE id = ?')
        ->execute([$u['id']]);
    if (password_needs_rehash($u['haslo'], PASSWORD_DEFAULT)) {
        db()->prepare('UPDATE uzytkownicy SET haslo = ? WHERE id = ?')
            ->execute([password_hash($haslo, PASSWORD_DEFAULT), $u['id']]);
    }

    session_regenerate_id(true);   // nowy identyfikator po zalogowaniu chroni przed session fixation
    $_SESSION = [
        'uid'      => (int) $u['id'],
        'rola'     => $u['rola'],
        'imie'     => $u['imie'],
        'nazwisko' => $u['nazwisko'],
        'ostatnia' => time(),
    ];
    return ['ok' => true, 'rola' => $u['rola']];
}
