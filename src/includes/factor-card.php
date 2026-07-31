<?php
/**
 * includes/factor-card.php - prikaz jednog faktora konteksta.
 *
 * Očekuje da je $factor (asocijativni niz sa poljima iz context_factors)
 * već postavljen pre uključivanja ovog fajla - deli scope sa foreach
 * petljom iz koje se poziva.
 */
?>
<div class="factor-card">
    <?php if (!empty($factor['category'])): ?>
        <span class="factor-category"><?= htmlspecialchars($factor['category']) ?></span>
    <?php endif; ?>
    <p><?= nl2br(htmlspecialchars($factor['description'])) ?></p>
    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj faktor?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $factor['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
