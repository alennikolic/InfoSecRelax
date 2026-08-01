/**
 * src/assets/help-modal.js
 *
 * Deljene funkcije za modal pomoći (samo prikaz - uređivanje je na
 * modules/pomoc-uredjivanje.php) - iste za svaki modul koji uključuje
 * includes/help-modal.php. Učitava se globalno (includes/footer.php),
 * pa svaka funkcija proverava da element zaista postoji pre nego što
 * ga dira - većina stranica još uvek nema modal pomoći na sebi.
 */

function openHelpModal() {
    var overlay = document.getElementById('help-modal-overlay');
    if (overlay) {
        overlay.classList.add('is-open');
    }
}

function closeHelpModal() {
    var overlay = document.getElementById('help-modal-overlay');
    if (overlay) {
        overlay.classList.remove('is-open');
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeHelpModal();
    }
});
