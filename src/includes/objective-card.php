<?php
/**
 * src/includes/objective-card.php - prikaz jednog cilja bezbednosti i
 * plana njegovog ostvarenja.
 *
 * Očekuje da je $objective (red iz objectives, spojen LEFT JOIN-om sa
 * personnel radi owner_name i sa risks radi risk_title) već postavljen
 * pre uključivanja ovog fajla - deli scope sa foreach petljom iz koje
 * se poziva.
 */

$statusLabels = [
    'planiran'   => 'Planiran',
    'u_toku'     => 'U toku',
    'ostvaren'   => 'Ostvaren',
    'neostvaren' => 'Neostvaren',
];

$statusTone = [
    'planiran'   => 'is-neutral',
    'u_toku'     => 'is-warning',
    'ostvaren'   => 'is-positive',
    'neostvaren' => 'is-danger',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($objective['title']) ?></span>
        <span class="status-badge <?= htmlspecialchars($statusTone[$objective['status']] ?? 'is-neutral') ?>">
            <?= htmlspecialchars($statusLabels[$objective['status']] ?? $objective['status']) ?>
        </span>
    </div>

    <p><strong>Šta:</strong> <?= nl2br(htmlspecialchars($objective['what_will_be_done'])) ?></p>

    <?php if (!empty($objective['resources_required'])): ?>
        <p><strong>Resursi:</strong> <?= nl2br(htmlspecialchars($objective['resources_required'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($objective['evaluation_method'])): ?>
        <p><strong>Merenje uspeha:</strong> <?= nl2br(htmlspecialchars($objective['evaluation_method'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        <?php if ($objective['owner_name'] !== null): ?>
            Nosilac: <?= htmlspecialchars($objective['owner_name']) ?> ·
        <?php endif; ?>
        <?php if (!empty($objective['due_date'])): ?>
            Rok: <?= htmlspecialchars($objective['due_date']) ?> ·
        <?php endif; ?>
        <?php if ($objective['risk_title'] !== null): ?>
            Povezan rizik: <?= htmlspecialchars($objective['risk_title']) ?>
        <?php endif; ?>
    </p>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= (int) $objective['id'] ?>">

        <div class="form-row form-row-inline">
            <label for="status_<?= (int) $objective['id'] ?>">Status:</label>
            <select name="status" id="status_<?= (int) $objective['id'] ?>">
                <?php foreach ($statusLabels as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $objective['status'] === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary">Ažuriraj status</button>
        </div>
    </form>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj cilj?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $objective['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
