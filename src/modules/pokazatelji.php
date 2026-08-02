<?php
/**
 * src/modules/pokazatelji.php
 *
 * Klauzula 9.1: Praćenje, merenje, analiza i ocenjivanje.
 *
 * metrics (definicija pokazatelja) -> metric_measurements (pojedinačna
 * merenja kroz vreme) - isti roditelj-dete obrazac kao svuda do sada.
 * Namerno se ne pokušava automatski oceniti da li je poslednje merenje
 * "dobro" ili "loše" u odnosu na cilj - šema ne beleži da li je za dati
 * pokazatelj poželjno da vrednost bude veća ili manja od cilja, pa se
 * cilj i poslednje merenje samo prikazuju jedno pored drugog.
 *
 * Isti obrazac kao ostali moduli: toolbar sa Pomoć desno, modal za
 * dodavanje/uređivanje pokazatelja, "Dodaj merenje" kao poseban modal
 * otvoren dugmetom u kartici.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'pokazatelji';

$errors = [];

// --- Dodavanje pokazatelja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_metric') {
    $name                 = trim($_POST['name'] ?? '');
    $description          = trim($_POST['description'] ?? '');
    $unit                 = trim($_POST['unit'] ?? '');
    $targetValue          = trim($_POST['target_value'] ?? '');
    $measurementFrequency = trim($_POST['measurement_frequency'] ?? '');

    if ($name === '') {
        $errors[] = 'Naziv pokazatelja je obavezan.';
    }

    $targetValueValue = null;
    if ($targetValue !== '') {
        if (!is_numeric($targetValue)) {
            $errors[] = 'Ciljna vrednost mora biti broj.';
        } else {
            $targetValueValue = (float) $targetValue;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO metrics (organization_id, name, description, unit, target_value, measurement_frequency)
             VALUES (:org_id, :name, :description, :unit, :target_value, :measurement_frequency)'
        );
        $stmt->execute([
            'org_id'                => $organizationId,
            'name'                  => $name,
            'description'           => $description !== '' ? $description : null,
            'unit'                  => $unit !== '' ? $unit : null,
            'target_value'          => $targetValueValue,
            'measurement_frequency' => $measurementFrequency !== '' ? $measurementFrequency : null,
        ]);

        header('Location: ?page=pokazatelji');
        exit;
    }
}

// --- Ažuriranje postojećeg pokazatelja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_metric') {
    $id                   = (int) ($_POST['id'] ?? 0);
    $name                 = trim($_POST['name'] ?? '');
    $description          = trim($_POST['description'] ?? '');
    $unit                 = trim($_POST['unit'] ?? '');
    $targetValue          = trim($_POST['target_value'] ?? '');
    $measurementFrequency = trim($_POST['measurement_frequency'] ?? '');

    if ($name === '') {
        $errors[] = 'Naziv pokazatelja je obavezan.';
    }

    $targetValueValue = null;
    if ($targetValue !== '') {
        if (!is_numeric($targetValue)) {
            $errors[] = 'Ciljna vrednost mora biti broj.';
        } else {
            $targetValueValue = (float) $targetValue;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE metrics
             SET name = :name, description = :description, unit = :unit,
                 target_value = :target_value, measurement_frequency = :measurement_frequency
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'name'                  => $name,
            'description'           => $description !== '' ? $description : null,
            'unit'                  => $unit !== '' ? $unit : null,
            'target_value'          => $targetValueValue,
            'measurement_frequency' => $measurementFrequency !== '' ? $measurementFrequency : null,
            'id'                    => $id,
            'org_id'                => $organizationId,
        ]);

        header('Location: ?page=pokazatelji');
        exit;
    }
}

// --- Brisanje pokazatelja (merenja se brišu kaskadno preko FK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_metric') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM metrics WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=pokazatelji');
    exit;
}

// --- Dodavanje merenja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_measurement') {
    $metricId   = (int) ($_POST['metric_id'] ?? 0);
    $value      = trim($_POST['value'] ?? '');
    $measuredAt = trim($_POST['measured_at'] ?? '');
    $measuredBy = trim($_POST['measured_by'] ?? '');
    $notes      = trim($_POST['notes'] ?? '');

    $metricCheck = $pdo->prepare('SELECT id FROM metrics WHERE id = :id AND organization_id = :org_id');
    $metricCheck->execute(['id' => $metricId, 'org_id' => $organizationId]);

    if ($metricCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznat pokazatelj.';
    }
    if ($value === '' || !is_numeric($value)) {
        $errors[] = 'Vrednost merenja je obavezna i mora biti broj.';
    }

    $measuredByValue = null;
    if ($measuredBy !== '') {
        $measuredByValue = (int) $measuredBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $measuredByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrana osoba nije pronađena.';
            $measuredByValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO metric_measurements (metric_id, measured_at, value, measured_by, notes)
             VALUES (:metric_id, :measured_at, :value, :measured_by, :notes)'
        );
        $stmt->execute([
            'metric_id'   => $metricId,
            'measured_at' => $measuredAt !== '' ? $measuredAt : date('Y-m-d'),
            'value'       => (float) $value,
            'measured_by' => $measuredByValue,
            'notes'       => $notes !== '' ? $notes : null,
        ]);

        header('Location: ?page=pokazatelji');
        exit;
    }
}

// --- Brisanje merenja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_measurement') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE m FROM metric_measurements m
         INNER JOIN metrics me ON me.id = m.metric_id
         WHERE m.id = :id AND me.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=pokazatelji');
    exit;
}

// --- Aktivne osobe za dropdown ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje pokazatelja ---
$metricsStmt = $pdo->prepare('SELECT * FROM metrics WHERE organization_id = :org_id ORDER BY name');
$metricsStmt->execute(['org_id' => $organizationId]);
$allMetrics = $metricsStmt->fetchAll();

// --- Merenja za sve pokazatelje ove organizacije, grupisana po metric_id (najnovije prvo) ---
$measurementsStmt = $pdo->prepare(
    'SELECT ms.*, p.full_name AS measured_by_name
     FROM metric_measurements ms
     INNER JOIN metrics me ON me.id = ms.metric_id
     LEFT JOIN personnel p ON p.id = ms.measured_by
     WHERE me.organization_id = :org_id
     ORDER BY ms.measured_at DESC'
);
$measurementsStmt->execute(['org_id' => $organizationId]);

$measurementsByMetric = [];
foreach ($measurementsStmt->fetchAll() as $measurement) {
    $measurementsByMetric[$measurement['metric_id']][] = $measurement;
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
    <button type="button" class="btn-primary" onclick="openAddMetricModal()">+ Dodaj pokazatelj</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allMetrics)): ?>
    <p class="empty-state">Još uvek nema unetih pokazatelja.</p>
<?php else: ?>
    <?php foreach ($allMetrics as $metric): ?>
        <?php $measurements = $measurementsByMetric[$metric['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/metric-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="metric-modal-overlay" onclick="closeMetricModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="metric-modal-title">Dodaj pokazatelj</span>
            <button type="button" class="modal-close" onclick="closeMetricModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="metric-modal-form">
            <input type="hidden" name="action" id="metric-modal-action" value="add_metric">
            <input type="hidden" name="id" id="metric-modal-id" value="">

            <div class="form-row">
                <label for="modal_name">Naziv pokazatelja</label>
                <input type="text" name="name" id="modal_name" required
                    placeholder="npr. Vreme ukidanja pristupa nakon odlaska zaposlenog">
            </div>

            <div class="form-row">
                <label for="modal_description">Opis (opciono)</label>
                <textarea name="description" id="modal_description" rows="2"
                    placeholder="npr. Broj sati od datuma prestanka rada do ukidanja svih pristupa."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_unit">Jedinica mere (opciono)</label>
                <input type="text" name="unit" id="modal_unit" placeholder="npr. sati, procenat, broj">
            </div>

            <div class="form-row">
                <label for="modal_target_value">Ciljna vrednost (opciono)</label>
                <input type="number" name="target_value" id="modal_target_value" step="0.01">
            </div>

            <div class="form-row">
                <label for="modal_measurement_frequency">Učestalost merenja (opciono)</label>
                <input type="text" name="measurement_frequency" id="modal_measurement_frequency" placeholder="npr. mesečno, kvartalno">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromMetricModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeMetricModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="measurement-modal-overlay" onclick="closeMeasurementModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="measurement-modal-title">Dodaj merenje</span>
            <button type="button" class="modal-close" onclick="closeMeasurementModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="add_measurement">
            <input type="hidden" name="metric_id" id="measurement-modal-metric-id" value="">

            <div class="form-row">
                <label for="modal_value">Vrednost</label>
                <input type="number" name="value" id="modal_value" step="0.01" required>
            </div>

            <div class="form-row">
                <label for="modal_measured_at">Datum merenja</label>
                <input type="date" name="measured_at" id="modal_measured_at">
            </div>

            <div class="form-row">
                <label for="modal_measured_by">Izmerio (opciono)</label>
                <select name="measured_by" id="modal_measured_by">
                    <option value="">Nije dodeljen</option>
                    <?php foreach ($activePersonnelOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_measurement_notes">Napomena (opciono)</label>
                <textarea name="notes" id="modal_measurement_notes" rows="2"></textarea>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromMeasurementModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeMeasurementModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddMetricModal() {
    document.getElementById('metric-modal-title').textContent = 'Dodaj pokazatelj';
    document.getElementById('metric-modal-action').value = 'add_metric';
    document.getElementById('metric-modal-id').value = '';
    document.getElementById('modal_name').value = '';
    document.getElementById('modal_description').value = '';
    document.getElementById('modal_unit').value = '';
    document.getElementById('modal_target_value').value = '';
    document.getElementById('modal_measurement_frequency').value = '';
    document.getElementById('metric-modal-overlay').classList.add('is-open');
}

function openEditMetricModal(metric) {
    document.getElementById('metric-modal-title').textContent = 'Uredi pokazatelj';
    document.getElementById('metric-modal-action').value = 'update_metric';
    document.getElementById('metric-modal-id').value = metric.id;
    document.getElementById('modal_name').value = metric.name;
    document.getElementById('modal_description').value = metric.description;
    document.getElementById('modal_unit').value = metric.unit;
    document.getElementById('modal_target_value').value = metric.target_value;
    document.getElementById('modal_measurement_frequency').value = metric.measurement_frequency;
    document.getElementById('metric-modal-overlay').classList.add('is-open');
}

function closeMetricModal() {
    document.getElementById('metric-modal-overlay').classList.remove('is-open');
}

function openHelpFromMetricModal() {
    closeMetricModal();
    openHelpModal();
}

function openMeasurementModal(metricId, metricName) {
    document.getElementById('measurement-modal-title').textContent = 'Dodaj merenje — ' + metricName;
    document.getElementById('measurement-modal-metric-id').value = metricId;
    document.getElementById('modal_value').value = '';
    document.getElementById('modal_measured_at').value = '<?= date('Y-m-d') ?>';
    document.getElementById('modal_measured_by').value = '';
    document.getElementById('modal_measurement_notes').value = '';
    document.getElementById('measurement-modal-overlay').classList.add('is-open');
}

function closeMeasurementModal() {
    document.getElementById('measurement-modal-overlay').classList.remove('is-open');
}

function openHelpFromMeasurementModal() {
    closeMeasurementModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeMetricModal();
        closeMeasurementModal();
    }
});
</script>
