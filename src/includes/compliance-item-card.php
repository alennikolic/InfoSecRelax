<?php
/**
 * src/includes/compliance-item-card.php - prikaz jedne stavke
 * usklađenosti (A.5.31-5.36).
 *
 * Očekuje da je $item (red iz compliance_items, spojen LEFT JOIN-om sa
 * personnel radi owner_name) već postavljen pre uključivanja ovog
 * fajla - deli scope sa foreach petljom iz koje se poziva.
 */

$controlLabels = [
    '5.31' => 'Pravni, statutarni, regulatorni i ugovorni zahtevi',
    '5.32' => 'Prava intelektualne svojine',
    '5.33' => 'Zaštita zapisa',
    '5.34' => 'Privatnost i zaštita ličnih podataka',
    '5.35' => 'Nezavisna provera bezbednosti informacija',
    '5.36' => 'Usklađenost sa politikama, pravilima i standardima',
];

$statusLabels = [
    'usaglaseno'      => 'Usaglašeno',
    'delimicno'       => 'Delimično usaglašeno',
    'neusaglaseno'    => 'Neusaglašeno',
    'nije_primenjivo' => 'Nije primenjivo',
];

$statusTone = [
    'usaglaseno'      => 'is-positive',
    'delimicno'       => 'is-warning',
    'neusaglaseno'    => 'is-danger',
    'nije_primenjivo' => 'is-neutral',
];

$isReviewOverdue = !empty($item['next_review_due']) && $item['next_review_due'] < date('Y-m-d');
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($item['title']) ?></span>
        <span class="status-badge <?= htmlspecialchars($statusTone[$item['status']] ?? 'is-neutral') ?>">
            <?= htmlspecialchars($statusLabels[$item['status']] ?? $item['status']) ?>
        </span>
    </div>

    <p class="item-meta">
        <?= htmlspecialchars($item['control_ref']) ?> — <?= htmlspecialchars($controlLabels[$item['control_ref']] ?? '') ?>
        <?php if ($isReviewOverdue): ?>
            · <span class="status-badge is-danger">Pregled dospeo</span>
        <?php endif; ?>
    </p>

    <?php if (!empty($item['description'])): ?>
        <p><?= nl2br(htmlspecialchars($item['description'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        <?php if ($item['owner_name'] !== null): ?>
            Nosilac: <?= htmlspecialchars($item['owner_name']) ?> ·
        <?php endif; ?>
        <?php if (!empty($item['last_reviewed_at'])): ?>
            Poslednji pregled: <?= htmlspecialchars($item['last_reviewed_at']) ?>
        <?php else: ?>
            Još nije pregledano
        <?php endif; ?>
        <?php if (!empty($item['next_review_due'])): ?>
            · Sledeći pregled: <?= htmlspecialchars($item['next_review_due']) ?>
        <?php endif; ?>
    </p>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">

        <div class="form-row form-row-inline">
            <label for="status_<?= (int) $item['id'] ?>">Status:</label>
            <select name="status" id="status_<?= (int) $item['id'] ?>">
                <?php foreach ($statusLabels as $value => $statusLabel): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $item['status'] === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($statusLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary">Ažuriraj status</button>
        </div>
    </form>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu stavku?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
