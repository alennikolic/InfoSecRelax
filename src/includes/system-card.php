<?php
/**
 * src/includes/system-card.php - prikaz jednog sistema i njegovih
 * pristupa (Klauzula 8.1, A.8.2-8.5).
 *
 * Očekuje da su $system (red iz systems, spojen LEFT JOIN-om sa
 * personnel radi owner_name) i $accessGrants (niz redova iz
 * access_grants za taj sistem, spojenih JOIN-om sa personnel radi
 * person_name i granted_by_name; može biti prazan niz) već postavljeni
 * pre uključivanja ovog fajla - deli scope sa foreach petljom iz koje
 * se poziva. Takođe očekuje $activePersonnelOptions, postavljen jednom
 * u sistemi-pristup.php pre foreach petlje.
 */

$hostingLabels = [
    'cloud'    => 'Cloud',
    'lokalno'  => 'Lokalno',
    'hibridno' => 'Hibridno',
];

$criticalityTone = [
    'nizak'   => 'is-positive',
    'srednji' => 'is-warning',
    'visok'   => 'is-danger',
];

$accessLevelLabels = [
    'standardni'   => 'Standardni',
    'privilegovan' => 'Privilegovan',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($system['name']) ?></span>
        <span class="status-badge <?= htmlspecialchars($criticalityTone[$system['criticality']] ?? 'is-neutral') ?>">
            Kritičnost: <?= htmlspecialchars(ucfirst($system['criticality'])) ?>
        </span>
    </div>

    <?php if (!empty($system['description'])): ?>
        <p><?= nl2br(htmlspecialchars($system['description'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        Hosting: <?= htmlspecialchars($hostingLabels[$system['hosting_type']] ?? $system['hosting_type']) ?>
        <?php if ($system['owner_name'] !== null): ?>
            · Vlasnik: <?= htmlspecialchars($system['owner_name']) ?>
        <?php endif; ?>
    </p>

    <p class="item-title">Pristupi (<?= count($accessGrants) ?>)</p>

    <?php if (empty($accessGrants)): ?>
        <p class="empty-state">Još uvek nema evidentiranih pristupa.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($accessGrants as $access): ?>
                <li class="requirement-item">
                    <div class="requirement-text">
                        <strong><?= htmlspecialchars($access['person_name']) ?></strong>
                        — <?= htmlspecialchars($accessLevelLabels[$access['access_level']] ?? $access['access_level']) ?>
                        <?php if ($access['status'] === 'ukinut'): ?>
                            <span class="status-badge is-neutral">Ukinut</span>
                        <?php else: ?>
                            <span class="status-badge is-positive">Aktivan</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($access['scope_note'])): ?>
                        <p class="item-meta"><?= htmlspecialchars($access['scope_note']) ?></p>
                    <?php endif; ?>
                    <p class="item-meta">
                        Dodeljeno: <?= htmlspecialchars(substr((string) $access['granted_at'], 0, 10)) ?>
                        <?php if ($access['granted_by_name'] !== null): ?>
                            (odobrio: <?= htmlspecialchars($access['granted_by_name']) ?>)
                        <?php endif; ?>
                        <?php if (!empty($access['revoked_at'])): ?>
                            · Ukinuto: <?= htmlspecialchars(substr((string) $access['revoked_at'], 0, 10)) ?>
                        <?php endif; ?>
                    </p>
                    <div class="card-actions">
                        <?php if ($access['status'] !== 'ukinut'): ?>
                            <form method="post" class="factor-delete-form" onsubmit="return confirm('Ukinuti ovaj pristup?');">
                                <input type="hidden" name="action" value="revoke_access">
                                <input type="hidden" name="id" value="<?= (int) $access['id'] ?>">
                                <button type="submit" class="btn-secondary">Ukini pristup</button>
                            </form>
                        <?php endif; ?>
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Trajno obrisati ovaj zapis o pristupu?');">
                            <input type="hidden" name="action" value="delete_access">
                            <input type="hidden" name="id" value="<?= (int) $access['id'] ?>">
                            <button type="submit" class="btn-delete">Obriši</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="add_access">
        <input type="hidden" name="system_id" value="<?= (int) $system['id'] ?>">

        <div class="form-row">
            <label for="personnel_id_<?= (int) $system['id'] ?>">Osoba</label>
            <select name="personnel_id" id="personnel_id_<?= (int) $system['id'] ?>" required>
                <option value="">Izaberite...</option>
                <?php foreach ($activePersonnelOptions as $option): ?>
                    <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label for="access_level_<?= (int) $system['id'] ?>">Nivo pristupa</label>
            <select name="access_level" id="access_level_<?= (int) $system['id'] ?>">
                <option value="standardni" selected>Standardni</option>
                <option value="privilegovan">Privilegovan</option>
            </select>
        </div>

        <div class="form-row">
            <label for="scope_note_<?= (int) $system['id'] ?>">Napomena o obimu (opciono)</label>
            <input type="text" name="scope_note" id="scope_note_<?= (int) $system['id'] ?>"
                placeholder="npr. Samo dodeljeni portfolio klijenata">
        </div>

        <div class="form-row">
            <label for="granted_by_<?= (int) $system['id'] ?>">Odobrio (opciono)</label>
            <select name="granted_by" id="granted_by_<?= (int) $system['id'] ?>">
                <option value="">Nije dodeljen</option>
                <?php foreach ($activePersonnelOptions as $option): ?>
                    <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-secondary">Dodaj pristup</button>
    </form>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj sistem i sve zapise o pristupu?');">
        <input type="hidden" name="action" value="delete_system">
        <input type="hidden" name="id" value="<?= (int) $system['id'] ?>">
        <button type="submit" class="btn-delete">Obriši sistem</button>
    </form>
</div>
