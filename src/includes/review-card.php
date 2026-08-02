<?php
/**
 * src/includes/review-card.php - prikaz jednog pregleda menadžmenta i
 * njegovih radnji (Klauzula 9.3).
 *
 * Očekuje da su $review (red iz management_reviews) i $actions (niz
 * redova iz management_review_actions za taj pregled, spojenih LEFT
 * JOIN-om sa personnel radi owner_name; može biti prazan niz) već
 * postavljeni pre uključivanja ovog fajla - deli scope sa foreach
 * petljom iz koje se poziva.
 *
 * "+ Dodaj radnju" ne otvara više ugrađenu formu u kartici - otvara
 * zajednički modal na nivou stranice (modules/pregled-menadzmenta.php).
 * Status pojedinačne radnje ostaje ugrađena forma (jednostavan
 * dropdown + dugme) - isti princip kao status kod ciljeva i rizika.
 *
 * Prikazuju se samo popunjena polja od sedam 9.3(a)-(g) ulaza, da se
 * prazan pregled ne prikaže kao gomila praznih naslova.
 */

$statusLabels = [
    'otvoreno' => 'Otvoreno',
    'u_toku'   => 'U toku',
    'zavrseno' => 'Završeno',
];

$reviewFields = [
    'previous_actions_status'   => '9.3(a) Status radnji iz prethodnih pregleda',
    'context_changes'           => '9.3(b) Promene konteksta organizacije',
    'interested_party_changes'  => '9.3(c) Promene potreba zainteresovanih strana',
    'performance_summary'       => '9.3(d) Učinak ISMS-a',
    'interested_party_feedback' => '9.3(e) Povratne informacije zainteresovanih strana',
    'risk_treatment_status'     => '9.3(f) Status procene i tretmana rizika',
    'improvement_opportunities' => '9.3(g) Prilike za unapređenje',
];
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title">Pregled <?= htmlspecialchars($review['review_date']) ?></span>
        <button type="button" class="btn-secondary"
            onclick='openEditReviewModal(<?= json_encode([
                "id"                        => (int) $review["id"],
                "review_date"               => $review["review_date"],
                "attendees"                 => $review["attendees"] ?? "",
                "previous_actions_status"   => $review["previous_actions_status"] ?? "",
                "context_changes"           => $review["context_changes"] ?? "",
                "interested_party_changes"  => $review["interested_party_changes"] ?? "",
                "performance_summary"       => $review["performance_summary"] ?? "",
                "interested_party_feedback" => $review["interested_party_feedback"] ?? "",
                "risk_treatment_status"     => $review["risk_treatment_status"] ?? "",
                "improvement_opportunities" => $review["improvement_opportunities"] ?? "",
            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
    </div>

    <?php if (!empty($review['attendees'])): ?>
        <p class="item-meta">Prisutni: <?= htmlspecialchars($review['attendees']) ?></p>
    <?php endif; ?>

    <?php foreach ($reviewFields as $field => $fieldLabel): ?>
        <?php if (!empty($review[$field])): ?>
            <p><strong><?= htmlspecialchars($fieldLabel) ?>:</strong> <?= nl2br(htmlspecialchars($review[$field])) ?></p>
        <?php endif; ?>
    <?php endforeach; ?>

    <p class="item-title">Radnje (<?= count($actions) ?>)</p>

    <?php if (empty($actions)): ?>
        <p class="empty-state">Još uvek nema unetih radnji.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($actions as $reviewAction): ?>
                <li class="requirement-item">
                    <div class="requirement-text"><?= nl2br(htmlspecialchars($reviewAction['action_description'])) ?></div>
                    <p class="item-meta">
                        <?php if ($reviewAction['owner_name'] !== null): ?>
                            Nosilac: <?= htmlspecialchars($reviewAction['owner_name']) ?> ·
                        <?php endif; ?>
                        <?php if (!empty($reviewAction['due_date'])): ?>
                            Rok: <?= htmlspecialchars($reviewAction['due_date']) ?>
                        <?php endif; ?>
                    </p>
                    <form method="post" class="subform">
                        <input type="hidden" name="action" value="update_action_status">
                        <input type="hidden" name="id" value="<?= (int) $reviewAction['id'] ?>">
                        <div class="form-row form-row-inline">
                            <label for="status_<?= (int) $reviewAction['id'] ?>">Status:</label>
                            <select name="status" id="status_<?= (int) $reviewAction['id'] ?>">
                                <?php foreach ($statusLabels as $value => $statusLabel): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= $reviewAction['status'] === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($statusLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-secondary">Ažuriraj</button>
                        </div>
                    </form>
                    <div class="card-actions">
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu radnju?');">
                            <input type="hidden" name="action" value="delete_action">
                            <input type="hidden" name="id" value="<?= (int) $reviewAction['id'] ?>">
                            <button type="submit" class="btn-delete">Obriši</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <button type="button" class="btn-secondary"
        onclick='openActionModal(<?= (int) $review['id'] ?>, <?= json_encode('Pregled ' . $review['review_date'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>+ Dodaj radnju</button>

    <form method="post" class="factor-delete-form card-footer-right" onsubmit="return confirm('Obrisati ovaj pregled i sve njegove radnje?');">
        <input type="hidden" name="action" value="delete_review">
        <input type="hidden" name="id" value="<?= (int) $review['id'] ?>">
        <button type="submit" class="btn-delete">Obriši pregled</button>
    </form>
</div>
