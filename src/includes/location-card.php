<?php
/**
 * src/includes/location-card.php - prikaz jedne fizičke lokacije
 * (Aneks A.7).
 *
 * Očekuje da je $location (red iz physical_locations) već postavljen
 * pre uključivanja ovog fajla - deli scope sa foreach petljom iz koje
 * se poziva.
 */
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($location['name']) ?></span>
        <div class="button-group">
            <span class="status-badge <?= $location['has_monitoring'] ? 'is-positive' : 'is-neutral' ?>">
                <?= $location['has_monitoring'] ? 'Video nadzor: Da' : 'Video nadzor: Ne' ?>
            </span>
            <button type="button" class="btn-secondary"
                onclick='openEditLocationModal(<?= json_encode([
                    "id"                     => (int) $location["id"],
                    "name"                   => $location["name"],
                    "address"                => $location["address"] ?? "",
                    "perimeter_description"  => $location["perimeter_description"] ?? "",
                    "entry_control_method"   => $location["entry_control_method"] ?? "",
                    "has_monitoring"         => (bool) $location["has_monitoring"],
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <?php if (!empty($location['address'])): ?>
        <p class="item-meta"><?= htmlspecialchars($location['address']) ?></p>
    <?php endif; ?>

    <?php if (!empty($location['perimeter_description'])): ?>
        <p><strong>Perimetar:</strong> <?= nl2br(htmlspecialchars($location['perimeter_description'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($location['entry_control_method'])): ?>
        <p><strong>Kontrola ulaska:</strong> <?= htmlspecialchars($location['entry_control_method']) ?></p>
    <?php endif; ?>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu lokaciju?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $location['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
