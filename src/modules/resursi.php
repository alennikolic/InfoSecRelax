<?php
/**
 * src/modules/resursi.php
 *
 * Klauzula 7.1: Resursi.
 *
 * Isti obrazac kao ciljevi.php/promene.php: toolbar sa Pomoć desno,
 * modal za dodavanje i uređivanje, status ostaje posebna radnja
 * (inline forma u kartici). Koristi tabelu isms_resources - videti
 * db/migrations/001_add_isms_resources.sql ako još nije primenjena.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'resursi';

$errors = [];

$validResourceTypes = ['budzet', 'osoblje', 'alat_ili_licenca', 'obuka', 'infrastruktura', 'ostalo'];
$validStatuses       = ['planirano', 'obezbedjeno', 'u_koriscenju'];

// --- Dodavanje resursa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $resourceType     = $_POST['resource_type'] ?? '';
    $description      = trim($_POST['description'] ?? '');
    $amountOrQuantity = trim($_POST['amount_or_quantity'] ?? '');
    $providedBy       = trim($_POST['provided_by'] ?? '');
    $reviewDate       = trim($_POST['review_date'] ?? '');

    if (!in_array($resourceType, $validResourceTypes, true)) {
        $errors[] = 'Izaberite vrstu resursa.';
    }
    if ($description === '') {
        $errors[] = 'Opis resursa je obavezan.';
    }

    $providedByValue = null;
    if ($providedBy !== '') {
        $providedByValue = (int) $providedBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $providedByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrana osoba nije pronađena.';
            $providedByValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO isms_resources
                (organization_id, resource_type, description, amount_or_quantity, provided_by, review_date)
             VALUES
                (:org_id, :resource_type, :description, :amount_or_quantity, :provided_by, :review_date)'
        );
        $stmt->execute([
            'org_id'             => $organizationId,
            'resource_type'      => $resourceType,
            'description'        => $description,
            'amount_or_quantity' => $amountOrQuantity !== '' ? $amountOrQuantity : null,
            'provided_by'        => $providedByValue,
            'review_date'        => $reviewDate !== '' ? $reviewDate : null,
        ]);

        header('Location: ?page=resursi');
        exit;
    }
}

// --- Ažuriranje postojećeg resursa (NE menja status - ta ostaje posebna radnja) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id               = (int) ($_POST['id'] ?? 0);
    $resourceType     = $_POST['resource_type'] ?? '';
    $description      = trim($_POST['description'] ?? '');
    $amountOrQuantity = trim($_POST['amount_or_quantity'] ?? '');
    $providedBy       = trim($_POST['provided_by'] ?? '');
    $reviewDate       = trim($_POST['review_date'] ?? '');

    if (!in_array($resourceType, $validResourceTypes, true)) {
        $errors[] = 'Izaberite vrstu resursa.';
    }
    if ($description === '') {
        $errors[] = 'Opis resursa je obavezan.';
    }

    $providedByValue = null;
    if ($providedBy !== '') {
        $providedByValue = (int) $providedBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $providedByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrana osoba nije pronađena.';
            $providedByValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE isms_resources
             SET resource_type = :resource_type, description = :description,
                 amount_or_quantity = :amount_or_quantity, provided_by = :provided_by,
                 review_date = :review_date
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'resource_type'      => $resourceType,
            'description'        => $description,
            'amount_or_quantity' => $amountOrQuantity !== '' ? $amountOrQuantity : null,
            'provided_by'        => $providedByValue,
            'review_date'        => $reviewDate !== '' ? $reviewDate : null,
            'id'                 => $id,
            'org_id'             => $organizationId,
        ]);

        header('Location: ?page=resursi');
        exit;
    }
}

// --- Promena statusa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id        = (int) ($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if (in_array($newStatus, $validStatuses, true)) {
        $pdo->prepare(
            'UPDATE isms_resources SET status = :status WHERE id = :id AND organization_id = :org_id'
        )->execute(['status' => $newStatus, 'id' => $id, 'org_id' => $organizationId]);
    }

    header('Location: ?page=resursi');
    exit;
}

// --- Brisanje resursa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM isms_resources WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=resursi');
    exit;
}

// --- Aktivne osobe za dropdown ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje resursa ---
$resourcesStmt = $pdo->prepare(
    'SELECT r.*, p.full_name AS provided_by_name
     FROM isms_resources r
     LEFT JOIN personnel p ON p.id = r.provided_by
     WHERE r.organization_id = :org_id
     ORDER BY r.resource_type, r.created_at DESC'
);
$resourcesStmt->execute(['org_id' => $organizationId]);
$allResources = $resourcesStmt->fetchAll();

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
    <button type="button" class="btn-primary" onclick="openAddResourceModal()">+ Dodaj resurs</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allResources)): ?>
    <p class="empty-state">Još uvek nema unetih resursa.</p>
<?php else: ?>
    <?php foreach ($allResources as $resource): ?>
        <?php include __DIR__ . '/../includes/resource-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="resource-modal-overlay" onclick="closeResourceModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="resource-modal-title">Dodaj resurs</span>
            <button type="button" class="modal-close" onclick="closeResourceModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="resource-modal-form">
            <input type="hidden" name="action" id="resource-modal-action" value="add">
            <input type="hidden" name="id" id="resource-modal-id" value="">

            <div class="form-row">
                <label for="modal_resource_type">Vrsta resursa</label>
                <select name="resource_type" id="modal_resource_type" required>
                    <option value="">Izaberite...</option>
                    <option value="budzet">Budžet</option>
                    <option value="osoblje">Osoblje</option>
                    <option value="alat_ili_licenca">Alat ili licenca</option>
                    <option value="obuka">Obuka</option>
                    <option value="infrastruktura">Infrastruktura</option>
                    <option value="ostalo">Ostalo</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_description">Opis</label>
                <textarea name="description" id="modal_description" rows="2" required
                    placeholder="npr. Godišnja licenca za alat za skeniranje ranjivosti."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_amount_or_quantity">Iznos ili količina (opciono)</label>
                <input type="text" name="amount_or_quantity" id="modal_amount_or_quantity"
                    placeholder="npr. 40.000 RSD godišnje, 2 dana mesečno, 1 licenca">
            </div>

            <div class="form-row">
                <label for="modal_provided_by">Obezbedio/odobrio (opciono)</label>
                <select name="provided_by" id="modal_provided_by">
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
                <label for="modal_review_date">Datum pregleda (opciono)</label>
                <input type="date" name="review_date" id="modal_review_date">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromResourceModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeResourceModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddResourceModal() {
    document.getElementById('resource-modal-title').textContent = 'Dodaj resurs';
    document.getElementById('resource-modal-action').value = 'add';
    document.getElementById('resource-modal-id').value = '';
    document.getElementById('modal_resource_type').value = '';
    document.getElementById('modal_description').value = '';
    document.getElementById('modal_amount_or_quantity').value = '';
    document.getElementById('modal_provided_by').value = '';
    document.getElementById('modal_review_date').value = '';
    document.getElementById('resource-modal-overlay').classList.add('is-open');
}

function openEditResourceModal(resource) {
    document.getElementById('resource-modal-title').textContent = 'Uredi resurs';
    document.getElementById('resource-modal-action').value = 'update';
    document.getElementById('resource-modal-id').value = resource.id;
    document.getElementById('modal_resource_type').value = resource.resource_type;
    document.getElementById('modal_description').value = resource.description;
    document.getElementById('modal_amount_or_quantity').value = resource.amount_or_quantity;
    document.getElementById('modal_provided_by').value = resource.provided_by;
    document.getElementById('modal_review_date').value = resource.review_date;
    document.getElementById('resource-modal-overlay').classList.add('is-open');
}

function closeResourceModal() {
    document.getElementById('resource-modal-overlay').classList.remove('is-open');
}

function openHelpFromResourceModal() {
    closeResourceModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeResourceModal();
    }
});
</script>
