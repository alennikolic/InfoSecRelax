<?php
/**
 * src/includes/audit-card.php - prikaz jednog internog audita i
 * njegovih nalaza (Klauzula 9.2).
 *
 * Očekuje da su $audit (red iz internal_audits) i $findings (niz
 * redova iz audit_findings za taj audit; može biti prazan niz) već
 * postavljeni pre uključivanja ovog fajla - deli scope sa foreach
 * petljom iz koje se poziva.
 */

$severityTone = [
    'nizak'   => 'is-positive',
    'srednji' => 'is-warning',
    'visok'   => 'is-danger',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title">Audit <?= htmlspecialchars($audit['audit_date']) ?></span>
        <div class="button-group">
            <span class="status-badge <?= $audit['is_external_auditor'] ? 'is-neutral' : 'is-positive' ?>">
                <?= $audit['is_external_auditor'] ? 'Spoljni auditor' : 'Interni auditor' ?>
            </span>
            <button type="button" class="btn-secondary"
                onclick='openEditAuditModal(<?= json_encode([
                    "id"                  => (int) $audit["id"],
                    "audit_date"          => $audit["audit_date"],
                    "scope"               => $audit["scope"] ?? "",
                    "auditor_name"        => $audit["auditor_name"],
                    "is_external_auditor" => (bool) $audit["is_external_auditor"],
                    "report_reference"    => $audit["report_reference"] ?? "",
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <?php if (!empty($audit['scope'])): ?>
        <p><strong>Obim:</strong> <?= nl2br(htmlspecialchars($audit['scope'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        Auditor: <?= htmlspecialchars($audit['auditor_name']) ?>
        <?php if (!empty($audit['report_reference'])): ?>
            · Izveštaj: <?= htmlspecialchars($audit['report_reference']) ?>
        <?php endif; ?>
    </p>

    <p class="item-title">Nalazi (<?= count($findings) ?>)</p>

    <?php if (empty($findings)): ?>
        <p class="empty-state">Nema zabeleženih nalaza.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($findings as $finding): ?>
                <li class="requirement-item">
                    <div class="requirement-text">
                        <?= nl2br(htmlspecialchars($finding['description'])) ?>
                    </div>
                    <div class="card-actions">
                        <span class="status-badge <?= htmlspecialchars($severityTone[$finding['severity']] ?? 'is-neutral') ?>">
                            <?= htmlspecialchars(ucfirst($finding['severity'])) ?>
                        </span>
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj nalaz?');">
                            <input type="hidden" name="action" value="delete_finding">
                            <input type="hidden" name="id" value="<?= (int) $finding['id'] ?>">
                            <button type="submit" class="btn-delete">Obriši</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <button type="button" class="btn-secondary"
        onclick='openFindingModal(<?= (int) $audit['id'] ?>, <?= json_encode('Audit ' . $audit['audit_date'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>+ Dodaj nalaz</button>

    <form method="post" class="factor-delete-form card-footer-right" onsubmit="return confirm('Obrisati ovaj audit i sve njegove nalaze?');">
        <input type="hidden" name="action" value="delete_audit">
        <input type="hidden" name="id" value="<?= (int) $audit['id'] ?>">
        <button type="submit" class="btn-delete">Obriši audit</button>
    </form>
</div>
