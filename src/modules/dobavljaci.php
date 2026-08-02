<?php
/**
 * src/modules/dobavljaci.php
 *
 * A.5.19-5.23: Bezbednost informacija u odnosima sa dobavljačima.
 *
 * suppliers ima pet boolean "provera" (has_data_access, is_cloud_service,
 * dpa_signed, exit_strategy_confirmed, subprocessors_reviewed) - svaka
 * odgovara jednoj podkontroli iz A.5.19-5.23. Prikazane su kao mali
 * bedževi na kartici, bez pokušaja da se iz njih izvede kombinovana
 * ocena - to ostaje na čitaocu da protumači.
 *
 * Isti obrazac kao ostali moduli: toolbar sa Pomoć desno, modal za
 * dodavanje/uređivanje dobavljača, "Dodaj pregled" kao poseban modal
 * otvoren dugmetom u kartici. Dodavanje pregleda automatski pomera
 * suppliers.last_reviewed_at napred (nikad unazad, ako neko naknadno
 * unese stariji istorijski pregled).
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'dobavljaci';

$errors = [];

$validRiskLevels = ['nizak', 'srednji', 'visok'];

// --- Dodavanje dobavljača ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_supplier') {
    $name                  = trim($_POST['name'] ?? '');
    $riskLevel             = $_POST['risk_level'] ?? 'srednji';
    $hasDataAccess         = isset($_POST['has_data_access']) ? 1 : 0;
    $isCloudService        = isset($_POST['is_cloud_service']) ? 1 : 0;
    $dpaSigned             = isset($_POST['dpa_signed']) ? 1 : 0;
    $exitStrategyConfirmed = isset($_POST['exit_strategy_confirmed']) ? 1 : 0;
    $subprocessorsReviewed = isset($_POST['subprocessors_reviewed']) ? 1 : 0;
    $contractStart         = trim($_POST['contract_start'] ?? '');
    $contractEnd           = trim($_POST['contract_end'] ?? '');
    $slaRequirements       = trim($_POST['sla_requirements'] ?? '');

    if ($name === '') {
        $errors[] = 'Naziv dobavljača je obavezan.';
    }
    if (!in_array($riskLevel, $validRiskLevels, true)) {
        $errors[] = 'Izaberite nivo rizika.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO suppliers
                (organization_id, name, has_data_access, risk_level, is_cloud_service,
                 contract_start, contract_end, dpa_signed, sla_requirements,
                 exit_strategy_confirmed, subprocessors_reviewed)
             VALUES
                (:org_id, :name, :has_data_access, :risk_level, :is_cloud_service,
                 :contract_start, :contract_end, :dpa_signed, :sla_requirements,
                 :exit_strategy_confirmed, :subprocessors_reviewed)'
        );
        $stmt->execute([
            'org_id'                  => $organizationId,
            'name'                    => $name,
            'has_data_access'         => $hasDataAccess,
            'risk_level'              => $riskLevel,
            'is_cloud_service'        => $isCloudService,
            'contract_start'          => $contractStart !== '' ? $contractStart : null,
            'contract_end'            => $contractEnd !== '' ? $contractEnd : null,
            'dpa_signed'              => $dpaSigned,
            'sla_requirements'        => $slaRequirements !== '' ? $slaRequirements : null,
            'exit_strategy_confirmed' => $exitStrategyConfirmed,
            'subprocessors_reviewed'  => $subprocessorsReviewed,
        ]);

        header('Location: ?page=dobavljaci');
        exit;
    }
}

// --- Ažuriranje postojećeg dobavljača ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_supplier') {
    $id                    = (int) ($_POST['id'] ?? 0);
    $name                  = trim($_POST['name'] ?? '');
    $riskLevel             = $_POST['risk_level'] ?? 'srednji';
    $hasDataAccess         = isset($_POST['has_data_access']) ? 1 : 0;
    $isCloudService        = isset($_POST['is_cloud_service']) ? 1 : 0;
    $dpaSigned             = isset($_POST['dpa_signed']) ? 1 : 0;
    $exitStrategyConfirmed = isset($_POST['exit_strategy_confirmed']) ? 1 : 0;
    $subprocessorsReviewed = isset($_POST['subprocessors_reviewed']) ? 1 : 0;
    $contractStart         = trim($_POST['contract_start'] ?? '');
    $contractEnd           = trim($_POST['contract_end'] ?? '');
    $slaRequirements       = trim($_POST['sla_requirements'] ?? '');

    if ($name === '') {
        $errors[] = 'Naziv dobavljača je obavezan.';
    }
    if (!in_array($riskLevel, $validRiskLevels, true)) {
        $errors[] = 'Izaberite nivo rizika.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE suppliers
             SET name = :name, risk_level = :risk_level, has_data_access = :has_data_access,
                 is_cloud_service = :is_cloud_service, dpa_signed = :dpa_signed,
                 exit_strategy_confirmed = :exit_strategy_confirmed,
                 subprocessors_reviewed = :subprocessors_reviewed, contract_start = :contract_start,
                 contract_end = :contract_end, sla_requirements = :sla_requirements
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'name'                    => $name,
            'risk_level'              => $riskLevel,
            'has_data_access'         => $hasDataAccess,
            'is_cloud_service'        => $isCloudService,
            'dpa_signed'              => $dpaSigned,
            'exit_strategy_confirmed' => $exitStrategyConfirmed,
            'subprocessors_reviewed'  => $subprocessorsReviewed,
            'contract_start'          => $contractStart !== '' ? $contractStart : null,
            'contract_end'            => $contractEnd !== '' ? $contractEnd : null,
            'sla_requirements'        => $slaRequirements !== '' ? $slaRequirements : null,
            'id'                      => $id,
            'org_id'                  => $organizationId,
        ]);

        header('Location: ?page=dobavljaci');
        exit;
    }
}

// --- Brisanje dobavljača (pregledi se brišu kaskadno preko FK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_supplier') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM suppliers WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=dobavljaci');
    exit;
}

// --- Dodavanje pregleda dobavljača ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_review') {
    $supplierId = (int) ($_POST['supplier_id'] ?? 0);
    $reviewDate = trim($_POST['review_date'] ?? '');
    $findings   = trim($_POST['findings'] ?? '');
    $reviewedBy = trim($_POST['reviewed_by'] ?? '');

    $supplierCheck = $pdo->prepare('SELECT id FROM suppliers WHERE id = :id AND organization_id = :org_id');
    $supplierCheck->execute(['id' => $supplierId, 'org_id' => $organizationId]);

    if ($supplierCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznat dobavljač.';
    }
    if ($reviewDate === '') {
        $errors[] = 'Datum pregleda je obavezan.';
    }

    $reviewedByValue = null;
    if ($reviewedBy !== '') {
        $reviewedByValue = (int) $reviewedBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $reviewedByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrana osoba nije pronađena.';
            $reviewedByValue = null;
        }
    }

    if (empty($errors)) {
        $pdo->prepare(
            'INSERT INTO supplier_reviews (supplier_id, review_date, findings, reviewed_by)
             VALUES (:supplier_id, :review_date, :findings, :reviewed_by)'
        )->execute([
            'supplier_id' => $supplierId,
            'review_date' => $reviewDate,
            'findings'    => $findings !== '' ? $findings : null,
            'reviewed_by' => $reviewedByValue,
        ]);

        // last_reviewed_at se pomera samo napred, nikad unazad - dva
        // različita imena parametra za istu vrednost jer nativni
        // pripremljeni upiti ne dozvoljavaju isto ime placeholder-a
        // dvaput u istom upitu.
        $pdo->prepare(
            'UPDATE suppliers
             SET last_reviewed_at = :review_date
             WHERE id = :id AND organization_id = :org_id
               AND (last_reviewed_at IS NULL OR last_reviewed_at < :review_date_cmp)'
        )->execute([
            'review_date'     => $reviewDate,
            'id'              => $supplierId,
            'org_id'          => $organizationId,
            'review_date_cmp' => $reviewDate,
        ]);

        header('Location: ?page=dobavljaci');
        exit;
    }
}

// --- Brisanje pregleda ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_review') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE r FROM supplier_reviews r
         INNER JOIN suppliers s ON s.id = r.supplier_id
         WHERE r.id = :id AND s.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=dobavljaci');
    exit;
}

// --- Aktivne osobe za dropdown recenzenta ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje dobavljača ---
$suppliersStmt = $pdo->prepare('SELECT * FROM suppliers WHERE organization_id = :org_id ORDER BY name');
$suppliersStmt->execute(['org_id' => $organizationId]);
$allSuppliers = $suppliersStmt->fetchAll();

// --- Pregledi za sve dobavljače ove organizacije, grupisani po supplier_id ---
$reviewsStmt = $pdo->prepare(
    'SELECT r.*, p.full_name AS reviewer_name
     FROM supplier_reviews r
     INNER JOIN suppliers s ON s.id = r.supplier_id
     LEFT JOIN personnel p ON p.id = r.reviewed_by
     WHERE s.organization_id = :org_id
     ORDER BY r.review_date DESC'
);
$reviewsStmt->execute(['org_id' => $organizationId]);

$reviewsBySupplier = [];
foreach ($reviewsStmt->fetchAll() as $review) {
    $reviewsBySupplier[$review['supplier_id']][] = $review;
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
    <button type="button" class="btn-primary" onclick="openAddSupplierModal()">+ Dodaj dobavljača</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allSuppliers)): ?>
    <p class="empty-state">Još uvek nema unetih dobavljača.</p>
<?php else: ?>
    <?php foreach ($allSuppliers as $supplier): ?>
        <?php $reviews = $reviewsBySupplier[$supplier['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/supplier-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="supplier-modal-overlay" onclick="closeSupplierModal()">
    <div class="modal-box modal-box-wide" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="supplier-modal-title">Dodaj dobavljača</span>
            <button type="button" class="modal-close" onclick="closeSupplierModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="supplier-modal-form">
            <input type="hidden" name="action" id="supplier-modal-action" value="add_supplier">
            <input type="hidden" name="id" id="supplier-modal-id" value="">

            <div class="form-row">
                <label for="modal_name">Naziv dobavljača</label>
                <input type="text" name="name" id="modal_name" required placeholder="npr. Dobavljač cloud hostinga">
            </div>

            <div class="form-row">
                <label for="modal_risk_level">Nivo rizika</label>
                <select name="risk_level" id="modal_risk_level">
                    <option value="nizak">Nizak</option>
                    <option value="srednji">Srednji</option>
                    <option value="visok">Visok</option>
                </select>
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="has_data_access" id="modal_has_data_access" value="1">
                    Ima pristup podacima (A.5.19)
                </label>
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_cloud_service" id="modal_is_cloud_service" value="1">
                    Cloud usluga (A.5.23)
                </label>
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="dpa_signed" id="modal_dpa_signed" value="1">
                    Ugovor o obradi podataka potpisan (A.5.20)
                </label>
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="exit_strategy_confirmed" id="modal_exit_strategy_confirmed" value="1">
                    Potvrđena izlazna strategija (A.5.23)
                </label>
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="subprocessors_reviewed" id="modal_subprocessors_reviewed" value="1">
                    Podobrađivači pregledani (A.5.21)
                </label>
            </div>

            <div class="form-row">
                <label for="modal_sla_requirements">Zahtevi u ugovoru (opciono)</label>
                <textarea name="sla_requirements" id="modal_sla_requirements" rows="2"
                    placeholder="npr. Obavezna dvofaktorska autentifikacija za pristup administraciji."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_contract_start">Početak ugovora (opciono)</label>
                <input type="date" name="contract_start" id="modal_contract_start">
            </div>

            <div class="form-row">
                <label for="modal_contract_end">Kraj ugovora (opciono)</label>
                <input type="date" name="contract_end" id="modal_contract_end">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromSupplierModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeSupplierModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="review-modal-overlay" onclick="closeReviewModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="review-modal-title">Dodaj pregled</span>
            <button type="button" class="modal-close" onclick="closeReviewModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="add_review">
            <input type="hidden" name="supplier_id" id="review-modal-supplier-id" value="">

            <div class="form-row">
                <label for="modal_review_date">Datum pregleda</label>
                <input type="date" name="review_date" id="modal_review_date" required>
            </div>

            <div class="form-row">
                <label for="modal_findings">Nalazi (opciono)</label>
                <textarea name="findings" id="modal_findings" rows="2"
                    placeholder="npr. Dobavljač je promenio podizvođača za skladištenje podataka."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_reviewed_by">Pregledao (opciono)</label>
                <select name="reviewed_by" id="modal_reviewed_by">
                    <option value="">Nije dodeljen</option>
                    <?php foreach ($activePersonnelOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
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

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddSupplierModal() {
    document.getElementById('supplier-modal-title').textContent = 'Dodaj dobavljača';
    document.getElementById('supplier-modal-action').value = 'add_supplier';
    document.getElementById('supplier-modal-id').value = '';
    document.getElementById('modal_name').value = '';
    document.getElementById('modal_risk_level').value = 'srednji';
    document.getElementById('modal_has_data_access').checked = false;
    document.getElementById('modal_is_cloud_service').checked = false;
    document.getElementById('modal_dpa_signed').checked = false;
    document.getElementById('modal_exit_strategy_confirmed').checked = false;
    document.getElementById('modal_subprocessors_reviewed').checked = false;
    document.getElementById('modal_sla_requirements').value = '';
    document.getElementById('modal_contract_start').value = '';
    document.getElementById('modal_contract_end').value = '';
    document.getElementById('supplier-modal-overlay').classList.add('is-open');
}

function openEditSupplierModal(supplier) {
    document.getElementById('supplier-modal-title').textContent = 'Uredi dobavljača';
    document.getElementById('supplier-modal-action').value = 'update_supplier';
    document.getElementById('supplier-modal-id').value = supplier.id;
    document.getElementById('modal_name').value = supplier.name;
    document.getElementById('modal_risk_level').value = supplier.risk_level;
    document.getElementById('modal_has_data_access').checked = supplier.has_data_access;
    document.getElementById('modal_is_cloud_service').checked = supplier.is_cloud_service;
    document.getElementById('modal_dpa_signed').checked = supplier.dpa_signed;
    document.getElementById('modal_exit_strategy_confirmed').checked = supplier.exit_strategy_confirmed;
    document.getElementById('modal_subprocessors_reviewed').checked = supplier.subprocessors_reviewed;
    document.getElementById('modal_sla_requirements').value = supplier.sla_requirements;
    document.getElementById('modal_contract_start').value = supplier.contract_start;
    document.getElementById('modal_contract_end').value = supplier.contract_end;
    document.getElementById('supplier-modal-overlay').classList.add('is-open');
}

function closeSupplierModal() {
    document.getElementById('supplier-modal-overlay').classList.remove('is-open');
}

function openHelpFromSupplierModal() {
    closeSupplierModal();
    openHelpModal();
}

function openReviewModal(supplierId, supplierName) {
    document.getElementById('review-modal-title').textContent = 'Dodaj pregled — ' + supplierName;
    document.getElementById('review-modal-supplier-id').value = supplierId;
    document.getElementById('modal_review_date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('modal_findings').value = '';
    document.getElementById('modal_reviewed_by').value = '';
    document.getElementById('review-modal-overlay').classList.add('is-open');
}

function closeReviewModal() {
    document.getElementById('review-modal-overlay').classList.remove('is-open');
}

function openHelpFromReviewModal() {
    closeReviewModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeSupplierModal();
        closeReviewModal();
    }
});
</script>
