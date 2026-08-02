<?php
/**
 * src/includes/risk-card.php - prikaz jednog rizika i njegovih mera
 * tretmana.
 *
 * Očekuje da su $risk (red iz risks, spojen LEFT JOIN-om sa assets radi
 * kolone asset_name) i $treatments (niz redova iz risk_treatments za
 * taj rizik, spojenih LEFT JOIN-om sa personnel radi owner_name; može
 * biti prazan niz) već postavljeni pre uključivanja ovog fajla - deli
 * scope sa foreach petljom iz koje se poziva.
 *
 * "Dodaj meru" ne otvara više ugrađenu formu u kartici - otvara
 * zajednički modal na nivou stranice (modules/procena-rizika.php), sa
 * ovim risk['id']/title prosleđenim preko JS poziva - isti obrazac kao
 * "+ Dodaj zahtev" u party-card.php.
 *
 * Status mere tretmana ovde ima samo jednu radnju koja menja stanje
 * ("Označi kao sprovedeno") - "u_toku" i "ponovo_otvoreno" nisu još
 * dostupni kroz UI, samo kroz direktan upis u bazu.
 */

$riskLevelLabels = [
    'nizak'   => 'Nizak',
    'srednji' => 'Srednji',
    'visok'   => 'Visok',
];

$riskLevelTone = [
    'nizak'   => 'is-positive',
    'srednji' => 'is-warning',
    'visok'   => 'is-danger',
];

$statusLabels = [
    'otvoren'    => 'Otvoren',
    'u_tretmanu' => 'U tretmanu',
    'tretiran'   => 'Tretiran',
    'prihvacen'  => 'Prihvaćen',
    'zatvoren'   => 'Zatvoren',
];

$reviewTriggerLabels = [
    'godisnji_ciklus' => 'Godišnji ciklus',
    'incident'        => 'Incident',
    'promena'         => 'Promena',
    'ostalo'          => 'Ostalo',
];

$treatmentOptionLabels = [
    'smanjiti'   => 'Smanjiti',
    'izbeci'     => 'Izbeći',
    'preneti'    => 'Preneti',
    'prihvatiti' => 'Prihvatiti',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($risk['title']) ?></span>
        <div class="button-group">
            <span class="status-badge <?= htmlspecialchars($riskLevelTone[$risk['risk_level']] ?? 'is-neutral') ?>">
                <?= htmlspecialchars($riskLevelLabels[$risk['risk_level']] ?? 'Nije izračunato') ?>
                (<?= (int) $risk['likelihood'] ?> × <?= (int) $risk['impact'] ?> = <?= (int) $risk['risk_score'] ?>)
            </span>
            <button type="button" class="btn-secondary"
                onclick='openEditRiskModal(<?= json_encode([
                    "id"                        => (int) $risk["id"],
                    "title"                     => $risk["title"],
                    "threat_description"        => $risk["threat_description"],
                    "vulnerability_description" => $risk["vulnerability_description"],
                    "asset_id"                  => $risk["asset_id"] ?? "",
                    "likelihood"                => (int) $risk["likelihood"],
                    "impact"                    => (int) $risk["impact"],
                    "identified_at"             => $risk["identified_at"],
                    "review_trigger"            => $risk["review_trigger"],
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <p><strong>Pretnja:</strong> <?= nl2br(htmlspecialchars($risk['threat_description'])) ?></p>
    <p><strong>Ranjivost:</strong> <?= nl2br(htmlspecialchars($risk['vulnerability_description'])) ?></p>

    <p class="item-meta">
        <?php if ($risk['asset_name'] !== null): ?>
            Sredstvo: <?= htmlspecialchars($risk['asset_name']) ?> ·
        <?php endif; ?>
        Identifikovano: <?= htmlspecialchars($risk['identified_at']) ?> ·
        Razlog pregleda: <?= htmlspecialchars($reviewTriggerLabels[$risk['review_trigger']] ?? $risk['review_trigger']) ?>
        <?php if (!empty($risk['last_reviewed_at'])): ?>
            · Poslednji pregled: <?= htmlspecialchars($risk['last_reviewed_at']) ?>
        <?php endif; ?>
    </p>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="risk_id" value="<?= (int) $risk['id'] ?>">

        <div class="form-row form-row-inline">
            <label for="status_<?= (int) $risk['id'] ?>">Status:</label>
            <select name="status" id="status_<?= (int) $risk['id'] ?>">
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $risk['status'] === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary">Ažuriraj status</button>
        </div>
    </form>

    <p class="item-title">Mere tretmana (<?= count($treatments) ?>)</p>

    <?php if (empty($treatments)): ?>
        <p class="empty-state">Još uvek nema unetih mera za ovaj rizik.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($treatments as $treatment): ?>
                <li class="requirement-item">
                    <div class="requirement-text">
                        <strong><?= htmlspecialchars($treatmentOptionLabels[$treatment['treatment_option']] ?? $treatment['treatment_option']) ?>:</strong>
                        <?= nl2br(htmlspecialchars($treatment['description'])) ?>
                    </div>
                    <p class="item-meta">
                        <?php if ($treatment['owner_name'] !== null): ?>
                            Nosilac: <?= htmlspecialchars($treatment['owner_name']) ?> ·
                        <?php endif; ?>
                        <?php if (!empty($treatment['due_date'])): ?>
                            Rok: <?= htmlspecialchars($treatment['due_date']) ?> ·
                        <?php endif; ?>
                        Status: <?= $treatment['status'] === 'sprovedeno' ? 'Sprovedeno' : 'Planirano' ?>
                    </p>
                    <div class="card-actions">
                        <?php if ($treatment['status'] !== 'sprovedeno'): ?>
                            <form method="post" class="factor-delete-form">
                                <input type="hidden" name="action" value="complete_treatment">
                                <input type="hidden" name="id" value="<?= (int) $treatment['id'] ?>">
                                <button type="submit" class="btn-secondary">Označi kao sprovedeno</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu meru?');">
                            <input type="hidden" name="action" value="delete_treatment">
                            <input type="hidden" name="id" value="<?= (int) $treatment['id'] ?>">
                            <button type="submit" class="btn-delete">Obriši</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <button type="button" class="btn-secondary"
        onclick='openTreatmentModal(<?= (int) $risk['id'] ?>, <?= json_encode($risk['title'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Dodaj meru</button>

    <form method="post" class="factor-delete-form card-footer-right" onsubmit="return confirm('Obrisati ovaj rizik i sve njegove mere tretmana?');">
        <input type="hidden" name="action" value="delete_risk">
        <input type="hidden" name="id" value="<?= (int) $risk['id'] ?>">
        <button type="submit" class="btn-delete">Obriši rizik</button>
    </form>
</div>
