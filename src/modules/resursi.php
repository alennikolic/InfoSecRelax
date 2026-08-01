<?php
/**
 * src/modules/resursi.php
 *
 * Klauzula 7.1: Resursi.
 *
 * Prost CRUD, isti obrazac kao ciljevi.php/promene.php, sa promenom
 * statusa preko inline forme. Koristi novu tabelu isms_resources -
 * videti db/migrations/001_add_isms_resources.sql za SQL koji je
 * potrebno primeniti (na postojeću bazu, jednim docker exec pozivom)
 * pre nego što ovaj modul može da radi.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

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
?>

<p class="module-intro">
    Klauzula 7.1 traži da se odrede i obezbede resursi potrebni za
    uspostavljanje, primenu, održavanje i stalno unapređenje ISMS-a - budžet,
    osoblje, alati, obuka i infrastruktura.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="factor-form">
    <input type="hidden" name="action" value="add">

    <div class="form-row">
        <label for="resource_type">Vrsta resursa</label>
        <select name="resource_type" id="resource_type" required>
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
        <label for="description">Opis</label>
        <textarea name="description" id="description" rows="2" required
            placeholder="npr. Godišnja licenca za alat za skeniranje ranjivosti."></textarea>
    </div>

    <div class="form-row">
        <label for="amount_or_quantity">Iznos ili količina (opciono)</label>
        <input type="text" name="amount_or_quantity" id="amount_or_quantity"
            placeholder="npr. 40.000 RSD godišnje, 2 dana mesečno, 1 licenca">
    </div>

    <div class="form-row">
        <label for="provided_by">Obezbedio/odobrio (opciono)</label>
        <select name="provided_by" id="provided_by">
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
        <label for="review_date">Datum pregleda (opciono)</label>
        <input type="date" name="review_date" id="review_date">
    </div>

    <button type="submit" class="btn-primary">Dodaj resurs</button>
</form>

<?php if (empty($allResources)): ?>
    <p class="empty-state">Još uvek nema unetih resursa.</p>
<?php else: ?>
    <?php foreach ($allResources as $resource): ?>
        <?php include __DIR__ . '/../includes/resource-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
