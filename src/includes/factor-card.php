<?php
/**
 * src/includes/factor-card.php - prikaz jednog faktora konteksta.
 *
 * Očekuje da je $factor (asocijativni niz sa poljima iz context_factors)
 * već postavljen pre uključivanja ovog fajla - deli scope sa foreach
 * petljom iz koje se poziva.
 *
 * "Uredi" dugme ne vodi na novu stranicu - poziva openEditFactorModal()
 * (definisano u modules/kontekst.php) sa podacima ovog faktora,
 * bezbedno prosleđenim kao JSON (JSON_HEX_* flagovi garantuju da se
 * ništa iz opisa ne može "izvući" iz onclick atributa).
 */
?>
<div class="factor-card">
    <?php if (!empty($factor['category'])): ?>
        <span class="factor-category"><?= htmlspecialchars($factor['category']) ?></span>
    <?php endif; ?>
    <p><?= nl2br(htmlspecialchars($factor['description'])) ?></p>
    <div class="card-actions">
        <button type="button" class="btn-secondary"
            onclick='openEditFactorModal(<?= json_encode([
                "id"          => (int) $factor["id"],
                "factor_type" => $factor["factor_type"],
                "category"    => $factor["category"] ?? "",
                "description" => $factor["description"],
            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj faktor?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $factor['id'] ?>">
            <button type="submit" class="btn-delete">Obriši</button>
        </form>
    </div>
</div>
