<?php
/**
 * src/includes/resource-card.php - prikaz jednog resursa (Klauzula 7.1).
 *
 * Očekuje da je $resource (red iz isms_resources, spojen LEFT JOIN-om
 * sa personnel radi provided_by_name) već postavljen pre uključivanja
 * ovog fajla - deli scope sa foreach petljom iz koje se poziva.
 */

$resourceTypeLabels = [
    'budzet'           => 'Budžet',
    'osoblje'          => 'Osoblje',
    'alat_ili_licenca' => 'Alat ili licenca',
    'obuka'            => 'Obuka',
    'infrastruktura'   => 'Infrastruktura',
    'ostalo'           => 'Ostalo',
];

$statusLabels = [
    'planirano'    => 'Planirano',
    'obezbedjeno'  => 'Obezbeđeno',
    'u_koriscenju' => 'U korišćenju',
];

$statusTone = [
    'planirano'    => 'is-neutral',
    'obezbedjeno'  => 'is-warning',
    'u_koriscenju' => 'is-positive',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($resourceTypeLabels[$resource['resource_type']] ?? $resource['resource_type']) ?></span>
        <span class="status-badge <?= htmlspecialchars($statusTone[$resource['status']] ?? 'is-neutral') ?>">
            <?= htmlspecialchars($statusLabels[$resource['status']] ?? $resource['status']) ?>
        </span>
    </div>

    <p><?= nl2br(htmlspecialchars($resource['description'])) ?></p>

    <p class="item-meta">
        <?php if (!empty($resource['amount_or_quantity'])): ?>
            Iznos/količina: <?= htmlspecialchars($resource['amount_or_quantity']) ?> ·
        <?php endif; ?>
        <?php if ($resource['provided_by_name'] !== null): ?>
            Obezbedio: <?= htmlspecialchars($resource['provided_by_name']) ?>
        <?php endif; ?>
    </p>

    <?php if (!empty($resource['review_date'])): ?>
        <p class="item-meta">Pregled: <?= htmlspecialchars($resource['review_date']) ?></p>
    <?php endif; ?>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= (int) $resource['id'] ?>">

        <div class="form-row form-row-inline">
            <label for="status_<?= (int) $resource['id'] ?>">Status:</label>
            <select name="status" id="status_<?= (int) $resource['id'] ?>">
                <?php foreach ($statusLabels as $value => $statusLabel): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $resource['status'] === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($statusLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary">Ažuriraj status</button>
        </div>
    </form>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj resurs?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $resource['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
