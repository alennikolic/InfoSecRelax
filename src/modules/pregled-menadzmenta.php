<?php
/**
 * src/modules/pregled-menadzmenta.php
 *
 * Klauzula 9.3: Pregled menadžmenta.
 *
 * management_reviews ima sedam tekstualnih polja koja doslovno prate
 * sedam obaveznih ulaza iz klauzule 9.3(a)-(g) - sva opciona u bazi
 * (niko ne mora popuniti baš sve odjednom), ali svako nosi svoju
 * oznaku u formi radi sledljivosti do standarda.
 *
 * management_review_actions je roditelj-dete kao svuda do sada, sa
 * statusom (otvoreno/u_toku/zavrseno) koji se menja preko inline
 * forme - isti obrazac kao kod ciljeva i rizika.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

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
?>

<p class="module-intro">
    Klauzula 9.3 traži da menadžment redovno pregleda ISMS na osnovu sedam
    obaveznih ulaza (9.3 a-g), i da iz toga proizađu konkretne radnje.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="factor-form">
    <input type="hidden" name="action" value="add_review">

    <div class="form-row">
        <label for="review_date">Datum pregleda</label>
        <input type="date" name="review_date" id="review_date" required value="<?= date('Y-m-d') ?>">
    </div>

    <div class="form-row">
        <label for="attendees">Prisutni (opciono)</label>
        <textarea name="attendees" id="attendees" rows="2"
            placeholder="npr. Direktor, Koordinator ISMS-a, Administrator sistema"></textarea>
    </div>

    <div class="form-row">
        <label for="previous_actions_status">9.3(a) Status radnji iz prethodnih pregleda (opciono)</label>
        <textarea name="previous_actions_status" id="previous_actions_status" rows="2"></textarea>
    </div>

    <div class="form-row">
        <label for="context_changes">9.3(b) Promene konteksta organizacije (opciono)</label>
        <textarea name="context_changes" id="context_changes" rows="2"></textarea>
    </div>

    <div class="form-row">
        <label for="interested_party_changes">9.3(c) Promene potreba zainteresovanih strana (opciono)</label>
        <textarea name="interested_party_changes" id="interested_party_changes" rows="2"></textarea>
    </div>

    <div class="form-row">
        <label for="performance_summary">9.3(d) Učinak ISMS-a (neusaglašenosti, pokazatelji, audit, ciljevi) (opciono)</label>
        <textarea name="performance_summary" id="performance_summary" rows="3"></textarea>
    </div>

    <div class="form-row">
        <label for="interested_party_feedback">9.3(e) Povratne informacije zainteresovanih strana (opciono)</label>
        <textarea name="interested_party_feedback" id="interested_party_feedback" rows="2"></textarea>
    </div>

    <div class="form-row">
        <label for="risk_treatment_status">9.3(f) Status procene i tretmana rizika (opciono)</label>
        <textarea name="risk_treatment_status" id="risk_treatment_status" rows="2"></textarea>
    </div>

    <div class="form-row">
        <label for="improvement_opportunities">9.3(g) Prilike za unapređenje (opciono)</label>
        <textarea name="improvement_opportunities" id="improvement_opportunities" rows="2"></textarea>
    </div>

    <button type="submit" class="btn-primary">Sačuvaj pregled</button>
</form>

<?php if (empty($allReviews)): ?>
    <p class="empty-state">Još uvek nema unetih pregleda menadžmenta.</p>
<?php else: ?>
    <?php foreach ($allReviews as $review): ?>
        <?php $actions = $actionsByReview[$review['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/review-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
