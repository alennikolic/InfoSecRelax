<?php
/**
 * src/includes/help-modal.php
 *
 * Deljeni modal za pomoć - prikaz i uređivanje sadržaja na istom
 * mestu, bez odlaska na posebnu stranicu. Očekuje $pageSlug (string,
 * slug stranice čija se pomoć prikazuje) i $helpContent (niz iz
 * help_content ili null ako sadržaj za ovu stranicu još nije unet)
 * već postavljene pre uključivanja ovog fajla.
 *
 * JavaScript funkcije (openHelpModal, closeHelpModal, toggleHelpEdit)
 * su deljene za sve module - videti assets/help-modal.js, uključeno
 * jednom u includes/footer.php, važi na svakoj stranici.
 *
 * Modul koji uključuje ovaj fajl mora sam da obradi POST akciju
 * "save_help" (poziva saveHelpContent() iz help-content.php sa svojim
 * $pageSlug) - nekoliko linija po modulu, videti kontekst.php.
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

        <div id="help-view-mode">
            <?php if ($helpBody === ''): ?>
                <p class="empty-state">Za ovu stranicu još uvek nije unet sadržaj pomoći.</p>
            <?php else: ?>
                <div class="help-content"><?= $helpBody ?></div>
            <?php endif; ?>

            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="toggleHelpEdit(true)">Uredi</button>
                <button type="button" class="btn-primary" onclick="closeHelpModal()">Zatvori</button>
            </div>
        </div>

        <div id="help-edit-mode" class="is-hidden">
            <form method="post">
                <input type="hidden" name="action" value="save_help">

                <div class="form-row">
                    <label for="help_title">Naslov</label>
                    <input type="text" name="help_title" id="help_title" required
                        value="<?= htmlspecialchars($helpTitle) ?>">
                </div>

                <div class="form-row">
                    <label for="help_body">Sadržaj (HTML - &lt;p&gt;, &lt;h4&gt;, &lt;ul&gt;/&lt;li&gt;, &lt;a href&gt;...)</label>
                    <textarea name="help_body" id="help_body" rows="16"><?= htmlspecialchars($helpBody) ?></textarea>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="toggleHelpEdit(false)">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj pomoć</button>
                </div>
            </form>
        </div>
    </div>
</div>
