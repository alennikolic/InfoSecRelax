<?php
/**
 * src/modules/promene.php
 *
 * Klauzula 6.3: Planiranje promena.
 *
 * Isti obrazac kao ciljevi.php: toolbar sa Pomoć desno, modal za
 * dodavanje i uređivanje, status ostaje posebna radnja (inline forma u
 * kartici). is_unintended razlikuje promenu koju je firma sama
 * pokrenula od one koju je samo uočila nakon što se već desila
 * (relevantno i za Klauzulu 8.1).
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'promene';

$errors = [];

$validStatuses = ['predlozeno', 'odobreno', 'sprovedeno', 'odbaceno'];

// --- Dodavanje planirane promene ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $title            = trim($_POST['title'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $impactAssessment = trim($_POST['impact_assessment'] ?? '');
    $testPlan         = trim($_POST['test_plan'] ?? '');
    $rollbackPlan     = trim($_POST['rollback_plan'] ?? '');
    $approvedBy       = trim($_POST['approved_by'] ?? '');
    $plannedDate      = trim($_POST['planned_date'] ?? '');
    $isUnintended     = isset($_POST['is_unintended']) ? 1 : 0;

    if ($title === '') {
        $errors[] = 'Naziv promene je obavezan.';
    }
    if ($description === '') {
        $errors[] = 'Opis promene je obavezan.';
    }

    $approvedByValue = null;
    if ($approvedBy !== '') {
        $approvedByValue = (int) $approvedBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $approvedByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani odobravalac nije pronađen.';
            $approvedByValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO planned_changes
                (organization_id, title, description, impact_assessment, test_plan, rollback_plan,
                 approved_by, planned_date, is_unintended)
             VALUES
                (:org_id, :title, :description, :impact_assessment, :test_plan, :rollback_plan,
                 :approved_by, :planned_date, :is_unintended)'
        );
        $stmt->execute([
            'org_id'            => $organizationId,
            'title'             => $title,
            'description'       => $description,
            'impact_assessment' => $impactAssessment !== '' ? $impactAssessment : null,
            'test_plan'         => $testPlan !== '' ? $testPlan : null,
            'rollback_plan'     => $rollbackPlan !== '' ? $rollbackPlan : null,
            'approved_by'       => $approvedByValue,
            'planned_date'      => $plannedDate !== '' ? $plannedDate : null,
            'is_unintended'     => $isUnintended,
        ]);

        header('Location: ?page=promene');
        exit;
    }
}

// --- Ažuriranje postojeće promene (NE menja status - ta ostaje posebna radnja) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id               = (int) ($_POST['id'] ?? 0);
    $title            = trim($_POST['title'] ?? '');
    $description      = trim($_POST['description'] ?? '');
    $impactAssessment = trim($_POST['impact_assessment'] ?? '');
    $testPlan         = trim($_POST['test_plan'] ?? '');
    $rollbackPlan     = trim($_POST['rollback_plan'] ?? '');
    $approvedBy       = trim($_POST['approved_by'] ?? '');
    $plannedDate      = trim($_POST['planned_date'] ?? '');
    $isUnintended     = isset($_POST['is_unintended']) ? 1 : 0;

    if ($title === '') {
        $errors[] = 'Naziv promene je obavezan.';
    }
    if ($description === '') {
        $errors[] = 'Opis promene je obavezan.';
    }

    $approvedByValue = null;
    if ($approvedBy !== '') {
        $approvedByValue = (int) $approvedBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $approvedByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani odobravalac nije pronađen.';
            $approvedByValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE planned_changes
             SET title = :title, description = :description, impact_assessment = :impact_assessment,
                 test_plan = :test_plan, rollback_plan = :rollback_plan, approved_by = :approved_by,
                 planned_date = :planned_date, is_unintended = :is_unintended
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'title'             => $title,
            'description'       => $description,
            'impact_assessment' => $impactAssessment !== '' ? $impactAssessment : null,
            'test_plan'         => $testPlan !== '' ? $testPlan : null,
            'rollback_plan'     => $rollbackPlan !== '' ? $rollbackPlan : null,
            'approved_by'       => $approvedByValue,
            'planned_date'      => $plannedDate !== '' ? $plannedDate : null,
            'is_unintended'     => $isUnintended,
            'id'                => $id,
            'org_id'            => $organizationId,
        ]);

        header('Location: ?page=promene');
        exit;
    }
}

// --- Promena statusa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id        = (int) ($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if (in_array($newStatus, $validStatuses, true)) {
        $pdo->prepare(
            'UPDATE planned_changes SET status = :status WHERE id = :id AND organization_id = :org_id'
        )->execute(['status' => $newStatus, 'id' => $id, 'org_id' => $organizationId]);
    }

    header('Location: ?page=promene');
    exit;
}

// --- Brisanje promene ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM planned_changes WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=promene');
    exit;
}

// --- Aktivne osobe za dropdown odobravaoca ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje promena (oni sa planiranim datumom prvo, po najbližem) ---
$changesStmt = $pdo->prepare(
    'SELECT pc.*, p.full_name AS approved_by_name
     FROM planned_changes pc
     LEFT JOIN personnel p ON p.id = pc.approved_by
     WHERE pc.organization_id = :org_id
     ORDER BY pc.planned_date IS NULL, pc.planned_date, pc.title'
);
$changesStmt->execute(['org_id' => $organizationId]);
$allChanges = $changesStmt->fetchAll();

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
    <button type="button" class="btn-primary" onclick="openAddChangeModal()">+ Dodaj promenu</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allChanges)): ?>
    <p class="empty-state">Još uvek nema unetih promena.</p>
<?php else: ?>
    <?php foreach ($allChanges as $change): ?>
        <?php include __DIR__ . '/../includes/change-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="change-modal-overlay" onclick="closeChangeModal()">
    <div class="modal-box modal-box-wide" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="change-modal-title">Dodaj promenu</span>
            <button type="button" class="modal-close" onclick="closeChangeModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="change-modal-form">
            <input type="hidden" name="action" id="change-modal-action" value="add">
            <input type="hidden" name="id" id="change-modal-id" value="">

            <div class="form-row">
                <label for="modal_title">Naziv promene</label>
                <input type="text" name="title" id="modal_title" required
                    placeholder="npr. Migracija na novog dobavljača cloud hostinga">
            </div>

            <div class="form-row">
                <label for="modal_description">Opis</label>
                <textarea name="description" id="modal_description" rows="2" required
                    placeholder="npr. Prelazak sa trenutnog na novog dobavljača hostinga zbog isteka ugovora."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_impact_assessment">Procena uticaja (opciono)</label>
                <textarea name="impact_assessment" id="modal_impact_assessment" rows="2"
                    placeholder="npr. Utiče na obim ISMS-a (novi dobavljač) i zahteva ažuriranje procene rizika."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_test_plan">Plan testiranja (opciono)</label>
                <textarea name="test_plan" id="modal_test_plan" rows="2"
                    placeholder="npr. Testirati na probnom okruženju pre prebacivanja produkcionih podataka."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_rollback_plan">Plan povratka na prethodno stanje (opciono)</label>
                <textarea name="rollback_plan" id="modal_rollback_plan" rows="2"
                    placeholder="npr. Zadržati stari nalog aktivan 30 dana kao rezervu."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_approved_by">Odobrio (opciono)</label>
                <select name="approved_by" id="modal_approved_by">
                    <option value="">Nije dodeljen</option>
                    <?php foreach ($activePersonnelOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($activePersonnelOptions)): ?>
                    <p class="item-meta">Nema unetih aktivnih osoba - prvo ih dodaj na stranici "Zaposleni i saradnici".</p>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <label for="modal_planned_date">Planirani datum (opciono)</label>
                <input type="date" name="planned_date" id="modal_planned_date">
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_unintended" id="modal_is_unintended" value="1">
                    Nenamerna promena (firma je uočila, nije je sama pokrenula)
                </label>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromChangeModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeChangeModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddChangeModal() {
    document.getElementById('change-modal-title').textContent = 'Dodaj promenu';
    document.getElementById('change-modal-action').value = 'add';
    document.getElementById('change-modal-id').value = '';
    document.getElementById('modal_title').value = '';
    document.getElementById('modal_description').value = '';
    document.getElementById('modal_impact_assessment').value = '';
    document.getElementById('modal_test_plan').value = '';
    document.getElementById('modal_rollback_plan').value = '';
    document.getElementById('modal_approved_by').value = '';
    document.getElementById('modal_planned_date').value = '';
    document.getElementById('modal_is_unintended').checked = false;
    document.getElementById('change-modal-overlay').classList.add('is-open');
}

function openEditChangeModal(change) {
    document.getElementById('change-modal-title').textContent = 'Uredi promenu';
    document.getElementById('change-modal-action').value = 'update';
    document.getElementById('change-modal-id').value = change.id;
    document.getElementById('modal_title').value = change.title;
    document.getElementById('modal_description').value = change.description;
    document.getElementById('modal_impact_assessment').value = change.impact_assessment;
    document.getElementById('modal_test_plan').value = change.test_plan;
    document.getElementById('modal_rollback_plan').value = change.rollback_plan;
    document.getElementById('modal_approved_by').value = change.approved_by;
    document.getElementById('modal_planned_date').value = change.planned_date;
    document.getElementById('modal_is_unintended').checked = change.is_unintended;
    document.getElementById('change-modal-overlay').classList.add('is-open');
}

function closeChangeModal() {
    document.getElementById('change-modal-overlay').classList.remove('is-open');
}

function openHelpFromChangeModal() {
    closeChangeModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeChangeModal();
    }
});
</script>
