<?php
/**
 * src/includes/csrf.php
 *
 * CSRF zaštita zasnovana na tokenu vezanom za sesiju (double-submit
 * nije potreban jer aplikacija već koristi httponly, samesite=Strict
 * kolačić - videti config/auth.php).
 *
 * Upotreba u svakom modulu koji ima formu:
 *
 *   require_once __DIR__ . '/../includes/csrf.php';
 *   ...
 *   if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_x') {
 *       csrfRequireValid();   // odmah na početku POST obrade, pre bilo kog upisa
 *       ...
 *   }
 *
 * i u samoj formi, odmah posle <form ...>:
 *
 *   <?= csrfField() ?>
 *
 * Učitava se isključivo preko require_once (i sam interno učitava
 * config/auth.php preko require_once) - videti napomenu u
 * config/database.php zašto se plain require ne sme koristiti za ove
 * deljene fajlove.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/auth.php';

function csrfToken(): string
{
    startAppSession();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrfField(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

function csrfVerify(): bool
{
    startAppSession();

    $submitted = $_POST['csrf_token'] ?? '';
    $expected  = $_SESSION['csrf_token'] ?? '';

    return $submitted !== '' && $expected !== '' && hash_equals($expected, $submitted);
}

/**
 * Zaustavlja izvršavanje uz poruku ako token ne odgovara. Namerno
 * echo + exit (ne exception) - isti stil obrade grešaka kao ostatak
 * aplikacije, koja ne koristi framework/exception hijerarhiju.
 *
 * NAPOMENA: poziva se isključivo iz modula, uvek posle što je
 * index.php već učitao header.php - zato zatvara layout preko
 * footer.php pre exit-a, umesto da ostavi nezatvoren HTML (ista
 * napomena kao uz requirePagePermission/requireSuperAdmin u
 * config/auth.php).
 */
function csrfRequireValid(): void
{
    if (!csrfVerify()) {
        http_response_code(403);
        echo '<div class="alert alert-error"><p>Sesija je istekla ili je zahtev nevažeći (CSRF zaštita). '
            . 'Osveži stranicu i pokušaj ponovo.</p></div>';
        require __DIR__ . '/footer.php';
        exit;
    }
}
