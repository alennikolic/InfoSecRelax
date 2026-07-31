<?php
/**
 * index.php - jedina ulazna tačka aplikacije.
 *
 * Ruta se određuje preko ?page=slug. Ako za tu stavku menija postoji
 * fajl u modules/, on se učitava; ako ne postoji, prikazuje se
 * placeholder ekran. Ovo omogućava da se svaki modul razvija nezavisno
 * i postepeno, bez ikakve izmene ovog fajla ili menija.
 */

declare(strict_types=1);

$menu = require __DIR__ . '/config/menu.php';

// Podrazumevana stranica je prva u meniju - trenutno "kontekst",
// pošto tu i počinje uvođenje standarda.
$requestedSlug = isset($_GET['page'])
    ? preg_replace('/[^a-z0-9\-]/', '', (string) $_GET['page'])
    : $menu[0]['slug'];

// Pronađi stavku menija koja odgovara traženom slug-u.
$currentItem = null;
foreach ($menu as $item) {
    if ($item['slug'] === $requestedSlug) {
        $currentItem = $item;
        break;
    }
}

// Ako slug ne postoji u meniju (npr. neko ručno izmeni URL), vrati na prvu stavku.
if ($currentItem === null) {
    $currentItem = $menu[0];
    $requestedSlug = $menu[0]['slug'];
}

require __DIR__ . '/includes/header.php';

$modulePath = __DIR__ . '/modules/' . $requestedSlug . '.php';

if (file_exists($modulePath)) {
    require $modulePath;
} else {
    require __DIR__ . '/includes/placeholder.php';
}

require __DIR__ . '/includes/footer.php';
