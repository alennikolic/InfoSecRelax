<?php
/**
 * src/includes/asset-card.php - prikaz jednog sredstva iz popisa.
 *
 * Očekuje da je $asset (red iz assets, spojen LEFT JOIN-om sa personnel
 * radi kolone owner_name) već postavljen pre uključivanja ovog fajla -
 * deli scope sa foreach petljom iz koje se poziva.
 */

$assetTypeLabels = [
    'informacija' => 'Informacija',
    'hardver'     => 'Hardver',
    'softver'     => 'Softver',
    'usluga'      => 'Usluga',
    'ljudi'       => 'Ljudi',
];

$classificationLabels = [
    'javno'             => 'Javno',
    'interno'           => 'Interno',
    'poverljivo'        => 'Poverljivo',
    'strogo_poverljivo' => 'Strogo poverljivo',
];

$classificationTone = [
    'javno'             => 'is-neutral',
    'interno'           => 'is-neutral',
    'poverljivo'        => 'is-warning',
    'strogo_poverljivo' => 'is-danger',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($asset['name']) ?></span>
        <div class="button-group">
            <span class="factor-category">
                <?= htmlspecialchars($assetTypeLabels[$asset['asset_type']] ?? $asset['asset_type']) ?>
            </span>
            <button type="button" class="btn-secondary"
                onclick='openEditAssetModal(<?= json_encode([
                    "id"             => (int) $asset["id"],
                    "name"           => $asset["name"],
                    "asset_type"     => $asset["asset_type"],
                    "description"    => $asset["description"] ?? "",
                    "owner_id"       => $asset["owner_id"] ?? "",
                    "classification" => $asset["classification"],
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <?php if (!empty($asset['description'])): ?>
        <p><?= nl2br(htmlspecialchars($asset['description'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        Vlasnik: <?= $asset['owner_name'] !== null ? htmlspecialchars($asset['owner_name']) : 'nije dodeljen' ?>
    </p>

    <p>
        <span class="status-badge <?= htmlspecialchars($classificationTone[$asset['classification']] ?? 'is-neutral') ?>">
            <?= htmlspecialchars($classificationLabels[$asset['classification']] ?? $asset['classification']) ?>
        </span>
    </p>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovo sredstvo?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $asset['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
