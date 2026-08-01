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
        <span class="status-badge <?= htmlspecialchars($testResultTone[$plan['test_result']] ?? 'is-neutral') ?>">
            <?= htmlspecialchars($testResultLabels[$plan['test_result']] ?? $plan['test_result']) ?>
        </span>
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

    <form method="post" class="subform">
        <input type="hidden" name="action" value="record_test">
        <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">

        <div class="form-row">
            <label for="test_result_<?= (int) $plan['id'] ?>">Rezultat testa</label>
            <select name="test_result" id="test_result_<?= (int) $plan['id'] ?>" required>
                <option value="uspesno">Uspešno</option>
                <option value="delimicno_uspesno">Delimično uspešno</option>
                <option value="neuspesno">Neuspešno</option>
            </select>
        </div>

        <div class="form-row">
            <label for="last_tested_at_<?= (int) $plan['id'] ?>">Datum testa</label>
            <input type="date" name="last_tested_at" id="last_tested_at_<?= (int) $plan['id'] ?>" required
                value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-row">
            <label for="next_test_due_<?= (int) $plan['id'] ?>">Sledeći test dospeva (opciono)</label>
            <input type="date" name="next_test_due" id="next_test_due_<?= (int) $plan['id'] ?>">
        </div>

        <button type="submit" class="btn-secondary">Zabeleži test</button>
    </form>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj plan kontinuiteta?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $plan['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
