<?php
/**
 * src/modules/sredstva.php
 *
 * A.5.9: Popis informacija i pridruženih sredstava.
 *
 * Prost CRUD (dodaj/uredi/prikaži/obriši), isti obrazac kao uloge.php -
 * toolbar sa Pomoć desno, modal za dodavanje i uređivanje. Dropdown za
 * vlasnika (owner_id) povlači samo aktivne osobe iz personnel, iz istog
 * razloga kao nosilac uloge tamo. Ovaj popis je Korak 2 iz procene
 * rizika: modul procena-rizika.php kasnije vezuje risks.asset_id za
 * redove iz ove tabele, pa je važno da postoji pre njega.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'sredstva';

$errors = [];

$validAssetTypes      = ['informacija', 'hardver', 'softver', 'usluga', 'ljudi'];
$validClassifications = ['javno', 'interno', 'poverljivo', 'strogo_poverljivo'];

// --- Dodavanje novog sredstva ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name           = trim($_POST['name'] ?? '');
    $assetType      = $_POST['asset_type'] ?? '';
    $description    = trim($_POST['description'] ?? '');
    $ownerId        = trim($_POST['owner_id'] ?? '');
    $classification = $_POST['classification'] ?? 'interno';

    if ($name === '') {
        $errors[] = 'Naziv sredstva je obavezan.';
    }
    if (!in_array($assetType, $validAssetTypes, true)) {
        $errors[] = 'Izaberite vrstu sredstva.';
    }
    if (!in_array($classification, $validClassifications, true)) {
        $errors[] = 'Izaberite klasifikaciju.';
    }

    // Vlasnik, ako je izabran, mora stvarno postojati u ovoj organizaciji -
    // sprečava dodelu preko izmenjenog POST-a osobi iz tuđe organizacije.
    $ownerIdValue = null;
    if ($ownerId !== '') {
        $ownerIdValue = (int) $ownerId;
        $personCheck = $pdo->prepare(
            'SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id'
        );
        $personCheck->execute(['id' => $ownerIdValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani vlasnik nije pronađen.';
            $ownerIdValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO assets (organization_id, name, asset_type, description, owner_id, classification)
             VALUES (:org_id, :name, :asset_type, :description, :owner_id, :classification)'
        );
        $stmt->execute([
            'org_id'         => $organizationId,
            'name'           => $name,
            'asset_type'     => $assetType,
            'description'    => $description !== '' ? $description : null,
            'owner_id'       => $ownerIdValue,
            'classification' => $classification,
        ]);

        header('Location: ?page=sredstva');
        exit;
    }
}

// --- Ažuriranje postojećeg sredstva ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id             = (int) ($_POST['id'] ?? 0);
    $name           = trim($_POST['name'] ?? '');
    $assetType      = $_POST['asset_type'] ?? '';
    $description    = trim($_POST['description'] ?? '');
    $ownerId        = trim($_POST['owner_id'] ?? '');
    $classification = $_POST['classification'] ?? 'interno';

    if ($name === '') {
        $errors[] = 'Naziv sredstva je obavezan.';
    }
    if (!in_array($assetType, $validAssetTypes, true)) {
        $errors[] = 'Izaberite vrstu sredstva.';
    }
    if (!in_array($classification, $validClassifications, true)) {
        $errors[] = 'Izaberite klasifikaciju.';
    }

    $ownerIdValue = null;
    if ($ownerId !== '') {
        $ownerIdValue = (int) $ownerId;
        $personCheck = $pdo->prepare(
            'SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id'
        );
        $personCheck->execute(['id' => $ownerIdValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani vlasnik nije pronađen.';
            $ownerIdValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE assets
             SET name = :name, asset_type = :asset_type, description = :description,
                 owner_id = :owner_id, classification = :classification
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'name'           => $name,
            'asset_type'     => $assetType,
            'description'    => $description !== '' ? $description : null,
            'owner_id'       => $ownerIdValue,
            'classification' => $classification,
            'id'             => $id,
            'org_id'         => $organizationId,
        ]);

        header('Location: ?page=sredstva');
        exit;
    }
}

// --- Brisanje sredstva ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM assets WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=sredstva');
    exit;
}

// --- Aktivne osobe za dropdown vlasnika ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje sredstava (sa imenom vlasnika preko LEFT JOIN-a) ---
$assetsStmt = $pdo->prepare(
    'SELECT a.*, p.full_name AS owner_name
     FROM assets a
     LEFT JOIN personnel p ON p.id = a.owner_id
     WHERE a.organization_id = :org_id
     ORDER BY a.asset_type, a.name'
);
$assetsStmt->execute(['org_id' => $organizationId]);
$allAssets = $assetsStmt->fetchAll();

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
    <button type="button" class="btn-primary" onclick="openAddAssetModal()">+ Dodaj sredstvo</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allAssets)): ?>
    <p class="empty-state">Još uvek nema unetih sredstava.</p>
<?php else: ?>
    <?php foreach ($allAssets as $asset): ?>
        <?php include __DIR__ . '/../includes/asset-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="asset-modal-overlay" onclick="closeAssetModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="asset-modal-title">Dodaj sredstvo</span>
            <button type="button" class="modal-close" onclick="closeAssetModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="asset-modal-form">
            <input type="hidden" name="action" id="asset-modal-action" value="add">
            <input type="hidden" name="id" id="asset-modal-id" value="">

            <div class="form-row">
                <label for="modal_name">Naziv sredstva</label>
                <input type="text" name="name" id="modal_name" required
                    placeholder="npr. Baza podataka klijenata, Laptop računovođe, Nalog za e-mail hosting">
            </div>

            <div class="form-row">
                <label for="modal_asset_type">Vrsta sredstva</label>
                <select name="asset_type" id="modal_asset_type" required>
                    <option value="">Izaberite...</option>
                    <option value="informacija">Informacija</option>
                    <option value="hardver">Hardver</option>
                    <option value="softver">Softver</option>
                    <option value="usluga">Usluga</option>
                    <option value="ljudi">Ljudi</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_description">Opis (opciono)</label>
                <textarea name="description" id="modal_description" rows="2"
                    placeholder="npr. Sadrži lične podatke klijenata, hostovana u cloud-u kod eksternog dobavljača."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_owner_id">Vlasnik sredstva (opciono)</label>
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
                <label for="modal_classification">Klasifikacija</label>
                <select name="classification" id="modal_classification">
                    <option value="javno">Javno</option>
                    <option value="interno">Interno</option>
                    <option value="poverljivo">Poverljivo</option>
                    <option value="strogo_poverljivo">Strogo poverljivo</option>
                </select>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromAssetModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeAssetModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddAssetModal() {
    document.getElementById('asset-modal-title').textContent = 'Dodaj sredstvo';
    document.getElementById('asset-modal-action').value = 'add';
    document.getElementById('asset-modal-id').value = '';
    document.getElementById('modal_name').value = '';
    document.getElementById('modal_asset_type').value = '';
    document.getElementById('modal_description').value = '';
    document.getElementById('modal_owner_id').value = '';
    document.getElementById('modal_classification').value = 'interno';
    document.getElementById('asset-modal-overlay').classList.add('is-open');
}

function openEditAssetModal(asset) {
    document.getElementById('asset-modal-title').textContent = 'Uredi sredstvo';
    document.getElementById('asset-modal-action').value = 'update';
    document.getElementById('asset-modal-id').value = asset.id;
    document.getElementById('modal_name').value = asset.name;
    document.getElementById('modal_asset_type').value = asset.asset_type;
    document.getElementById('modal_description').value = asset.description;
    document.getElementById('modal_owner_id').value = asset.owner_id;
    document.getElementById('modal_classification').value = asset.classification;
    document.getElementById('asset-modal-overlay').classList.add('is-open');
}

function closeAssetModal() {
    document.getElementById('asset-modal-overlay').classList.remove('is-open');
}

function openHelpFromAssetModal() {
    closeAssetModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeAssetModal();
    }
});
</script>
