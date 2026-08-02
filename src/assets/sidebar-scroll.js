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
 * NE koristi activeNavLink.scrollIntoView() - taj poziv po potrebi
 * skroluje SVE scrollable pretke, uključujući i samu stranicu
 * (window), ne samo .sidebar. Na dužim stranicama .sidebar se flex
 * "stretch" ponašanjem rasteže do visine glavnog sadržaja (.app-shell
 * je flex red, align-items je podrazumevano stretch), pa aktivna
 * stavka pri dnu menija fizički može biti hiljadu piksela niže u samom
 * dokumentu - scrollIntoView() bi tad skrolovao celu stranicu nadole
 * da je prikaže, gurajući sadržaj van vidokruga (tačno bag koji je ovo
 * zamenilo).
 *
 * Umesto toga, ručno se računa pozicija aktivne stavke U ODNOSU NA
 * .sidebar (preko getBoundingClientRect(), pouzdano bez obzira na
 * offsetParent lanac) i menja se isključivo sidebar.scrollTop - ta
 * vrednost utiče samo na unutrašnji scroll te jedne trake, nikad na
 * scroll cele stranice.
 */

var sidebar = document.querySelector('.sidebar');
var activeNavLink = document.querySelector('.sidebar-nav .nav-link.active');

if (sidebar && activeNavLink) {
    var sidebarRect = sidebar.getBoundingClientRect();
    var linkRect = activeNavLink.getBoundingClientRect();

    var linkTopWithinSidebar = (linkRect.top - sidebarRect.top) + sidebar.scrollTop;
    var linkBottomWithinSidebar = linkTopWithinSidebar + activeNavLink.offsetHeight;

    if (linkTopWithinSidebar < sidebar.scrollTop) {
        sidebar.scrollTop = linkTopWithinSidebar;
    } else if (linkBottomWithinSidebar > sidebar.scrollTop + sidebar.clientHeight) {
        sidebar.scrollTop = linkBottomWithinSidebar - sidebar.clientHeight;
    }
}
