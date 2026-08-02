<?php
/**
 * src/includes/party-card.php - prikaz jedne zainteresovane strane sa
 * njenim zahtevima.
 *
 * Očekuje da je $party (red iz interested_parties) i $requirements
 * (niz redova iz interested_party_requirements za tu stranu, može biti
 * prazan niz) već postavljeni pre uključivanja ovog fajla - deli scope
 * sa foreach petljom iz koje se poziva, isto kao includes/factor-card.php.
 *
 * "Uredi" menja naziv i vrstu strane. "+ Dodaj zahtev" ne otvara više
 * ugrađenu formu u kartici - otvara zajednički modal na nivou stranice
 * (modules/zainteresovane-strane.php), sa ovim party['id']/name
 * prosleđenim preko JS poziva.
 */
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($party['name']) ?></span>
        <div class="button-group">
            <button type="button" class="btn-secondary"
                onclick='openEditPartyModal(<?= json_encode([
                    "id"         => (int) $party["id"],
                    "name"       => $party["name"],
                    "party_type" => $party["party_type"],
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
            <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu zainteresovanu stranu i sve njene zahteve?');">
                <input type="hidden" name="action" value="delete_party">
                <input type="hidden" name="id" value="<?= (int) $party['id'] ?>">
                <button type="submit" class="btn-delete">Obriši stranu</button>
            </form>
        </div>
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
                        <span class="status-badge<?= $requirement['addressed_by_isms'] ? ' is-positive' : ' is-warning' ?>">
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

    <button type="button" class="btn-secondary"
        onclick="openAddRequirementModal(<?= (int) $party['id'] ?>, <?= json_encode($party['name'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)">+ Dodaj zahtev</button>
</div>
