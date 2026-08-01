/**
 * src/assets/help-modal.js
 *
 * Deljene funkcije za modal pomoći - iste za svaki modul koji uključuje
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
    toggleHelpEdit(false);
}

function toggleHelpEdit(showEdit) {
    var viewMode = document.getElementById('help-view-mode');
    var editMode = document.getElementById('help-edit-mode');
    if (viewMode) {
        viewMode.classList.toggle('is-hidden', showEdit);
    }
    if (editMode) {
        editMode.classList.toggle('is-hidden', !showEdit);
    }
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeHelpModal();
    }
});
