<?php
/**
 * src/includes/change-card.php - prikaz jedne planirane promene
 * (Klauzula 6.3).
 *
 * Očekuje da je $change (red iz planned_changes, spojen LEFT JOIN-om
 * sa personnel radi approved_by_name) već postavljen pre uključivanja
 * ovog fajla - deli scope sa foreach petljom iz koje se poziva.
 */

$statusLabels = [
    'predlozeno' => 'Predloženo',
    'odobreno'   => 'Odobreno',
    'sprovedeno' => 'Sprovedeno',
    'odbaceno'   => 'Odbačeno',
];

$statusTone = [
    'predlozeno' => 'is-neutral',
    'odobreno'   => 'is-warning',
    'sprovedeno' => 'is-positive',
    'odbaceno'   => 'is-danger',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($change['title']) ?></span>
        <span class="status-badge <?= htmlspecialchars($statusTone[$change['status']] ?? 'is-neutral') ?>">
            <?= htmlspecialchars($statusLabels[$change['status']] ?? $change['status']) ?>
        </span>
    </div>

    <?php if ($change['is_unintended']): ?>
        <p><span class="status-badge is-warning">Nenamerna promena</span></p>
    <?php endif; ?>

    <p><?= nl2br(htmlspecialchars($change['description'])) ?></p>

    <?php if (!empty($change['impact_assessment'])): ?>
        <p><strong>Procena uticaja:</strong> <?= nl2br(htmlspecialchars($change['impact_assessment'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($change['test_plan'])): ?>
        <p><strong>Plan testiranja:</strong> <?= nl2br(htmlspecialchars($change['test_plan'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($change['rollback_plan'])): ?>
        <p><strong>Plan povratka:</strong> <?= nl2br(htmlspecialchars($change['rollback_plan'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        <?php if ($change['approved_by_name'] !== null): ?>
            Odobrio: <?= htmlspecialchars($change['approved_by_name']) ?> ·
        <?php endif; ?>
        <?php if (!empty($change['planned_date'])): ?>
            Planirani datum: <?= htmlspecialchars($change['planned_date']) ?>
        <?php endif; ?>
    </p>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= (int) $change['id'] ?>">

        <div class="form-row form-row-inline">
            <label for="status_<?= (int) $change['id'] ?>">Status:</label>
            <select name="status" id="status_<?= (int) $change['id'] ?>">
                <?php foreach ($statusLabels as $value => $statusLabel): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $change['status'] === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($statusLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary">Ažuriraj status</button>
        </div>
    </form>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu promenu?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $change['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
