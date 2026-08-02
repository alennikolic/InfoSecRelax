<?php
/**
 * src/includes/supplier-card.php - prikaz jednog dobavljača i njegovih
 * pregleda (A.5.19-5.23).
 *
 * Očekuje da su $supplier (red iz suppliers) i $reviews (niz redova iz
 * supplier_reviews za tog dobavljača, spojenih LEFT JOIN-om sa
 * personnel radi reviewer_name; može biti prazan niz) već postavljeni
 * pre uključivanja ovog fajla - deli scope sa foreach petljom iz koje
 * se poziva.
 *
 * "+ Dodaj pregled" ne otvara više ugrađenu formu u kartici - otvara
 * zajednički modal na nivou stranice (modules/dobavljaci.php).
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
        <div class="button-group">
            <span class="status-badge <?= htmlspecialchars($riskLevelTone[$supplier['risk_level']] ?? 'is-neutral') ?>">
                Rizik: <?= htmlspecialchars(ucfirst($supplier['risk_level'])) ?>
            </span>
            <button type="button" class="btn-secondary"
                onclick='openEditSupplierModal(<?= json_encode([
                    "id"                      => (int) $supplier["id"],
                    "name"                    => $supplier["name"],
                    "risk_level"              => $supplier["risk_level"],
                    "has_data_access"         => (bool) $supplier["has_data_access"],
                    "is_cloud_service"        => (bool) $supplier["is_cloud_service"],
                    "dpa_signed"              => (bool) $supplier["dpa_signed"],
                    "exit_strategy_confirmed" => (bool) $supplier["exit_strategy_confirmed"],
                    "subprocessors_reviewed"  => (bool) $supplier["subprocessors_reviewed"],
                    "sla_requirements"        => $supplier["sla_requirements"] ?? "",
                    "contract_start"          => $supplier["contract_start"] ?? "",
                    "contract_end"            => $supplier["contract_end"] ?? "",
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
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

    <button type="button" class="btn-secondary"
        onclick='openReviewModal(<?= (int) $supplier['id'] ?>, <?= json_encode($supplier['name'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>+ Dodaj pregled</button>

    <form method="post" class="factor-delete-form card-footer-right" onsubmit="return confirm('Obrisati ovog dobavljača i sve njegove preglede?');">
        <input type="hidden" name="action" value="delete_supplier">
        <input type="hidden" name="id" value="<?= (int) $supplier['id'] ?>">
        <button type="submit" class="btn-delete">Obriši dobavljača</button>
    </form>
</div>
