<?php
/**
 * src/includes/competence-card.php - prikaz jednog zapisa o
 * kompetentnosti (Klauzula 7.2).
 *
 * Očekuje da je $record (red iz competence_records, spojen JOIN-om sa
 * personnel radi person_name i LEFT JOIN-om sa roles_responsibilities
 * radi role_name) već postavljen pre uključivanja ovog fajla - deli
 * scope sa foreach petljom iz koje se poziva.
 */
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($record['person_name']) ?></span>
        <div class="button-group">
            <?php if ($record['role_name'] !== null): ?>
                <span class="factor-category"><?= htmlspecialchars($record['role_name']) ?></span>
            <?php endif; ?>
            <button type="button" class="btn-secondary"
                onclick='openEditCompetenceModal(<?= json_encode([
                    "id"                  => (int) $record["id"],
                    "personnel_id"        => (int) $record["personnel_id"],
                    "role_id"             => $record["role_id"] ?? "",
                    "required_competence" => $record["required_competence"],
                    "gap_identified"      => $record["gap_identified"] ?? "",
                    "action_taken"        => $record["action_taken"] ?? "",
                    "evaluated_effective" => $record["evaluated_effective"] ?? "",
                    "evaluated_at"        => $record["evaluated_at"] ?? "",
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <p><strong>Potrebna kompetencija:</strong> <?= nl2br(htmlspecialchars($record['required_competence'])) ?></p>

    <?php if (!empty($record['gap_identified'])): ?>
        <p><strong>Uočen nedostatak:</strong> <?= nl2br(htmlspecialchars($record['gap_identified'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($record['action_taken'])): ?>
        <p><strong>Preduzeta radnja:</strong> <?= nl2br(htmlspecialchars($record['action_taken'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        <?php if ($record['evaluated_effective'] === null): ?>
            Efikasnost: nije ocenjeno
        <?php elseif ($record['evaluated_effective']): ?>
            <span class="status-badge is-positive">Efikasno</span>
        <?php else: ?>
            <span class="status-badge is-danger">Nije efikasno</span>
        <?php endif; ?>
        <?php if (!empty($record['evaluated_at'])): ?>
            · Ocenjeno: <?= htmlspecialchars($record['evaluated_at']) ?>
        <?php endif; ?>
    </p>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj zapis?');">
        <input type="hidden" name="action" value="delete_competence">
        <input type="hidden" name="id" value="<?= (int) $record['id'] ?>">
        <button type="submit" class="btn-delete">Obriši</button>
    </form>
</div>
