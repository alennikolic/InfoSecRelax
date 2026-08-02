<?php
/**
 * src/modules/ciljevi.php
 *
 * Klauzula 6.2: Ciljevi bezbednosti informacija i planiranje njihovog
 * ostvarenja.
 *
 * Isti obrazac kao procena-rizika.php: toolbar sa Pomoć desno, modal za
 * dodavanje i uređivanje, status ostaje posebna radnja (inline forma u
 * kartici, ne dira se kroz "Uredi"). Pet polja u formi direktno
 * odgovaraju na "pet pitanja" iz standarda za plan ostvarenja: šta
 * (what_will_be_done), koji resursi (resources_required), ko
 * (owner_id), kada (due_date), kako se meri uspeh (evaluation_method).
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'ciljevi';

$errors = [];

$validStatuses = ['planiran', 'u_toku', 'ostvaren', 'neostvaren'];

// --- Dodavanje novog cilja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $title             = trim($_POST['title'] ?? '');
    $whatWillBeDone    = trim($_POST['what_will_be_done'] ?? '');
    $resourcesRequired = trim($_POST['resources_required'] ?? '');
    $ownerId           = trim($_POST['owner_id'] ?? '');
    $dueDate           = trim($_POST['due_date'] ?? '');
    $evaluationMethod  = trim($_POST['evaluation_method'] ?? '');
    $linkedRiskId      = trim($_POST['linked_risk_id'] ?? '');

    if ($title === '') {
        $errors[] = 'Naziv cilja je obavezan.';
    }
    if ($whatWillBeDone === '') {
        $errors[] = 'Opis šta će biti urađeno je obavezan.';
    }

    // Nosilac, ako je izabran, mora stvarno postojati u ovoj organizaciji.
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

    // Isto i povezani rizik.
    $linkedRiskIdValue = null;
    if ($linkedRiskId !== '') {
        $linkedRiskIdValue = (int) $linkedRiskId;
        $riskCheck = $pdo->prepare('SELECT id FROM risks WHERE id = :id AND organization_id = :org_id');
        $riskCheck->execute(['id' => $linkedRiskIdValue, 'org_id' => $organizationId]);

        if ($riskCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani rizik nije pronađen.';
            $linkedRiskIdValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO objectives
                (organization_id, title, linked_risk_id, what_will_be_done, resources_required,
                 owner_id, due_date, evaluation_method)
             VALUES
                (:org_id, :title, :linked_risk_id, :what_will_be_done, :resources_required,
                 :owner_id, :due_date, :evaluation_method)'
        );
        $stmt->execute([
            'org_id'             => $organizationId,
            'title'              => $title,
            'linked_risk_id'     => $linkedRiskIdValue,
            'what_will_be_done'  => $whatWillBeDone,
            'resources_required' => $resourcesRequired !== '' ? $resourcesRequired : null,
            'owner_id'           => $ownerIdValue,
            'due_date'           => $dueDate !== '' ? $dueDate : null,
            'evaluation_method'  => $evaluationMethod !== '' ? $evaluationMethod : null,
        ]);

        header('Location: ?page=ciljevi');
        exit;
    }
}

// --- Ažuriranje postojećeg cilja (NE menja status - ta ostaje posebna radnja) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id                = (int) ($_POST['id'] ?? 0);
    $title             = trim($_POST['title'] ?? '');
    $whatWillBeDone    = trim($_POST['what_will_be_done'] ?? '');
    $resourcesRequired = trim($_POST['resources_required'] ?? '');
    $ownerId           = trim($_POST['owner_id'] ?? '');
    $dueDate           = trim($_POST['due_date'] ?? '');
    $evaluationMethod  = trim($_POST['evaluation_method'] ?? '');
    $linkedRiskId      = trim($_POST['linked_risk_id'] ?? '');

    if ($title === '') {
        $errors[] = 'Naziv cilja je obavezan.';
    }
    if ($whatWillBeDone === '') {
        $errors[] = 'Opis šta će biti urađeno je obavezan.';
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

    $linkedRiskIdValue = null;
    if ($linkedRiskId !== '') {
        $linkedRiskIdValue = (int) $linkedRiskId;
        $riskCheck = $pdo->prepare('SELECT id FROM risks WHERE id = :id AND organization_id = :org_id');
        $riskCheck->execute(['id' => $linkedRiskIdValue, 'org_id' => $organizationId]);

        if ($riskCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani rizik nije pronađen.';
            $linkedRiskIdValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE objectives
             SET title = :title, linked_risk_id = :linked_risk_id, what_will_be_done = :what_will_be_done,
                 resources_required = :resources_required, owner_id = :owner_id, due_date = :due_date,
                 evaluation_method = :evaluation_method
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'title'              => $title,
            'linked_risk_id'     => $linkedRiskIdValue,
            'what_will_be_done'  => $whatWillBeDone,
            'resources_required' => $resourcesRequired !== '' ? $resourcesRequired : null,
            'owner_id'           => $ownerIdValue,
            'due_date'           => $dueDate !== '' ? $dueDate : null,
            'evaluation_method'  => $evaluationMethod !== '' ? $evaluationMethod : null,
            'id'                 => $id,
            'org_id'             => $organizationId,
        ]);

        header('Location: ?page=ciljevi');
        exit;
    }
}

// --- Promena statusa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id        = (int) ($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if (in_array($newStatus, $validStatuses, true)) {
        $pdo->prepare(
            'UPDATE objectives SET status = :status WHERE id = :id AND organization_id = :org_id'
        )->execute(['status' => $newStatus, 'id' => $id, 'org_id' => $organizationId]);
    }

    header('Location: ?page=ciljevi');
    exit;
}

// --- Brisanje cilja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM objectives WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=ciljevi');
    exit;
}

// --- Aktivne osobe za dropdown nosioca ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Rizici za dropdown ---
$risksStmt = $pdo->prepare('SELECT id, title FROM risks WHERE organization_id = :org_id ORDER BY title');
$risksStmt->execute(['org_id' => $organizationId]);
$riskOptions = $risksStmt->fetchAll();

// --- Učitavanje ciljeva (oni sa rokom prvo, po najbližem roku) ---
$objectivesStmt = $pdo->prepare(
    'SELECT o.*, p.full_name AS owner_name, r.title AS risk_title
     FROM objectives o
     LEFT JOIN personnel p ON p.id = o.owner_id
     LEFT JOIN risks r ON r.id = o.linked_risk_id
     WHERE o.organization_id = :org_id
     ORDER BY o.due_date IS NULL, o.due_date, o.title'
);
$objectivesStmt->execute(['org_id' => $organizationId]);
$allObjectives = $objectivesStmt->fetchAll();

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
    <button type="button" class="btn-primary" onclick="openAddObjectiveModal()">+ Dodaj cilj</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allObjectives)): ?>
    <p class="empty-state">Još uvek nema unetih ciljeva.</p>
<?php else: ?>
    <?php foreach ($allObjectives as $objective): ?>
        <?php include __DIR__ . '/../includes/objective-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="objective-modal-overlay" onclick="closeObjectiveModal()">
    <div class="modal-box modal-box-wide" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="objective-modal-title">Dodaj cilj</span>
            <button type="button" class="modal-close" onclick="closeObjectiveModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="objective-modal-form">
            <input type="hidden" name="action" id="objective-modal-action" value="add">
            <input type="hidden" name="id" id="objective-modal-id" value="">

            <div class="form-row">
                <label for="modal_title">Naziv cilja</label>
                <input type="text" name="title" id="modal_title" required
                    placeholder="npr. Smanjiti prosečno vreme ukidanja pristupa nakon odlaska zaposlenog">
            </div>

            <div class="form-row">
                <label for="modal_what_will_be_done">Šta će biti urađeno</label>
                <textarea name="what_will_be_done" id="modal_what_will_be_done" rows="2" required
                    placeholder="npr. Uvesti checklistu za offboarding koja uključuje ukidanje pristupa u roku od 24h."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_resources_required">Potrebni resursi (opciono)</label>
                <textarea name="resources_required" id="modal_resources_required" rows="2"
                    placeholder="npr. Nekoliko sati administratora sistema mesečno."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_owner_id">Nosilac (opciono)</label>
                <select name="owner_id" id="modal_owner_id">
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
                <label for="modal_due_date">Rok (opciono)</label>
                <input type="date" name="due_date" id="modal_due_date">
            </div>

            <div class="form-row">
                <label for="modal_evaluation_method">Kako će se meriti uspeh (opciono)</label>
                <textarea name="evaluation_method" id="modal_evaluation_method" rows="2"
                    placeholder="npr. Pokazatelj 'vreme ukidanja pristupa', cilj ispod 24h u proseku."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_linked_risk_id">Povezan rizik (opciono)</label>
                <select name="linked_risk_id" id="modal_linked_risk_id">
                    <option value="">Nije povezano</option>
                    <?php foreach ($riskOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromObjectiveModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeObjectiveModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddObjectiveModal() {
    document.getElementById('objective-modal-title').textContent = 'Dodaj cilj';
    document.getElementById('objective-modal-action').value = 'add';
    document.getElementById('objective-modal-id').value = '';
    document.getElementById('modal_title').value = '';
    document.getElementById('modal_what_will_be_done').value = '';
    document.getElementById('modal_resources_required').value = '';
    document.getElementById('modal_owner_id').value = '';
    document.getElementById('modal_due_date').value = '';
    document.getElementById('modal_evaluation_method').value = '';
    document.getElementById('modal_linked_risk_id').value = '';
    document.getElementById('objective-modal-overlay').classList.add('is-open');
}

function openEditObjectiveModal(objective) {
    document.getElementById('objective-modal-title').textContent = 'Uredi cilj';
    document.getElementById('objective-modal-action').value = 'update';
    document.getElementById('objective-modal-id').value = objective.id;
    document.getElementById('modal_title').value = objective.title;
    document.getElementById('modal_what_will_be_done').value = objective.what_will_be_done;
    document.getElementById('modal_resources_required').value = objective.resources_required;
    document.getElementById('modal_owner_id').value = objective.owner_id;
    document.getElementById('modal_due_date').value = objective.due_date;
    document.getElementById('modal_evaluation_method').value = objective.evaluation_method;
    document.getElementById('modal_linked_risk_id').value = objective.linked_risk_id;
    document.getElementById('objective-modal-overlay').classList.add('is-open');
}

function closeObjectiveModal() {
    document.getElementById('objective-modal-overlay').classList.remove('is-open');
}

function openHelpFromObjectiveModal() {
    closeObjectiveModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeObjectiveModal();
    }
});
</script>
