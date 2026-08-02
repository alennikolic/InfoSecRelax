<?php
/**
 * src/modules/fizicka-bezbednost.php
 *
 * Aneks A.7: Fizička bezbednost - popis fizičkih lokacija (kancelarija,
 * data centara i sl.) i osnovnih kontrola nad njima (perimetar,
 * kontrola ulaska, video nadzor).
 *
 * Prost CRUD (dodaj/uredi/prikaži/obriši), isti obrazac kao
 * uloge.php/sredstva.php - samo jedna tabela, bez ugnježdene dece.
 * equipment.location_id (iz šeme) čeka budući equipment modul, koji
 * još ne postoji.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'fizicka-bezbednost';

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

// --- Ažuriranje postojeće lokacije ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id                   = (int) ($_POST['id'] ?? 0);
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
            'UPDATE physical_locations
             SET name = :name, address = :address, perimeter_description = :perimeter_description,
                 entry_control_method = :entry_control_method, has_monitoring = :has_monitoring
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'name'                  => $name,
            'address'               => $address !== '' ? $address : null,
            'perimeter_description' => $perimeterDescription !== '' ? $perimeterDescription : null,
            'entry_control_method'  => $entryControlMethod !== '' ? $entryControlMethod : null,
            'has_monitoring'        => $hasMonitoring,
            'id'                    => $id,
            'org_id'                => $organizationId,
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
    <button type="button" class="btn-primary" onclick="openAddLocationModal()">+ Dodaj lokaciju</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allLocations)): ?>
    <p class="empty-state">Još uvek nema unetih lokacija.</p>
<?php else: ?>
    <?php foreach ($allLocations as $location): ?>
        <?php include __DIR__ . '/../includes/location-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="location-modal-overlay" onclick="closeLocationModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="location-modal-title">Dodaj lokaciju</span>
            <button type="button" class="modal-close" onclick="closeLocationModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="location-modal-form">
            <input type="hidden" name="action" id="location-modal-action" value="add">
            <input type="hidden" name="id" id="location-modal-id" value="">

            <div class="form-row">
                <label for="modal_name">Naziv lokacije</label>
                <input type="text" name="name" id="modal_name" required placeholder="npr. Kancelarija u Beogradu">
            </div>

            <div class="form-row">
                <label for="modal_address">Adresa (opciono)</label>
                <input type="text" name="address" id="modal_address" placeholder="npr. Bulevar Kralja Aleksandra 1, Beograd">
            </div>

            <div class="form-row">
                <label for="modal_perimeter_description">Opis perimetra (opciono)</label>
                <textarea name="perimeter_description" id="modal_perimeter_description" rows="2"
                    placeholder="npr. Kancelarija na trećem spratu poslovne zgrade sa recepcijom u prizemlju."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_entry_control_method">Kontrola ulaska (opciono)</label>
                <input type="text" name="entry_control_method" id="modal_entry_control_method"
                    placeholder="npr. Elektronska brava sa karticama zaposlenih">
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="has_monitoring" id="modal_has_monitoring" value="1">
                    Postoji video nadzor (A.7.4)
                </label>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromLocationModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeLocationModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddLocationModal() {
    document.getElementById('location-modal-title').textContent = 'Dodaj lokaciju';
    document.getElementById('location-modal-action').value = 'add';
    document.getElementById('location-modal-id').value = '';
    document.getElementById('modal_name').value = '';
    document.getElementById('modal_address').value = '';
    document.getElementById('modal_perimeter_description').value = '';
    document.getElementById('modal_entry_control_method').value = '';
    document.getElementById('modal_has_monitoring').checked = false;
    document.getElementById('location-modal-overlay').classList.add('is-open');
}

function openEditLocationModal(location) {
    document.getElementById('location-modal-title').textContent = 'Uredi lokaciju';
    document.getElementById('location-modal-action').value = 'update';
    document.getElementById('location-modal-id').value = location.id;
    document.getElementById('modal_name').value = location.name;
    document.getElementById('modal_address').value = location.address;
    document.getElementById('modal_perimeter_description').value = location.perimeter_description;
    document.getElementById('modal_entry_control_method').value = location.entry_control_method;
    document.getElementById('modal_has_monitoring').checked = location.has_monitoring;
    document.getElementById('location-modal-overlay').classList.add('is-open');
}

function closeLocationModal() {
    document.getElementById('location-modal-overlay').classList.remove('is-open');
}

function openHelpFromLocationModal() {
    closeLocationModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeLocationModal();
    }
});
</script>
