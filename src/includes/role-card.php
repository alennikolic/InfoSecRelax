<?php
/**
 * src/includes/role-card.php - prikaz jedne uloge i njene odgovornosti.
 *
 * Očekuje da je $role (red iz roles_responsibilities, spojen LEFT
 * JOIN-om sa personnel radi kolone assigned_name) već postavljen pre
 * uključivanja ovog fajla - deli scope sa foreach petljom iz koje se
 * poziva, isto kao ostali *-card.php partiali.
 */
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($role['role_name']) ?></span>
        <?php if (!empty($role['related_control_ref'])): ?>
            <span class="factor-category"><?= htmlspecialchars($role['related_control_ref']) ?></span>
        <?php endif; ?>
    </div>

    <?php if (!empty($role['description'])): ?>
        <p><?= nl2br(htmlspecialchars($role['description'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        Nosilac:
        <?= $role['assigned_name'] !== null ? htmlspecialchars($role['assigned_name']) : 'nije dodeljeno' ?>
    </p>

    <?php if (!empty($role['authority_level'])): ?>
        <p class="item-meta">Ovlašćenje: <?= htmlspecialchars($role['authority_level']) ?></p>
    <?php endif; ?>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu ulogu?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $role['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
