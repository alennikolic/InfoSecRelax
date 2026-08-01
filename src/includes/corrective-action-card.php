<?php
/**
 * src/includes/corrective-action-card.php - prikaz jedne korektivne
 * mere (Klauzula 10.2).
 *
 * Očekuje da je $correctiveAction (red iz corrective_actions, spojen
 * LEFT JOIN-ovima sa personnel, security_events i audit_findings/
 * internal_audits radi owner_name i podataka o izvoru) već postavljen
 * pre uključivanja ovog fajla - deli scope sa foreach petljom iz koje
 * se poziva.
 */

$statusLabels = [
    'otvoreno'           => 'Otvoreno',
    'sprovedeno'         => 'Sprovedeno',
    'provereno_efikasno' => 'Provereno efikasno',
    'ponovo_otvoreno'    => 'Ponovo otvoreno',
];

$statusTone = [
    'otvoreno'           => 'is-neutral',
    'sprovedeno'         => 'is-warning',
    'provereno_efikasno' => 'is-positive',
    'ponovo_otvoreno'    => 'is-danger',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title">Korektivna mera od <?= htmlspecialchars(substr((string) $correctiveAction['created_at'], 0, 10)) ?></span>
        <span class="status-badge <?= htmlspecialchars($statusTone[$correctiveAction['status']] ?? 'is-neutral') ?>">
            <?= htmlspecialchars($statusLabels[$correctiveAction['status']] ?? $correctiveAction['status']) ?>
        </span>
    </div>

    <p><?= nl2br(htmlspecialchars($correctiveAction['description'])) ?></p>

    <?php if ($correctiveAction['source_event_description'] !== null): ?>
        <p class="item-meta">
            Izvor: incident od <?= htmlspecialchars(substr((string) $correctiveAction['source_event_reported_at'], 0, 10)) ?>
        </p>
    <?php endif; ?>

    <?php if ($correctiveAction['source_finding_description'] !== null): ?>
        <p class="item-meta">
            Izvor: nalaz iz audita od <?= htmlspecialchars($correctiveAction['source_finding_audit_date']) ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($correctiveAction['root_cause_generalized'])): ?>
        <p><strong>Da li se može desiti i drugde:</strong> <?= nl2br(htmlspecialchars($correctiveAction['root_cause_generalized'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        <?php if ($correctiveAction['owner_name'] !== null): ?>
            Nosilac: <?= htmlspecialchars($correctiveAction['owner_name']) ?> ·
        <?php endif; ?>
        <?php if (!empty($correctiveAction['due_date'])): ?>
            Rok: <?= htmlspecialchars($correctiveAction['due_date']) ?>
        <?php endif; ?>
    </p>

    <?php if (!empty($correctiveAction['effectiveness_confirmed_at'])): ?>
        <p class="item-meta">Efikasnost potvrđena: <?= htmlspecialchars($correctiveAction['effectiveness_confirmed_at']) ?></p>
    <?php endif; ?>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="update_status">
        <input type="hidden" name="id" value="<?= (int) $correctiveAction['id'] ?>">

        <div class="form-row form-row-inline">
            <label for="status_<?= (int) $correctiveAction['id'] ?>">Status:</label>
            <select name="status" id="status_<?= (int) $correctiveAction['id'] ?>">
                <?php foreach ($statusLabels as $value => $statusLabel): ?>
                    <option value="<?= htmlspecialchars($value) ?>" <?= $correctiveAction['status'] === $value ? 'selected' : '' ?>>
                        <?= htmlspecialchars($statusLabel) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary">Ažuriraj status</button>
        </div>
    </form>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu korektivnu meru?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int) $correctiveAction['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
