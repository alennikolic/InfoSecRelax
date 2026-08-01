<?php
/**
 * src/modules/fizicka-bezbednost.php
 *
 * Aneks A.7: Fizička bezbednost - popis fizičkih lokacija (kancelarija,
 * data centara i sl.) i osnovnih kontrola nad njima (perimetar,
 * kontrola ulaska, video nadzor).
 *
 * Prost CRUD, isti obrazac kao uloge.php/sredstva.php - samo jedna
 * tabela, bez ugnježdene dece. equipment.location_id (iz šeme) čeka
 * budući equipment modul, koji još ne postoji.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

// --- Dodavanje lokacije ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $name                 = trim($_POST['name'] ?? '');
    $address              = trim($_POST['address'] ?? '');
    $perimeterDescription = trim($_POST['perimeter_description'] ?? '');
    $entryControlMethod   = trim($_POST['entry_control_method'] ?? '');
    $hasMonitoring        = isset($_POST['has_monitoring']) ? 1 : 0;

    if ($name === '') {
        $errors[] = 'Naziv lokacije je obavezan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO physical_locations
                (organization_id, name, address, perimeter_description, entry_control_method, has_monitoring)
             VALUES
                (:org_id, :name, :address, :perimeter_description, :entry_control_method, :has_monitoring)'
        );
        $stmt->execute([
            'org_id'                => $organizationId,
            'name'                  => $name,
            'address'               => $address !== '' ? $address : null,
            'perimeter_description' => $perimeterDescription !== '' ? $perimeterDescription : null,
            'entry_control_method'  => $entryControlMethod !== '' ? $entryControlMethod : null,
            'has_monitoring'        => $hasMonitoring,
        ]);

        header('Location: ?page=fizicka-bezbednost');
        exit;
    }
}

// --- Brisanje lokacije ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM physical_locations WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=fizicka-bezbednost');
    exit;
}

// --- Učitavanje lokacija ---
$stmt = $pdo->prepare('SELECT * FROM physical_locations WHERE organization_id = :org_id ORDER BY name');
$stmt->execute(['org_id' => $organizationId]);
$allLocations = $stmt->fetchAll();
?>

<p class="module-intro">
    Aneks A.7 traži kontrolu fizičkih prostora u kojima se obrađuju ili čuvaju
    informacije - perimetar, ko sme da uđe i kako, i da li postoji video nadzor.
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
        <label for="name">Naziv lokacije</label>
        <input type="text" name="name" id="name" required placeholder="npr. Kancelarija u Beogradu">
    </div>

    <div class="form-row">
        <label for="address">Adresa (opciono)</label>
        <input type="text" name="address" id="address" placeholder="npr. Bulevar Kralja Aleksandra 1, Beograd">
    </div>

    <div class="form-row">
        <label for="perimeter_description">Opis perimetra (opciono)</label>
        <textarea name="perimeter_description" id="perimeter_description" rows="2"
            placeholder="npr. Kancelarija na trećem spratu poslovne zgrade sa recepcijom u prizemlju."></textarea>
    </div>

    <div class="form-row">
        <label for="entry_control_method">Kontrola ulaska (opciono)</label>
        <input type="text" name="entry_control_method" id="entry_control_method"
            placeholder="npr. Elektronska brava sa karticama zaposlenih">
    </div>

    <div class="form-row form-row-inline">
        <label class="checkbox-label">
            <input type="checkbox" name="has_monitoring" value="1">
            Postoji video nadzor (A.7.4)
        </label>
    </div>

    <button type="submit" class="btn-primary">Dodaj lokaciju</button>
</form>

<?php if (empty($allLocations)): ?>
    <p class="empty-state">Još uvek nema unetih lokacija.</p>
<?php else: ?>
    <?php foreach ($allLocations as $location): ?>
        <?php include __DIR__ . '/../includes/location-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
