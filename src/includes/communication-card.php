<?php
/**
 * src/includes/communication-card.php - prikaz jedne stavke
 * komunikacionog plana.
 *
 * Očekuje da je $item (red iz communications_plan) već postavljen pre
 * uključivanja ovog fajla - deli scope sa foreach petljom iz koje se
 * poziva.
 */
?>
<div class="factor-card">
    <p><?= nl2br(htmlspecialchars($item['what_is_communicated'])) ?></p>

    <p class="item-meta">
        Kome: <?= htmlspecialchars($item['audience']) ?> ·
        Kada: <?= htmlspecialchars($item['trigger_condition']) ?> ·
        Kako: <?= htmlspecialchars($item['channel']) ?>
    </p>

    <div class="card-footer-right button-group">
        <button type="button" class="btn-secondary"
            onclick='openEditCommunicationModal(<?= json_encode([
                "id"                    => (int) $item["id"],
                "what_is_communicated"  => $item["what_is_communicated"],
                "audience"              => $item["audience"],
                "trigger_condition"     => $item["trigger_condition"],
                "channel"               => $item["channel"],
            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu stavku?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
            <button type="submit" class="btn-delete">Obriši</button>
        </form>
    </div>
</div>
