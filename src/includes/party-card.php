<?php
/**
 * src/includes/party-card.php - prikaz jedne zainteresovane strane sa
 * njenim zahtevima.
 *
 * Očekuje da su $party (red iz interested_parties) i $requirements
 * (niz redova iz interested_party_requirements za tu stranu, može biti
 * prazan niz) već postavljeni pre uključivanja ovog fajla - deli scope
 * sa foreach petljom iz koje se poziva, isto kao includes/factor-card.php.
 */
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($party['name']) ?></span>
        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu zainteresovanu stranu i sve njene zahteve?');">
            <input type="hidden" name="action" value="delete_party">
            <input type="hidden" name="id" value="<?= (int) $party['id'] ?>">
            <button type="submit" class="btn-delete">Obriši stranu</button>
        </form>
    </div>

    <?php if (empty($requirements)): ?>
        <p class="empty-state">Još uvek nema unetih zahteva za ovu stranu.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($requirements as $requirement): ?>
                <li class="requirement-item">
                    <div class="requirement-text">
                        <?= nl2br(htmlspecialchars($requirement['requirement'])) ?>
                    </div>
                    <div class="requirement-meta">
                        <span class="requirement-status<?= $requirement['addressed_by_isms'] ? ' is-addressed' : ' is-not-addressed' ?>">
                            <?= $requirement['addressed_by_isms'] ? 'Pokriveno ISMS-om' : 'Nije pokriveno ISMS-om' ?>
                        </span>
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj zahtev?');">
                            <input type="hidden" name="action" value="delete_requirement">
                            <input type="hidden" name="id" value="<?= (int) $requirement['id'] ?>">
                            <button type="submit" class="btn-delete">Obriši</button>
                        </form>
                    </div>
                    <?php if (!empty($requirement['notes'])): ?>
                        <p class="requirement-notes"><?= nl2br(htmlspecialchars($requirement['notes'])) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="add_requirement">
        <input type="hidden" name="interested_party_id" value="<?= (int) $party['id'] ?>">

        <div class="form-row">
            <label for="requirement_<?= (int) $party['id'] ?>">Novi zahtev</label>
            <textarea name="requirement" id="requirement_<?= (int) $party['id'] ?>" rows="2" required
                placeholder="npr. Klijenti očekuju da njihovi lični podaci budu zaštićeni od neovlašćenog pristupa."></textarea>
        </div>

        <div class="form-row form-row-inline">
            <label class="checkbox-label">
                <input type="checkbox" name="addressed_by_isms" value="1" checked>
                Pokriveno kroz ISMS
            </label>
        </div>

        <div class="form-row">
            <label for="notes_<?= (int) $party['id'] ?>">Napomena (opciono)</label>
            <input type="text" name="notes" id="notes_<?= (int) $party['id'] ?>">
        </div>

        <button type="submit" class="btn-secondary">Dodaj zahtev</button>
    </form>
</div>
