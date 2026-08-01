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
        <span class="status-badge <?= $location['has_monitoring'] ? 'is-positive' : 'is-neutral' ?>">
            <?= $location['has_monitoring'] ? 'Video nadzor: Da' : 'Video nadzor: Ne' ?>
        </span>
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
