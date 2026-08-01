<?php
/**
 * src/includes/supplier-card.php - prikaz jednog dobavljača i njegovih
 * pregleda (A.5.19-5.23).
 *
 * Očekuje da su $supplier (red iz suppliers) i $reviews (niz redova iz
 * supplier_reviews za tog dobavljača, spojenih LEFT JOIN-om sa
 * personnel radi reviewer_name; može biti prazan niz) već postavljeni
 * pre uključivanja ovog fajla - deli scope sa foreach petljom iz koje
 * se poziva. Takođe očekuje $activePersonnelOptions, postavljen jednom
 * u dobavljaci.php pre foreach petlje.
 */

$riskLevelTone = [
    'nizak'   => 'is-positive',
    'srednji' => 'is-warning',
    'visok'   => 'is-danger',
];

$checks = [
    'has_data_access'        => 'Pristup podacima',
    'is_cloud_service'       => 'Cloud usluga',
    'dpa_signed'              => 'DPA potpisan',
    'exit_strategy_confirmed' => 'Izlazna strategija',
    'subprocessors_reviewed'  => 'Podobrađivači pregledani',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($supplier['name']) ?></span>
        <span class="status-badge <?= htmlspecialchars($riskLevelTone[$supplier['risk_level']] ?? 'is-neutral') ?>">
            Rizik: <?= htmlspecialchars(ucfirst($supplier['risk_level'])) ?>
        </span>
    </div>

    <p>
        <?php foreach ($checks as $field => $label): ?>
            <span class="status-badge <?= $supplier[$field] ? 'is-positive' : 'is-neutral' ?>">
                <?= htmlspecialchars($label) ?>: <?= $supplier[$field] ? 'Da' : 'Ne' ?>
            </span>
        <?php endforeach; ?>
    </p>

    <?php if (!empty($supplier['sla_requirements'])): ?>
        <p><strong>Zahtevi u ugovoru:</strong> <?= nl2br(htmlspecialchars($supplier['sla_requirements'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        <?php if (!empty($supplier['contract_start']) || !empty($supplier['contract_end'])): ?>
            Ugovor:
            <?= !empty($supplier['contract_start']) ? htmlspecialchars($supplier['contract_start']) : 'nepoznat početak' ?>
            –
            <?= !empty($supplier['contract_end']) ? htmlspecialchars($supplier['contract_end']) : 'otvoren' ?>
            ·
        <?php endif; ?>
        <?php if (!empty($supplier['last_reviewed_at'])): ?>
            Poslednji pregled: <?= htmlspecialchars($supplier['last_reviewed_at']) ?>
        <?php else: ?>
            Još nije pregledan
        <?php endif; ?>
    </p>

    <p class="item-title">Pregledi (<?= count($reviews) ?>)</p>

    <?php if (empty($reviews)): ?>
        <p class="empty-state">Još uvek nema evidentiranih pregleda.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($reviews as $review): ?>
                <li class="requirement-item">
                    <div class="requirement-text">
                        <strong><?= htmlspecialchars($review['review_date']) ?></strong>
                        <?php if (!empty($review['findings'])): ?>
                            — <?= nl2br(htmlspecialchars($review['findings'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($review['reviewer_name'] !== null): ?>
                        <p class="item-meta">Pregledao: <?= htmlspecialchars($review['reviewer_name']) ?></p>
                    <?php endif; ?>
                    <div class="card-actions">
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj pregled?');">
                            <input type="hidden" name="action" value="delete_review">
                            <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
                            <button type="submit" class="btn-delete">Obriši</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="add_review">
        <input type="hidden" name="supplier_id" value="<?= (int) $supplier['id'] ?>">

        <div class="form-row">
            <label for="review_date_<?= (int) $supplier['id'] ?>">Datum pregleda</label>
            <input type="date" name="review_date" id="review_date_<?= (int) $supplier['id'] ?>" required
                value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-row">
            <label for="findings_<?= (int) $supplier['id'] ?>">Nalazi (opciono)</label>
            <textarea name="findings" id="findings_<?= (int) $supplier['id'] ?>" rows="2"
                placeholder="npr. Dobavljač je promenio podizvođača za skladištenje podataka."></textarea>
        </div>

        <div class="form-row">
            <label for="reviewed_by_<?= (int) $supplier['id'] ?>">Pregledao (opciono)</label>
            <select name="reviewed_by" id="reviewed_by_<?= (int) $supplier['id'] ?>">
                <option value="">Nije dodeljen</option>
                <?php foreach ($activePersonnelOptions as $option): ?>
                    <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-secondary">Dodaj pregled</button>
    </form>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovog dobavljača i sve njegove preglede?');">
        <input type="hidden" name="action" value="delete_supplier">
        <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
        <button type="submit" class="btn-delete">Obriši dobavljača</button>
    </form>
</div>
