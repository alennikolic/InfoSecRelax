<?php
/**
 * src/includes/help-modal.php
 *
 * Deljeni modal za pomoć - samo prikaz (view-only). Uređivanje se radi
 * centralno na modules/pomoc-uredjivanje.php, ne "na licu mesta" po
 * svakoj stranici - jedno mesto za sav sadržaj, umesto da se mehanizam
 * za uređivanje ponavlja po 28 stranica.
 *
 * Očekuje $pageSlug (string, slug stranice čija se pomoć prikazuje) i
 * $helpContent (niz iz help_content ili null ako sadržaj za ovu
 * stranicu još nije unet) već postavljene pre uključivanja ovog fajla.
 *
 * JavaScript funkcije (openHelpModal, closeHelpModal) su deljene za
 * sve module - videti assets/help-modal.js, uključeno jednom u
 * includes/footer.php, važi na svakoj stranici.
 */

$helpTitle = $helpContent['title'] ?? ('Pomoć — ' . ucfirst(str_replace('-', ' ', $pageSlug)));
$helpBody  = $helpContent['body'] ?? '';
?>
<div class="modal-overlay" id="help-modal-overlay" onclick="closeHelpModal()">
    <div class="modal-box modal-box-wide" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title"><?= htmlspecialchars($helpTitle) ?></span>
            <button type="button" class="modal-close" onclick="closeHelpModal()" aria-label="Zatvori">&times;</button>
        </div>

        <?php if ($helpBody === ''): ?>
            <p class="empty-state">Za ovu stranicu još uvek nije unet sadržaj pomoći.</p>
        <?php else: ?>
            <div class="help-content"><?= $helpBody ?></div>
        <?php endif; ?>

        <div class="modal-actions">
            <button type="button" class="btn-primary" onclick="closeHelpModal()">Zatvori</button>
        </div>
    </div>
</div>
