<?php
// Małe funkcje używane na każdej stronie.

// Escapowanie danych przed wypisaniem w HTML (ochrona przed XSS).
function e(?string $tekst): string
{
    return htmlspecialchars((string) $tekst, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

// Pobiera wartość z formularza jako tekst (tablice i brak pola dają ''), domyślnie obcina spacje.
function post(string $klucz, bool $obetnij = true): string
{
    $v = $_POST[$klucz] ?? '';
    if (!is_string($v)) {
        return '';
    }
    return $obetnij ? trim($v) : $v;
}

function metoda_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

// Komunikaty jednorazowe (wyświetlane po przekierowaniu).
function flash(string $typ, string $tekst): void
{
    $_SESSION['flash'][] = ['typ' => $typ, 'tekst' => $tekst];
}

function pobierz_flash(): array
{
    $lista = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $lista;
}

// Token CSRF: ukryte pole w każdym formularzu POST + sprawdzenie po stronie serwera.
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_pole(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_sprawdz(): bool
{
    $podany = $_POST['csrf'] ?? '';
    return is_string($podany)
        && isset($_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], $podany);   // porównanie odporne na ataki czasowe
}
