<?php
/**
 * src/modules/sredstva.php
 *
 * A.5.9: Popis informacija i pridruženih sredstava.
 *
 * Prost CRUD, isti obrazac kao uloge.php - dropdown za vlasnika
 * (owner_id) povlači samo aktivne osobe iz personnel, iz istog razloga
 * kao nosilac uloge tamo. Ovaj popis je Korak 2 iz procene rizika:
 * modul procena-rizika.php će kasnije vezivati risks.asset_id za redove
 * iz ove tabele, pa je važno da postoji pre njega.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

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
?>

<p class="module-intro">
    A.5.9 traži popis informacija i sredstava koja im služe kao podrška - od
    ovog popisa počinje procena rizika: svaki rizik će se kasnije vezivati za
    konkretno sredstvo, a svako sredstvo treba da ima vlasnika.
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
        <label for="name">Naziv sredstva</label>
        <input type="text" name="name" id="name" required
            placeholder="npr. Baza podataka klijenata, Laptop računovođe, Nalog za e-mail hosting">
    </div>

    <div class="form-row">
        <label for="asset_type">Vrsta sredstva</label>
        <select name="asset_type" id="asset_type" required>
            <option value="">Izaberite...</option>
            <option value="informacija">Informacija</option>
            <option value="hardver">Hardver</option>
            <option value="softver">Softver</option>
            <option value="usluga">Usluga</option>
            <option value="ljudi">Ljudi</option>
        </select>
    </div>

    <div class="form-row">
        <label for="description">Opis (opciono)</label>
        <textarea name="description" id="description" rows="2"
            placeholder="npr. Sadrži lične podatke klijenata, hostovana u cloud-u kod eksternog dobavljača."></textarea>
    </div>

    <div class="form-row">
        <label for="owner_id">Vlasnik sredstva (opciono)</label>
        <select name="owner_id" id="owner_id">
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
        <label for="classification">Klasifikacija</label>
        <select name="classification" id="classification">
            <option value="javno">Javno</option>
            <option value="interno" selected>Interno</option>
            <option value="poverljivo">Poverljivo</option>
            <option value="strogo_poverljivo">Strogo poverljivo</option>
        </select>
    </div>

    <button type="submit" class="btn-primary">Dodaj sredstvo</button>
</form>

<?php if (empty($allAssets)): ?>
    <p class="empty-state">Još uvek nema unetih sredstava.</p>
<?php else: ?>
    <?php foreach ($allAssets as $asset): ?>
        <?php include __DIR__ . '/../includes/asset-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
