/**
 * src/assets/sidebar-scroll.js
 *
 * Svaki klik na stavku menija je pun page reload (ova aplikacija nema
 * SPA navigaciju), pa se bočna traka (.sidebar-nav) svaki put učita
 * ponovo skrolovana na vrh - ako je kliknuta stavka bila pri dnu
 * dugačkog menija, posle prelaska na novu stranicu ostaje van
 * vidokruga i mora se ručno skrolovati da bi se ponovo videla kao
 * aktivna.
 *
 * Fajl se učitava kao poslednji <script> pre </body> (footer.php), pa
 * je .sidebar-nav sa server-rendered .active klasom (header.php) već
 * u DOM-u u trenutku izvršavanja - nije potreban DOMContentLoaded
 * listener, kod se izvršava direktno.
 *
 * block: 'nearest' skroluje samo ako stavka već nije u potpunosti
 * vidljiva, minimalnom potrebnom količinom - bez toga bi se svaka
 * stranica (i kad je aktivna stavka već na vrhu, vidljiva) nepotrebno
 * pomerala.
 */

var activeNavLink = document.querySelector('.sidebar-nav .nav-link.active');

if (activeNavLink) {
    activeNavLink.scrollIntoView({ block: 'nearest' });
}
