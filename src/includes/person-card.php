<?php
/**
 * src/includes/person-card.php - prikaz jednog zaposlenog/saradnika.
 *
 * Očekuje da je $person (red iz personnel) već postavljen pre
 * uključivanja ovog fajla - deli scope sa foreach petljom iz koje se
 * poziva, isto kao includes/factor-card.php i includes/party-card.php.
 */

$employmentTypeLabels = [
    'zaposleni'          => 'Zaposleni',
    'honorarni_saradnik' => 'Honorarni saradnik',
    'spoljni_dobavljac'  => 'Spoljni dobavljač',
    'ostalo'             => 'Ostalo',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($person['full_name']) ?></span>
        <div class="button-group">
            <span class="factor-category">
                <?= htmlspecialchars($employmentTypeLabels[$person['employment_type']] ?? $person['employment_type']) ?>
            </span>
            <button type="button" class="btn-secondary"
                onclick='openEditPersonModal(<?= json_encode([
                    "id"              => (int) $person["id"],
                    "full_name"       => $person["full_name"],
                    "employment_type" => $person["employment_type"],
                    "job_title"       => $person["job_title"] ?? "",
                    "email"           => $person["email"] ?? "",
                    "start_date"      => $person["start_date"] ?? "",
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <?php if (!empty($person['job_title'])): ?>
        <p class="item-meta"><?= htmlspecialchars($person['job_title']) ?></p>
    <?php endif; ?>

    <?php if (!empty($person['email'])): ?>
        <p class="item-meta"><?= htmlspecialchars($person['email']) ?></p>
    <?php endif; ?>

    <?php if (!empty($person['start_date']) || !empty($person['end_date'])): ?>
        <p class="item-meta">
            <?php if (!empty($person['start_date'])): ?>
                Od <?= htmlspecialchars($person['start_date']) ?>
            <?php endif; ?>
            <?php if (!empty($person['end_date'])): ?>
                do <?= htmlspecialchars($person['end_date']) ?>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <div class="card-actions">
        <?php if ($person['is_active']): ?>
            <form method="post" class="factor-delete-form" onsubmit="return confirm('Označiti kao neaktivnog? Datum prestanka će biti postavljen na danas, ako već nije unet.');">
                <input type="hidden" name="action" value="deactivate">
                <input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
                <button type="submit" class="btn-secondary">Deaktiviraj</button>
            </form>
        <?php else: ?>
            <form method="post" class="factor-delete-form" onsubmit="return confirm('Ponovo aktivirati ovu osobu?');">
                <input type="hidden" name="action" value="reactivate">
                <input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
                <button type="submit" class="btn-secondary">Aktiviraj ponovo</button>
            </form>
        <?php endif; ?>

        <form method="post" class="factor-delete-form" onsubmit="return confirm('Trajno obrisati ovu osobu? Ovo briše i sve povezane zapise koji se na nju odnose (pristupi, obuke, provere...). Za nekoga ko je otišao iz firme, bolje koristi Deaktiviraj.');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
            <button type="submit" class="btn-delete">Obriši</button>
        </form>
    </div>
</div>
