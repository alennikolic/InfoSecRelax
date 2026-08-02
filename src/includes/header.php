<?php
/**
 * src/includes/header.php - zajednički layout: <head>, bočna navigacija,
 * i otvaranje glavnog sadržaja.
 *
 * Očekuje da su $menu, $currentItem i $requestedSlug već postavljeni
 * u index.php pre uključivanja ovog fajla. $menu je već filtriran po
 * RBAC pravima trenutnog korisnika (ili prazan / jednostavan niz za
 * granu "nije ulogovan" / "super admin" - videti index.php).
 *
 * IZMENA (RBAC): traka sa emailom ulogovanog korisnika i linkom
 * "Odjava" je dodata odmah ispod .sidebar-brand. currentUser() se
 * poziva direktno ovde SAMO radi prikaza (ne radi provere pristupa -
 * ta provera se već desila u index.php pre nego što je ovaj fajl
 * uopšte učitan) - zato je bezbedno da ne postoji provera null-a pre
 * poziva funkcije, currentUser() vraća null uredno ako niko nije
 * ulogovan (npr. na samoj stranici za prijavu).
 *
 * Skripta za skrolovanje aktivne stavke menija (videti komentar uz nju
 * ispod) je namerno UGRAĐENA ovde, odmah posle </nav>, a ne u
 * spoljnom fajlu učitanom na dnu stranice (footer.php) - cilj je da se
 * izvrši što je ranije moguće, dok browser još nije iscrtao stranicu.
 * Da je spoljni fajl na dnu <body>-ja (kao pre), korisnik bi video
 * vidljiv "skok" sadržaja menija tačno ispod miša, pošto bi se
 * ispravka scroll pozicije desila POSLE što je stranica već iscrtana
 * sa scrollTop=0. Ugrađena skripta odmah posle menija stiže pre tog
 * prvog iscrtavanja.
 */

$__authUser = currentUser();
?>
<!DOCTYPE html>
<html lang="sr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($currentItem['title']) ?> — InfoSecRelax</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="app-shell">

        <aside class="sidebar">
            <div class="sidebar-brand">
                <span class="brand-name">InfoSecRelax</span>
                <span class="brand-tag">ISO 27001, korak po korak</span>
            </div>

            <?php if ($__authUser !== null): ?>
            <div class="sidebar-user">
                <span class="sidebar-user-email"><?= htmlspecialchars($__authUser['email']) ?></span>
                <a href="?page=odjava" class="sidebar-user-logout">Odjava</a>
            </div>
            <?php endif; ?>

            <nav class="sidebar-nav">
                <?php
                $lastGroup = null;
                foreach ($menu as $item):
                    if ($item['group'] !== $lastGroup):
                        $lastGroup = $item['group'];
                ?>
                <?php if ($lastGroup !== null): ?>
                <div class="nav-group-title"><?= htmlspecialchars($lastGroup) ?></div>
                <?php endif; ?>
                <?php endif; ?>
                <a href="?page=<?= htmlspecialchars($item['slug']) ?>"
                   class="nav-link<?= $requestedSlug === $item['slug'] ? ' active' : '' ?>">
                    <span class="nav-title"><?= htmlspecialchars($item['title']) ?></span>
                    <?php if (!empty($item['iso_ref'])): ?>
                    <span class="nav-ref"><?= htmlspecialchars($item['iso_ref']) ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </nav>

            <script>
                /**
                 * Videti napomenu u docbloku na vrhu fajla za "zašto ugrađeno
                 * ovde" - suština: NE koristi se scrollIntoView() na aktivnoj
                 * stavci, jer bi taj poziv po potrebi skrolovao i celu
                 * stranicu (window), ne samo .sidebar, na dužim stranicama
                 * gde .sidebar flex-stretch ponašanjem naraste do visine
                 * glavnog sadržaja. Umesto toga, ručno se računa pozicija
                 * aktivne stavke U ODNOSU NA .sidebar i menja se isključivo
                 * sidebar.scrollTop - ta vrednost utiče samo na unutrašnji
                 * scroll te jedne trake, nikad na scroll cele stranice.
                 */
                (function () {
                    var sidebar = document.querySelector('.sidebar');
                    var activeNavLink = document.querySelector('.sidebar-nav .nav-link.active');

                    if (!sidebar || !activeNavLink) {
                        return;
                    }

                    var sidebarRect = sidebar.getBoundingClientRect();
                    var linkRect = activeNavLink.getBoundingClientRect();

                    var linkTopWithinSidebar = (linkRect.top - sidebarRect.top) + sidebar.scrollTop;
                    var linkBottomWithinSidebar = linkTopWithinSidebar + activeNavLink.offsetHeight;

                    if (linkTopWithinSidebar < sidebar.scrollTop) {
                        sidebar.scrollTop = linkTopWithinSidebar;
                    } else if (linkBottomWithinSidebar > sidebar.scrollTop + sidebar.clientHeight) {
                        sidebar.scrollTop = linkBottomWithinSidebar - sidebar.clientHeight;
                    }
                })();
            </script>
        </aside>

        <main class="main-content">
            <header class="content-header">
                <h1><?= htmlspecialchars($currentItem['title']) ?></h1>
                <?php if (!empty($currentItem['iso_ref'])): ?>
                <span class="content-ref"><?= htmlspecialchars($currentItem['iso_ref']) ?></span>
                <?php endif; ?>
            </header>

            <div class="content-body">
