<?php
/**
 * src/modules/pregled-menadzmenta.php
 *
 * Klauzula 9.3: Pregled menadžmenta.
 *
 * management_reviews ima sedam tekstualnih polja koja doslovno prate
 * sedam obaveznih ulaza iz klauzule 9.3(a)-(g) - sva opciona u bazi
 * (niko ne mora popuniti baš sve odjednom), ali svako nosi svoju
 * oznaku u formi radi sledljivosti do standarda. Modal za
 * dodavanje/uređivanje je zato širi (modal-box-wide) nego uobičajeno.
 *
 * management_review_actions je roditelj-dete kao svuda do sada -
 * "Dodaj radnju" je poseban modal otvoren dugmetom u kartici, dok
 * status radnje ostaje ugrađena forma (jednostavan dropdown + dugme) -
 * isti obrazac kao kod ciljeva i rizika.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'pregled-menadzmenta';

$errors = [];

$validActionStatuses = ['otvoreno', 'u_toku', 'zavrseno'];

// --- Dodavanje pregleda menadžmenta ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_review') {
    $reviewDate               = trim($_POST['review_date'] ?? '');
    $attendees                = trim($_POST['attendees'] ?? '');
    $previousActionsStatus    = trim($_POST['previous_actions_status'] ?? '');
    $contextChanges           = trim($_POST['context_changes'] ?? '');
    $interestedPartyChanges   = trim($_POST['interested_party_changes'] ?? '');
    $performanceSummary       = trim($_POST['performance_summary'] ?? '');
    $interestedPartyFeedback  = trim($_POST['interested_party_feedback'] ?? '');
    $riskTreatmentStatus      = trim($_POST['risk_treatment_status'] ?? '');
    $improvementOpportunities = trim($_POST['improvement_opportunities'] ?? '');

    if ($reviewDate === '') {
        $errors[] = 'Datum pregleda je obavezan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO management_reviews
                (organization_id, review_date, attendees, previous_actions_status, context_changes,
                 interested_party_changes, performance_summary, interested_party_feedback,
                 risk_treatment_status, improvement_opportunities)
             VALUES
                (:org_id, :review_date, :attendees, :previous_actions_status, :context_changes,
                 :interested_party_changes, :performance_summary, :interested_party_feedback,
                 :risk_treatment_status, :improvement_opportunities)'
        );
        $stmt->execute([
            'org_id'                    => $organizationId,
            'review_date'               => $reviewDate,
            'attendees'                 => $attendees !== '' ? $attendees : null,
            'previous_actions_status'   => $previousActionsStatus !== '' ? $previousActionsStatus : null,
            'context_changes'           => $contextChanges !== '' ? $contextChanges : null,
            'interested_party_changes'  => $interestedPartyChanges !== '' ? $interestedPartyChanges : null,
            'performance_summary'       => $performanceSummary !== '' ? $performanceSummary : null,
            'interested_party_feedback' => $interestedPartyFeedback !== '' ? $interestedPartyFeedback : null,
            'risk_treatment_status'     => $riskTreatmentStatus !== '' ? $riskTreatmentStatus : null,
            'improvement_opportunities' => $improvementOpportunities !== '' ? $improvementOpportunities : null,
        ]);

        header('Location: ?page=pregled-menadzmenta');
        exit;
    }
}

// --- Ažuriranje postojećeg pregleda ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_review') {
    $id                        = (int) ($_POST['id'] ?? 0);
    $reviewDate                = trim($_POST['review_date'] ?? '');
    $attendees                 = trim($_POST['attendees'] ?? '');
    $previousActionsStatus     = trim($_POST['previous_actions_status'] ?? '');
    $contextChanges            = trim($_POST['context_changes'] ?? '');
    $interestedPartyChanges    = trim($_POST['interested_party_changes'] ?? '');
    $performanceSummary        = trim($_POST['performance_summary'] ?? '');
    $interestedPartyFeedback   = trim($_POST['interested_party_feedback'] ?? '');
    $riskTreatmentStatus       = trim($_POST['risk_treatment_status'] ?? '');
    $improvementOpportunities  = trim($_POST['improvement_opportunities'] ?? '');

    if ($reviewDate === '') {
        $errors[] = 'Datum pregleda je obavezan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE management_reviews
             SET review_date = :review_date, attendees = :attendees,
                 previous_actions_status = :previous_actions_status, context_changes = :context_changes,
                 interested_party_changes = :interested_party_changes, performance_summary = :performance_summary,
                 interested_party_feedback = :interested_party_feedback, risk_treatment_status = :risk_treatment_status,
                 improvement_opportunities = :improvement_opportunities
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'review_date'               => $reviewDate,
            'attendees'                 => $attendees !== '' ? $attendees : null,
            'previous_actions_status'   => $previousActionsStatus !== '' ? $previousActionsStatus : null,
            'context_changes'           => $contextChanges !== '' ? $contextChanges : null,
            'interested_party_changes'  => $interestedPartyChanges !== '' ? $interestedPartyChanges : null,
            'performance_summary'       => $performanceSummary !== '' ? $performanceSummary : null,
            'interested_party_feedback' => $interestedPartyFeedback !== '' ? $interestedPartyFeedback : null,
            'risk_treatment_status'     => $riskTreatmentStatus !== '' ? $riskTreatmentStatus : null,
            'improvement_opportunities' => $improvementOpportunities !== '' ? $improvementOpportunities : null,
            'id'                        => $id,
            'org_id'                    => $organizationId,
        ]);

        header('Location: ?page=pregled-menadzmenta');
        exit;
    }
}

// --- Brisanje pregleda (radnje se brišu kaskadno preko FK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_review') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM management_reviews WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=pregled-menadzmenta');
    exit;
}

// --- Dodavanje radnje iz pregleda ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_action') {
    $managementReviewId = (int) ($_POST['management_review_id'] ?? 0);
    $actionDescription   = trim($_POST['action_description'] ?? '');
    $ownerId             = trim($_POST['owner_id'] ?? '');
    $dueDate             = trim($_POST['due_date'] ?? '');

    $reviewCheck = $pdo->prepare('SELECT id FROM management_reviews WHERE id = :id AND organization_id = :org_id');
    $reviewCheck->execute(['id' => $managementReviewId, 'org_id' => $organizationId]);

    if ($reviewCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznat pregled.';
    }
    if ($actionDescription === '') {
        $errors[] = 'Opis radnje je obavezan.';
    }

    $ownerIdValue = null;
    if ($ownerId !== '') {
        $ownerIdValue = (int) $ownerId;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $ownerIdValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani nosilac nije pronađen.';
            $ownerIdValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO management_review_actions (management_review_id, action_description, owner_id, due_date)
             VALUES (:management_review_id, :action_description, :owner_id, :due_date)'
        );
        $stmt->execute([
            'management_review_id' => $managementReviewId,
            'action_description'   => $actionDescription,
            'owner_id'             => $ownerIdValue,
            'due_date'             => $dueDate !== '' ? $dueDate : null,
        ]);

        header('Location: ?page=pregled-menadzmenta');
        exit;
    }
}

// --- Promena statusa radnje ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_action_status') {
    $id        = (int) ($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if (in_array($newStatus, $validActionStatuses, true)) {
        $stmt = $pdo->prepare(
            'UPDATE management_review_actions a
             INNER JOIN management_reviews r ON r.id = a.management_review_id
             SET a.status = :status
             WHERE a.id = :id AND r.organization_id = :org_id'
        );
        $stmt->execute(['status' => $newStatus, 'id' => $id, 'org_id' => $organizationId]);
    }

    header('Location: ?page=pregled-menadzmenta');
    exit;
}

// --- Brisanje radnje ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_action') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE a FROM management_review_actions a
         INNER JOIN management_reviews r ON r.id = a.management_review_id
         WHERE a.id = :id AND r.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=pregled-menadzmenta');
    exit;
}

// --- Aktivne osobe za dropdown nosioca radnje ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje pregleda (najnoviji prvo) ---
$reviewsStmt = $pdo->prepare(
    'SELECT * FROM management_reviews WHERE organization_id = :org_id ORDER BY review_date DESC'
);
$reviewsStmt->execute(['org_id' => $organizationId]);
$allReviews = $reviewsStmt->fetchAll();

// --- Radnje za sve preglede ove organizacije, grupisane po management_review_id ---
$actionsStmt = $pdo->prepare(
    'SELECT a.*, p.full_name AS owner_name
     FROM management_review_actions a
     INNER JOIN management_reviews r ON r.id = a.management_review_id
     LEFT JOIN personnel p ON p.id = a.owner_id
     WHERE r.organization_id = :org_id
     ORDER BY a.due_date IS NULL, a.due_date'
);
$actionsStmt->execute(['org_id' => $organizationId]);

$actionsByReview = [];
foreach ($actionsStmt->fetchAll() as $action) {
    $actionsByReview[$action['management_review_id']][] = $action;
}

// --- Učitavanje sadržaja pomoći za ovu stranicu ---
$helpContent = getHelpContent($pdo, $pageSlug);
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="toolbar">
    <button type="button" class="btn-primary" onclick="openAddReviewModal()">+ Dodaj pregled</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allReviews)): ?>
    <p class="empty-state">Još uvek nema unetih pregleda menadžmenta.</p>
<?php else: ?>
    <?php foreach ($allReviews as $review): ?>
        <?php $actions = $actionsByReview[$review['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/review-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="review-modal-overlay" onclick="closeReviewModal()">
    <div class="modal-box modal-box-wide" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="review-modal-title">Dodaj pregled</span>
            <button type="button" class="modal-close" onclick="closeReviewModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="review-modal-form">
            <input type="hidden" name="action" id="review-modal-action" value="add_review">
            <input type="hidden" name="id" id="review-modal-id" value="">

            <div class="form-row">
                <label for="modal_review_date">Datum pregleda</label>
                <input type="date" name="review_date" id="modal_review_date" required>
            </div>

            <div class="form-row">
                <label for="modal_attendees">Prisutni (opciono)</label>
                <textarea name="attendees" id="modal_attendees" rows="2"
                    placeholder="npr. Direktor, Koordinator ISMS-a, Administrator sistema"></textarea>
            </div>

            <div class="form-row">
                <label for="modal_previous_actions_status">9.3(a) Status radnji iz prethodnih pregleda (opciono)</label>
                <textarea name="previous_actions_status" id="modal_previous_actions_status" rows="2"></textarea>
            </div>

            <div class="form-row">
                <label for="modal_context_changes">9.3(b) Promene konteksta organizacije (opciono)</label>
                <textarea name="context_changes" id="modal_context_changes" rows="2"></textarea>
            </div>

            <div class="form-row">
                <label for="modal_interested_party_changes">9.3(c) Promene potreba zainteresovanih strana (opciono)</label>
                <textarea name="interested_party_changes" id="modal_interested_party_changes" rows="2"></textarea>
            </div>

            <div class="form-row">
                <label for="modal_performance_summary">9.3(d) Učinak ISMS-a (neusaglašenosti, pokazatelji, audit, ciljevi) (opciono)</label>
                <textarea name="performance_summary" id="modal_performance_summary" rows="3"></textarea>
            </div>

            <div class="form-row">
                <label for="modal_interested_party_feedback">9.3(e) Povratne informacije zainteresovanih strana (opciono)</label>
                <textarea name="interested_party_feedback" id="modal_interested_party_feedback" rows="2"></textarea>
            </div>

            <div class="form-row">
                <label for="modal_risk_treatment_status">9.3(f) Status procene i tretmana rizika (opciono)</label>
                <textarea name="risk_treatment_status" id="modal_risk_treatment_status" rows="2"></textarea>
            </div>

            <div class="form-row">
                <label for="modal_improvement_opportunities">9.3(g) Prilike za unapređenje (opciono)</label>
                <textarea name="improvement_opportunities" id="modal_improvement_opportunities" rows="2"></textarea>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromReviewModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeReviewModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="action-modal-overlay" onclick="closeActionModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="action-modal-title">Dodaj radnju</span>
            <button type="button" class="modal-close" onclick="closeActionModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="add_action">
            <input type="hidden" name="management_review_id" id="action-modal-review-id" value="">

            <div class="form-row">
                <label for="modal_action_description">Opis radnje</label>
                <textarea name="action_description" id="modal_action_description" rows="2" required
                    placeholder="npr. Ažurirati registar rizika sa novim pretnjama identifikovanim ovog kvartala."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_action_owner_id">Nosilac (opciono)</label>
                <select name="owner_id" id="modal_action_owner_id">
                    <option value="">Nije dodeljen</option>
                    <?php foreach ($activePersonnelOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_action_due_date">Rok (opciono)</label>
                <input type="date" name="due_date" id="modal_action_due_date">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromActionModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeActionModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddReviewModal() {
    document.getElementById('review-modal-title').textContent = 'Dodaj pregled';
    document.getElementById('review-modal-action').value = 'add_review';
    document.getElementById('review-modal-id').value = '';
    document.getElementById('modal_review_date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('modal_attendees').value = '';
    document.getElementById('modal_previous_actions_status').value = '';
    document.getElementById('modal_context_changes').value = '';
    document.getElementById('modal_interested_party_changes').value = '';
    document.getElementById('modal_performance_summary').value = '';
    document.getElementById('modal_interested_party_feedback').value = '';
    document.getElementById('modal_risk_treatment_status').value = '';
    document.getElementById('modal_improvement_opportunities').value = '';
    document.getElementById('review-modal-overlay').classList.add('is-open');
}

function openEditReviewModal(review) {
    document.getElementById('review-modal-title').textContent = 'Uredi pregled';
    document.getElementById('review-modal-action').value = 'update_review';
    document.getElementById('review-modal-id').value = review.id;
    document.getElementById('modal_review_date').value = review.review_date;
    document.getElementById('modal_attendees').value = review.attendees;
    document.getElementById('modal_previous_actions_status').value = review.previous_actions_status;
    document.getElementById('modal_context_changes').value = review.context_changes;
    document.getElementById('modal_interested_party_changes').value = review.interested_party_changes;
    document.getElementById('modal_performance_summary').value = review.performance_summary;
    document.getElementById('modal_interested_party_feedback').value = review.interested_party_feedback;
    document.getElementById('modal_risk_treatment_status').value = review.risk_treatment_status;
    document.getElementById('modal_improvement_opportunities').value = review.improvement_opportunities;
    document.getElementById('review-modal-overlay').classList.add('is-open');
}

function closeReviewModal() {
    document.getElementById('review-modal-overlay').classList.remove('is-open');
}

function openHelpFromReviewModal() {
    closeReviewModal();
    openHelpModal();
}

function openActionModal(reviewId, reviewLabel) {
    document.getElementById('action-modal-title').textContent = 'Dodaj radnju — ' + reviewLabel;
    document.getElementById('action-modal-review-id').value = reviewId;
    document.getElementById('modal_action_description').value = '';
    document.getElementById('modal_action_owner_id').value = '';
    document.getElementById('modal_action_due_date').value = '';
    document.getElementById('action-modal-overlay').classList.add('is-open');
}

function closeActionModal() {
    document.getElementById('action-modal-overlay').classList.remove('is-open');
}

function openHelpFromActionModal() {
    closeActionModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeReviewModal();
        closeActionModal();
    }
});
</script>
