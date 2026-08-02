<?php
/**
 * src/includes/policy-card.php - prikaz jedne politike, njene istorije
 * verzija i potvrda zaposlenih (Klauzula 5.2 / A.5.1).
 *
 * Očekuje da su $policy (red iz policies, spojen JOIN-om sa documents i
 * LEFT JOIN-ovima sa personnel radi owner_name/approved_by_name),
 * $versions (niz iz document_versions za policy['document_id']) i
 * $acknowledgments (niz iz policy_acknowledgments za taj policy,
 * spojenih JOIN-om sa personnel radi person_name) već postavljeni pre
 * uključivanja ovog fajla - deli scope sa foreach petljom iz koje se
 * poziva. Takođe očekuje $activePersonnelOptions, postavljen jednom u
 * politike.php pre foreach petlje.
 */

$policyTypeLabels = ['opsta' => 'Opšta', 'tematska' => 'Tematska'];

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

$isReviewOverdue = !empty($policy['next_review_due']) && $policy['next_review_due'] < date('Y-m-d');
$acknowledgedCount = count($acknowledgments);
$activeCount = count($activePersonnelOptions);
$alreadyAcknowledgedIds = array_map('intval', array_column($acknowledgments, 'personnel_id'));
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($policy['title']) ?></span>
        <div class="button-group">
            <span class="factor-category"><?= htmlspecialchars($policyTypeLabels[$policy['policy_type']] ?? $policy['policy_type']) ?></span>
            <button type="button" class="btn-secondary"
                onclick='openEditPolicyModal(<?= json_encode([
                    "id"                      => (int) $policy["id"],
                    "title"                   => $policy["title"],
                    "policy_type"             => $policy["policy_type"],
                    "topic"                   => $policy["topic"] ?? "",
                    "acknowledgment_required" => (bool) $policy["acknowledgment_required"],
                    "classification"          => $policy["classification"],
                    "owner_id"                => $policy["owner_id"] ?? "",
                    "approved_by"             => $policy["approved_by"] ?? "",
                    "approved_at"             => $policy["approved_at"] ?? "",
                    "next_review_due"         => $policy["next_review_due"] ?? "",
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <?php if (!empty($policy['topic'])): ?>
        <p class="item-meta">Tema: <?= htmlspecialchars($policy['topic']) ?></p>
    <?php endif; ?>

    <p>
        <span class="status-badge <?= htmlspecialchars($classificationTone[$policy['classification']] ?? 'is-neutral') ?>">
            <?= htmlspecialchars($classificationLabels[$policy['classification']] ?? $policy['classification']) ?>
        </span>
        <?php if ($isReviewOverdue): ?>
            <span class="status-badge is-danger">Pregled dospeo</span>
        <?php endif; ?>
        <?php if ($policy['acknowledgment_required']): ?>
            <span class="status-badge is-warning">Potvrda obavezna</span>
        <?php endif; ?>
    </p>

    <p class="item-meta">
        Verzija: <?= htmlspecialchars($policy['current_version']) ?>
        <?php if ($policy['owner_name'] !== null): ?>
            · Vlasnik: <?= htmlspecialchars($policy['owner_name']) ?>
        <?php endif; ?>
        <?php if ($policy['approved_by_name'] !== null): ?>
            · Odobrio: <?= htmlspecialchars($policy['approved_by_name']) ?>
            <?php if (!empty($policy['approved_at'])): ?>
                (<?= htmlspecialchars($policy['approved_at']) ?>)
            <?php endif; ?>
        <?php endif; ?>
    </p>

    <?php if (!empty($policy['next_review_due'])): ?>
        <p class="item-meta">Sledeći pregled: <?= htmlspecialchars($policy['next_review_due']) ?></p>
    <?php endif; ?>

    <p class="item-title">Istorija verzija (<?= count($versions) ?>)</p>
    <?php if (empty($versions)): ?>
        <p class="empty-state">Nema zabeleženih verzija.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($versions as $version): ?>
                <li class="requirement-item">
                    <div class="requirement-text">
                        <strong>Verzija <?= htmlspecialchars($version['version_number']) ?></strong>
                        <?php if (!empty($version['change_summary'])): ?>
                            — <?= nl2br(htmlspecialchars($version['change_summary'])) ?>
                        <?php endif; ?>
                    </div>
                    <p class="item-meta">
                        <?php if ($version['changed_by_name'] !== null): ?>
                            Izmenio: <?= htmlspecialchars($version['changed_by_name']) ?> ·
                        <?php endif; ?>
                        <?= htmlspecialchars(substr((string) $version['created_at'], 0, 10)) ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="add_version">
        <input type="hidden" name="policy_id" value="<?= (int) $policy['id'] ?>">

        <div class="form-row">
            <label for="version_number_<?= (int) $policy['id'] ?>">Nova verzija</label>
            <input type="text" name="version_number" id="version_number_<?= (int) $policy['id'] ?>" required
                placeholder="npr. 1.1">
        </div>

        <div class="form-row">
            <label for="change_summary_<?= (int) $policy['id'] ?>">Šta je izmenjeno (opciono)</label>
            <textarea name="change_summary" id="change_summary_<?= (int) $policy['id'] ?>" rows="2"></textarea>
        </div>

        <div class="form-row">
            <label for="changed_by_<?= (int) $policy['id'] ?>">Izmenio (opciono)</label>
            <select name="changed_by" id="changed_by_<?= (int) $policy['id'] ?>">
                <option value="">Nije dodeljen</option>
                <?php foreach ($activePersonnelOptions as $option): ?>
                    <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-secondary">Sačuvaj novu verziju</button>
    </form>

    <p class="item-title">Potvrde zaposlenih (<?= $acknowledgedCount ?> od <?= $activeCount ?> aktivnih)</p>
    <?php if (empty($acknowledgments)): ?>
        <p class="empty-state">Još niko nije potvrdio.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($acknowledgments as $acknowledgment): ?>
                <li class="requirement-item">
                    <div class="requirement-text">
                        <?= htmlspecialchars($acknowledgment['person_name']) ?>
                        <span class="item-meta">— <?= htmlspecialchars(substr((string) $acknowledgment['acknowledged_at'], 0, 10)) ?></span>
                    </div>
                    <div class="card-actions">
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Ukloniti ovu potvrdu?');">
                            <input type="hidden" name="action" value="delete_acknowledgment">
                            <input type="hidden" name="id" value="<?= (int) $acknowledgment['id'] ?>">
                            <button type="submit" class="btn-delete">Ukloni</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="add_acknowledgment">
        <input type="hidden" name="policy_id" value="<?= (int) $policy['id'] ?>">

        <div class="form-row form-row-inline">
            <label for="personnel_id_<?= (int) $policy['id'] ?>">Dodaj potvrdu:</label>
            <select name="personnel_id" id="personnel_id_<?= (int) $policy['id'] ?>">
                <option value="">Izaberite...</option>
                <?php foreach ($activePersonnelOptions as $option): ?>
                    <?php if (!in_array((int) $option['id'], $alreadyAcknowledgedIds, true)): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary">Dodaj</button>
        </div>
    </form>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu politiku, njen dokument, sve verzije i potvrde?');">
        <input type="hidden" name="action" value="delete_policy">
        <input type="hidden" name="id" value="<?= (int) $policy['id'] ?>">
        <button type="submit" class="btn-delete">Obriši politiku</button>
    </form>
</div>
