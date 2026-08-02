<?php
/**
 * index.php - jedina ulazna tačka aplikacije.
 *
 * Ruta se određuje preko ?page=slug. Tri grane, u ovom redosledu:
 *
 *   1. Niko nije ulogovan       -> jedino "prijava" je dostupna.
 *   2. Ulogovan super admin     -> "organizacije" (kreiranje novih
 *                                  firmi) i "pomoc-uredjivanje"
 *                                  (help_content NIJE multi-tenant -
 *                                  deljen je kroz celu aplikaciju, pa
 *                                  namerno nije deo RBAC-a nijedne
 *                                  organizacije, videti config/menu.php).
 *                                  Super admin nema organization_id/
 *                                  role_id, pa ne prolazi kroz
 *                                  role_page_permissions.
 *   3. Ulogovan organizacioni
 *      korisnik                 -> standardni ISO meni iz config/menu.php,
 *                                  filtriran na stranice gde njegova rola
 *                                  ima bar 'citanje', uz RBAC proveru pre
 *                                  učitavanja svakog modula.
 *
 * "odjava" radi identično u sve tri grane (dostupna čim postoji sesija),
 * pa se proverava pre grananja.
 *
 * Ako za tražen slug postoji fajl u modules/, on se učitava; ako ne
 * postoji, prikazuje se placeholder ekran - nepromenjeno u odnosu na
 * pre RBAC izmene.
 */

declare(strict_types=1);

// Output buffering omogućava modulima da pozovu header('Location: ...')
// za redirekciju (npr. posle čuvanja forme) čak i pošto je header.php
// već ispisao deo HTML-a - ništa se stvarno ne šalje browseru dok se
// bafer ne isprazni na kraju skripte.
ob_start();

require_once __DIR__ . '/config/auth.php';

startAppSession();

$requestedSlug = isset($_GET['page'])
    ? preg_replace('/[^a-z0-9\-]/', '', (string) $_GET['page'])
    : null;

// --- Odjava: dostupna svakom ko ima sesiju, bez obzira na granu ---
if ($requestedSlug === 'odjava') {
    logoutUser();
    header('Location: ?page=prijava');
    exit;
}

$user = currentUser();

// =====================================================================
// GRANA 1: niko nije ulogovan
// =====================================================================
if ($user === null) {
    $menu          = [];
    $requestedSlug = 'prijava';
    $currentItem   = ['slug' => 'prijava', 'title' => 'Prijava', 'iso_ref' => null, 'group' => null];

    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/modules/prijava.php';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// =====================================================================
// GRANA 2: super admin (nema organizaciju - upravlja organizacijama i
// jedinim GLOBALNIM, ne-multi-tenant sadržajem aplikacije - help_content)
// =====================================================================
if ((bool) $user['is_super_admin']) {
    $superAdminMenu = [
        ['slug' => 'organizacije',       'title' => 'Organizacije',        'iso_ref' => null, 'group' => null],
        ['slug' => 'pomoc-uredjivanje',  'title' => 'Uređivanje pomoći',   'iso_ref' => null, 'group' => null],
    ];

    $currentItem = null;
    foreach ($superAdminMenu as $item) {
        if ($item['slug'] === $requestedSlug) {
            $currentItem = $item;
            break;
        }
    }
    if ($currentItem === null) {
        $currentItem   = $superAdminMenu[0];
        $requestedSlug = $superAdminMenu[0]['slug'];
    }

    $menu = $superAdminMenu;

    require __DIR__ . '/includes/header.php';
    require __DIR__ . '/modules/' . $requestedSlug . '.php';
    require __DIR__ . '/includes/footer.php';
    exit;
}

// =====================================================================
// GRANA 3: organizacioni korisnik - standardni ISO meni + RBAC
// =====================================================================
$fullMenu = require __DIR__ . '/config/menu.php';

if ($requestedSlug === null) {
    $requestedSlug = $fullMenu[0]['slug'];
}

$currentItem = null;
foreach ($fullMenu as $item) {
    if ($item['slug'] === $requestedSlug) {
        $currentItem = $item;
        break;
    }
}
if ($currentItem === null) {
    $currentItem   = $fullMenu[0];
    $requestedSlug = $fullMenu[0]['slug'];
}

$pdo = getDbConnection();

// Bočni meni prikazuje samo stranice gde rola ima bar 'citanje' -
// stavke na 'zabranjeno' se ne prikazuju uopšte (videti dogovor iz
// razgovora - default-hide u navigaciji). Ovo ne zahteva da je
// header.php već učitan, pa može da stoji pre njega.
$menu = array_values(array_filter($fullMenu, static function (array $item) use ($pdo, $user): bool {
    $roleId = $user['role_id'] !== null ? (int) $user['role_id'] : null;
    return permissionAtLeast(permissionLevelFor($pdo, $roleId, $item['slug']), PERMISSION_READ);
}));

// header.php MORA biti učitan PRE requirePagePermission() - ta funkcija,
// kad odbije pristup, sama zatvara layout preko footer.php (videti
// config/auth.php) da HTML ne ostane nezatvoren. Da je poredak obrnut,
// odbijenica bi se ispisala kao goli fragment bez <head>/navigacije/CSS.
require __DIR__ . '/includes/header.php';

// RBAC provera za TRENUTNU stranicu - zaustavlja i prikazuje
// "Nemate pristup" (uz zatvoren layout) ako rola nema ni 'citanje'
// (npr. neko ukuca URL stranice koju mu meni ne prikazuje).
$currentPermission = requirePagePermission($pdo, $user, $requestedSlug, PERMISSION_READ);

// Centralna, blanket zaštita za upis: SVAKI POST zahtev na stranici gde
// rola ima samo 'citanje' (ne 'puno') se zaustavlja OVDE, pre nego što
// ijedan modul i takne bazu - moduli sami ne moraju svaki posebno da
// proveravaju nivo prava za upis, samo za sakrivanje dugmadi u prikazu
// (videti $currentPermission dostupnu svakom modulu).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !permissionAtLeast($currentPermission, PERMISSION_FULL)) {
    http_response_code(403);
    require __DIR__ . '/includes/pristup-odbijen.php';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$modulePath = __DIR__ . '/modules/' . $requestedSlug . '.php';

if (file_exists($modulePath)) {
    require $modulePath;
} else {
    require __DIR__ . '/includes/placeholder.php';
}

require __DIR__ . '/includes/footer.php';
