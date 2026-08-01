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
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

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
?>

<p class="module-intro">
    Klauzula 9.1 traži da se prati i meri koliko dobro ISMS zaista funkcioniše -
    definiši pokazatelj, njegov cilj i koliko često se meri, pa unosi merenja
    kroz vreme.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="factor-form">
    <input type="hidden" name="action" value="add_metric">

    <div class="form-row">
        <label for="name">Naziv pokazatelja</label>
        <input type="text" name="name" id="name" required
            placeholder="npr. Vreme ukidanja pristupa nakon odlaska zaposlenog">
    </div>

    <div class="form-row">
        <label for="description">Opis (opciono)</label>
        <textarea name="description" id="description" rows="2"
            placeholder="npr. Broj sati od datuma prestanka rada do ukidanja svih pristupa."></textarea>
    </div>

    <div class="form-row">
        <label for="unit">Jedinica mere (opciono)</label>
        <input type="text" name="unit" id="unit" placeholder="npr. sati, procenat, broj">
    </div>

    <div class="form-row">
        <label for="target_value">Ciljna vrednost (opciono)</label>
        <input type="number" name="target_value" id="target_value" step="0.01">
    </div>

    <div class="form-row">
        <label for="measurement_frequency">Učestalost merenja (opciono)</label>
        <input type="text" name="measurement_frequency" id="measurement_frequency" placeholder="npr. mesečno, kvartalno">
    </div>

    <button type="submit" class="btn-primary">Dodaj pokazatelj</button>
</form>

<?php if (empty($allMetrics)): ?>
    <p class="empty-state">Još uvek nema unetih pokazatelja.</p>
<?php else: ?>
    <?php foreach ($allMetrics as $metric): ?>
        <?php $measurements = $measurementsByMetric[$metric['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/metric-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
