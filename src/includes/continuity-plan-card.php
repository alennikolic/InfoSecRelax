<?php
/**
 * src/includes/continuity-plan-card.php - prikaz jednog plana
 * kontinuiteta poslovanja (A.5.29-5.30).
 *
 * Očekuje da je $plan (red iz continuity_plans, spojen LEFT JOIN-om sa
 * personnel radi owner_name) već postavljen pre uključivanja ovog
 * fajla - deli scope sa foreach petljom iz koje se poziva.
 */

$testResultLabels = [
    'uspesno'           => 'Uspešno',
    'delimicno_uspesno' => 'Delimično uspešno',
    'neuspesno'         => 'Neuspešno',
    'nije_testirano'    => 'Nije testirano',
];

$testResultTone = [
    'uspesno'           => 'is-positive',
    'delimicno_uspesno' => 'is-warning',
    'neuspesno'         => 'is-danger',
    'nije_testirano'    => 'is-neutral',
];

$isTestOverdue = !empty($plan['next_test_due']) && $plan['next_test_due'] < date('Y-m-d');
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($plan['scenario']) ?></span>
        <div class="button-group">
            <span class="status-badge <?= htmlspecialchars($testResultTone[$plan['test_result']] ?? 'is-neutral') ?>">
                <?= htmlspecialchars($testResultLabels[$plan['test_result']] ?? $plan['test_result']) ?>
            </span>
            <button type="button" class="btn-secondary"
                onclick='openEditPlanModal(<?= json_encode([
                    "id"               => (int) $plan["id"],
                    "scenario"         => $plan["scenario"],
                    "plan_description" => $plan["plan_description"],
                    "owner_id"         => $plan["owner_id"] ?? "",
                    "next_test_due"    => $plan["next_test_due"] ?? "",
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <?php if ($isTestOverdue): ?>
        <p><span class="status-badge is-danger">Test dospeo</span></p>
    <?php endif; ?>

    <p><?= nl2br(htmlspecialchars($plan['plan_description'])) ?></p>

    <p class="item-meta">
        <?php if ($plan['owner_name'] !== null): ?>
            Nosilac: <?= htmlspecialchars($plan['owner_name']) ?> ·
        <?php endif; ?>
        <?php if (!empty($plan['last_tested_at'])): ?>
            Poslednji test: <?= htmlspecialchars($plan['last_tested_at']) ?>
        <?php else: ?>
            Još nije testirano
        <?php endif; ?>
        <?php if (!empty($plan['next_test_due'])): ?>
            · Sledeći test: <?= htmlspecialchars($plan['next_test_due']) ?>
        <?php endif; ?>
    </p>

    <button type="button" class="btn-secondary"
        onclick='openTestModal(<?= (int) $plan['id'] ?>, <?= json_encode($plan['scenario'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Zabeleži test</button>

    <form method="post" class="factor-delete-form card-footer-right" onsubmit="return confirm('Obrisati ovaj plan kontinuiteta?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
