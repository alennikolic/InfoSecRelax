<?php
/**
 * src/modules/procena-rizika.php
 *
 * Klauzula 6.1.2 (procena rizika) i 6.1.3 (tretman rizika) - nema
 * posebne stavke menija za 6.1.3, pa su obe kombinovane ovde: svaki
 * rizik nosi svoje mere tretmana, isti odnos roditelj-dete kao strana
 * i njeni zahtevi u zainteresovane-strane.php.
 *
 * Najsloženiji modul do sada, iz dva razloga:
 *
 * 1) risk_criteria je jedan red po organizaciji (podešavanje, ne
 *    lista) - postoji ensureCurrentRiskCriteria() ispod, analogno
 *    ensureDefaultOrganization() iz database.php, samo ovde lokalno
 *    jer je specifično za ovaj modul.
 *
 * 2) risk_level NIJE generisana kolona (za razliku od risk_score) -
 *    aplikacija ga računa iz risk_score i risk_criteria pragova
 *    (calculateRiskLevel ispod). Kad se kriterijumi promene, SVI
 *    postojeći rizici se odmah preračunaju (jedan UPDATE), da
 *    risk_level nikad ne ostane zastareo u odnosu na trenutne pragove.
 *
 * Ograničenje koje je svesno ostavljeno: ako se likelihood_scale_max
 * ili impact_scale_max naknadno smanje ispod već unetih vrednosti
 * likelihood/impact na postojećim rizicima, ti redovi se ne
 * validiraju niti ispravljaju retroaktivno.
 *
 * Status mere tretmana ovde ima samo jednu radnju ("Označi kao
 * sprovedeno") - "u_toku" i "ponovo_otvoreno" nisu dostupni kroz UI.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

/**
 * Osigurava da za organizaciju postoji tačno jedan red u risk_criteria
 * (sa podrazumevanim vrednostima iz šeme ako se prvi put poziva) - isti
 * princip kao ensureDefaultOrganization(), samo za ovu tabelu.
 */
function ensureCurrentRiskCriteria(PDO $pdo, int $organizationId): array
{
    $pdo->prepare('INSERT IGNORE INTO risk_criteria (organization_id) VALUES (:org_id)')
        ->execute(['org_id' => $organizationId]);

    $stmt = $pdo->prepare('SELECT * FROM risk_criteria WHERE organization_id = :org_id');
    $stmt->execute(['org_id' => $organizationId]);

    return $stmt->fetch();
}

/**
 * Nivo rizika iz proizvoda verovatnoća × uticaj, prema pragovima koje
 * je organizacija sama definisala (Klauzula 6.1.2, Korak 1).
 */
function calculateRiskLevel(int $riskScore, array $criteria): string
{
    if ($riskScore <= (int) $criteria['low_threshold_max']) {
        return 'nizak';
    }
    if ($riskScore <= (int) $criteria['medium_threshold_max']) {
        return 'srednji';
    }
    return 'visok';
}

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);
$riskCriteria = ensureCurrentRiskCriteria($pdo, $organizationId);

$errors = [];

// --- Čuvanje kriterijuma procene (Korak 1) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_criteria') {
    $likelihoodScaleMax = (int) ($_POST['likelihood_scale_max'] ?? 0);
    $impactScaleMax     = (int) ($_POST['impact_scale_max'] ?? 0);
    $lowThresholdMax    = (int) ($_POST['low_threshold_max'] ?? 0);
    $mediumThresholdMax = (int) ($_POST['medium_threshold_max'] ?? 0);
    $notes              = trim($_POST['notes'] ?? '');

    if ($likelihoodScaleMax < 1 || $impactScaleMax < 1) {
        $errors[] = 'Skale verovatnoće i uticaja moraju biti bar 1.';
    }
    if ($lowThresholdMax < 1) {
        $errors[] = 'Granica za nizak rizik mora biti bar 1.';
    }
    if ($mediumThresholdMax <= $lowThresholdMax) {
        $errors[] = 'Granica za srednji rizik mora biti veća od granice za nizak rizik.';
    }

    if (empty($errors)) {
        $pdo->prepare(
            'UPDATE risk_criteria
             SET likelihood_scale_max = :likelihood_max,
                 impact_scale_max = :impact_max,
                 low_threshold_max = :low_max,
                 medium_threshold_max = :medium_max,
                 notes = :notes
             WHERE organization_id = :org_id'
        )->execute([
            'likelihood_max' => $likelihoodScaleMax,
            'impact_max'     => $impactScaleMax,
            'low_max'        => $lowThresholdMax,
            'medium_max'     => $mediumThresholdMax,
            'notes'          => $notes !== '' ? $notes : null,
            'org_id'         => $organizationId,
        ]);

        // Postojeći rizici su računati po starim pragovima - preračunaj ih
        // odmah, u jednom upitu, da risk_level nikad ne bude zastareo.
        $pdo->prepare(
            "UPDATE risks
             SET risk_level = CASE
                WHEN risk_score <= :low_max THEN 'nizak'
                WHEN risk_score <= :medium_max THEN 'srednji'
                ELSE 'visok'
             END
             WHERE organization_id = :org_id"
        )->execute([
            'low_max'    => $lowThresholdMax,
            'medium_max' => $mediumThresholdMax,
            'org_id'     => $organizationId,
        ]);

        header('Location: ?page=procena-rizika');
        exit;
    }
}

// --- Dodavanje novog rizika ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_risk') {
    $title                    = trim($_POST['title'] ?? '');
    $threatDescription        = trim($_POST['threat_description'] ?? '');
    $vulnerabilityDescription = trim($_POST['vulnerability_description'] ?? '');
    $likelihood               = (int) ($_POST['likelihood'] ?? 0);
    $impact                   = (int) ($_POST['impact'] ?? 0);
    $assetId                  = trim($_POST['asset_id'] ?? '');
    $identifiedAt             = trim($_POST['identified_at'] ?? '');
    $reviewTrigger            = $_POST['review_trigger'] ?? 'godisnji_ciklus';

    $validReviewTriggers = ['godisnji_ciklus', 'incident', 'promena', 'ostalo'];

    if ($title === '') {
        $errors[] = 'Naziv rizika je obavezan.';
    }
    if ($threatDescription === '') {
        $errors[] = 'Opis pretnje je obavezan.';
    }
    if ($vulnerabilityDescription === '') {
        $errors[] = 'Opis ranjivosti je obavezan.';
    }
    if ($likelihood < 1 || $likelihood > (int) $riskCriteria['likelihood_scale_max']) {
        $errors[] = 'Verovatnoća mora biti između 1 i ' . (int) $riskCriteria['likelihood_scale_max'] . '.';
    }
    if ($impact < 1 || $impact > (int) $riskCriteria['impact_scale_max']) {
        $errors[] = 'Uticaj mora biti između 1 i ' . (int) $riskCriteria['impact_scale_max'] . '.';
    }
    if ($identifiedAt === '') {
        $errors[] = 'Datum identifikacije je obavezan.';
    }
    if (!in_array($reviewTrigger, $validReviewTriggers, true)) {
        $errors[] = 'Izaberite razlog pregleda.';
    }

    // Sredstvo, ako je izabrano, mora stvarno postojati u ovoj organizaciji.
    $assetIdValue = null;
    if ($assetId !== '') {
        $assetIdValue = (int) $assetId;
        $assetCheck = $pdo->prepare('SELECT id FROM assets WHERE id = :id AND organization_id = :org_id');
        $assetCheck->execute(['id' => $assetIdValue, 'org_id' => $organizationId]);

        if ($assetCheck->fetchColumn() === false) {
            $errors[] = 'Izabrano sredstvo nije pronađeno.';
            $assetIdValue = null;
        }
    }

    if (empty($errors)) {
        $riskScore = $likelihood * $impact;
        $riskLevel = calculateRiskLevel($riskScore, $riskCriteria);

        $stmt = $pdo->prepare(
            'INSERT INTO risks
                (organization_id, asset_id, title, threat_description, vulnerability_description,
                 likelihood, impact, risk_level, identified_at, review_trigger)
             VALUES
                (:org_id, :asset_id, :title, :threat_description, :vulnerability_description,
                 :likelihood, :impact, :risk_level, :identified_at, :review_trigger)'
        );
        $stmt->execute([
            'org_id'                    => $organizationId,
            'asset_id'                  => $assetIdValue,
            'title'                     => $title,
            'threat_description'        => $threatDescription,
            'vulnerability_description' => $vulnerabilityDescription,
            'likelihood'                => $likelihood,
            'impact'                    => $impact,
            'risk_level'                => $riskLevel,
            'identified_at'             => $identifiedAt,
            'review_trigger'            => $reviewTrigger,
        ]);

        header('Location: ?page=procena-rizika');
        exit;
    }
}

// --- Promena statusa rizika (računa se i kao pregled - postavlja last_reviewed_at) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $riskId    = (int) ($_POST['risk_id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    $validStatuses = ['otvoren', 'u_tretmanu', 'tretiran', 'prihvacen', 'zatvoren'];

    if (in_array($newStatus, $validStatuses, true)) {
        $pdo->prepare(
            'UPDATE risks SET status = :status, last_reviewed_at = CURDATE()
             WHERE id = :id AND organization_id = :org_id'
        )->execute(['status' => $newStatus, 'id' => $riskId, 'org_id' => $organizationId]);
    }

    header('Location: ?page=procena-rizika');
    exit;
}

// --- Brisanje rizika (mere tretmana se brišu kaskadno preko FK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_risk') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM risks WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=procena-rizika');
    exit;
}

// --- Dodavanje mere tretmana rizika ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_treatment') {
    $riskId          = (int) ($_POST['risk_id'] ?? 0);
    $treatmentOption = $_POST['treatment_option'] ?? '';
    $description     = trim($_POST['description'] ?? '');
    $ownerId         = trim($_POST['owner_id'] ?? '');
    $dueDate         = trim($_POST['due_date'] ?? '');

    $validTreatmentOptions = ['smanjiti', 'izbeci', 'preneti', 'prihvatiti'];

    $riskCheck = $pdo->prepare('SELECT id FROM risks WHERE id = :id AND organization_id = :org_id');
    $riskCheck->execute(['id' => $riskId, 'org_id' => $organizationId]);

    if ($riskCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznat rizik.';
    }
    if (!in_array($treatmentOption, $validTreatmentOptions, true)) {
        $errors[] = 'Izaberite način tretmana.';
    }
    if ($description === '') {
        $errors[] = 'Opis mere je obavezan.';
    }

    $ownerIdValue = null;
    if ($ownerId !== '') {
        $ownerIdValue = (int) $ownerId;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $ownerIdValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani nosilac mere nije pronađen.';
            $ownerIdValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO risk_treatments (risk_id, treatment_option, description, owner_id, due_date)
             VALUES (:risk_id, :treatment_option, :description, :owner_id, :due_date)'
        );
        $stmt->execute([
            'risk_id'          => $riskId,
            'treatment_option' => $treatmentOption,
            'description'      => $description,
            'owner_id'         => $ownerIdValue,
            'due_date'         => $dueDate !== '' ? $dueDate : null,
        ]);

        header('Location: ?page=procena-rizika');
        exit;
    }
}

// --- Označavanje mere kao sprovedene ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete_treatment') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        "UPDATE risk_treatments t
         INNER JOIN risks r ON r.id = t.risk_id
         SET t.status = 'sprovedeno', t.completed_at = COALESCE(t.completed_at, CURDATE())
         WHERE t.id = :id AND r.organization_id = :org_id"
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=procena-rizika');
    exit;
}

// --- Brisanje mere tretmana ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_treatment') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE t FROM risk_treatments t
         INNER JOIN risks r ON r.id = t.risk_id
         WHERE t.id = :id AND r.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=procena-rizika');
    exit;
}

// --- Sredstva za dropdown ---
$assetsStmt = $pdo->prepare('SELECT id, name FROM assets WHERE organization_id = :org_id ORDER BY name');
$assetsStmt->execute(['org_id' => $organizationId]);
$assetOptions = $assetsStmt->fetchAll();

// --- Aktivne osobe za dropdown nosioca mere ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje rizika (najviši skor prvi) ---
$risksStmt = $pdo->prepare(
    'SELECT r.*, a.name AS asset_name
     FROM risks r
     LEFT JOIN assets a ON a.id = r.asset_id
     WHERE r.organization_id = :org_id
     ORDER BY r.risk_score DESC, r.identified_at DESC'
);
$risksStmt->execute(['org_id' => $organizationId]);
$allRisks = $risksStmt->fetchAll();

// --- Mere tretmana za sve rizike ove organizacije, grupisane po risk_id ---
$treatmentsStmt = $pdo->prepare(
    'SELECT t.*, p.full_name AS owner_name
     FROM risk_treatments t
     INNER JOIN risks r ON r.id = t.risk_id
     LEFT JOIN personnel p ON p.id = t.owner_id
     WHERE r.organization_id = :org_id
     ORDER BY t.created_at ASC'
);
$treatmentsStmt->execute(['org_id' => $organizationId]);

$treatmentsByRisk = [];
foreach ($treatmentsStmt->fetchAll() as $treatment) {
    $treatmentsByRisk[$treatment['risk_id']][] = $treatment;
}
?>

<p class="module-intro">
    Klauzula 6.1.2 traži metodologiju procene rizika, a 6.1.3 tretman za svaki
    identifikovani rizik - obe su ovde kombinovane: prvo se podešavaju
    kriterijumi (Korak 1), zatim se vode rizici i njihove mere tretmana.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="scope-current">
    <div class="card-header-row">
        <span class="card-title">Kriterijumi procene rizika</span>
    </div>
    <p class="item-meta">
        Rizik = verovatnoća × uticaj. Ovi pragovi određuju kada je rizik nizak,
        srednji ili visok - promena ovde odmah preračunava sve već unete rizike.
    </p>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="update_criteria">

        <div class="form-row">
            <label for="likelihood_scale_max">Najveća vrednost skale verovatnoće (npr. 5 za skalu 1-5)</label>
            <input type="number" name="likelihood_scale_max" id="likelihood_scale_max" min="1" max="20"
                value="<?= (int) $riskCriteria['likelihood_scale_max'] ?>" required>
        </div>

        <div class="form-row">
            <label for="impact_scale_max">Najveća vrednost skale uticaja (npr. 5 za skalu 1-5)</label>
            <input type="number" name="impact_scale_max" id="impact_scale_max" min="1" max="20"
                value="<?= (int) $riskCriteria['impact_scale_max'] ?>" required>
        </div>

        <div class="form-row">
            <label for="low_threshold_max">Nizak rizik: proizvod do ove vrednosti</label>
            <input type="number" name="low_threshold_max" id="low_threshold_max" min="1"
                value="<?= (int) $riskCriteria['low_threshold_max'] ?>" required>
        </div>

        <div class="form-row">
            <label for="medium_threshold_max">Srednji rizik: proizvod do ove vrednosti (iznad je visok)</label>
            <input type="number" name="medium_threshold_max" id="medium_threshold_max" min="1"
                value="<?= (int) $riskCriteria['medium_threshold_max'] ?>" required>
        </div>

        <div class="form-row">
            <label for="notes">Napomena (opciono)</label>
            <textarea name="notes" id="notes" rows="2"><?= htmlspecialchars($riskCriteria['notes'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn-secondary">Sačuvaj kriterijume</button>
    </form>
</div>

<form method="post" class="factor-form">
    <input type="hidden" name="action" value="add_risk">

    <div class="form-row">
        <label for="title">Naziv rizika</label>
        <input type="text" name="title" id="title" required
            placeholder="npr. Gubitak pristupa nalogu za e-mail hosting">
    </div>

    <div class="form-row">
        <label for="threat_description">Pretnja</label>
        <textarea name="threat_description" id="threat_description" rows="2" required
            placeholder="npr. Napadač dobija pristup nalogu putem phishing napada."></textarea>
    </div>

    <div class="form-row">
        <label for="vulnerability_description">Ranjivost</label>
        <textarea name="vulnerability_description" id="vulnerability_description" rows="2" required
            placeholder="npr. Nalog nema uključenu dvofaktorsku autentifikaciju."></textarea>
    </div>

    <div class="form-row">
        <label for="asset_id">Povezano sredstvo (opciono)</label>
        <select name="asset_id" id="asset_id">
            <option value="">Nije povezano</option>
            <?php foreach ($assetOptions as $option): ?>
                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($assetOptions)): ?>
            <p class="item-meta">Nema unetih sredstava - prvo ih dodaj na stranici "Popis sredstava".</p>
        <?php endif; ?>
    </div>

    <div class="form-row">
        <label for="likelihood">Verovatnoća (1-<?= (int) $riskCriteria['likelihood_scale_max'] ?>)</label>
        <select name="likelihood" id="likelihood" required>
            <?php for ($i = 1; $i <= (int) $riskCriteria['likelihood_scale_max']; $i++): ?>
                <option value="<?= $i ?>"><?= $i ?></option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="form-row">
        <label for="impact">Uticaj (1-<?= (int) $riskCriteria['impact_scale_max'] ?>)</label>
        <select name="impact" id="impact" required>
            <?php for ($i = 1; $i <= (int) $riskCriteria['impact_scale_max']; $i++): ?>
                <option value="<?= $i ?>"><?= $i ?></option>
            <?php endfor; ?>
        </select>
    </div>

    <div class="form-row">
        <label for="identified_at">Datum identifikacije</label>
        <input type="date" name="identified_at" id="identified_at" required value="<?= date('Y-m-d') ?>">
    </div>

    <div class="form-row">
        <label for="review_trigger">Razlog pregleda</label>
        <select name="review_trigger" id="review_trigger">
            <option value="godisnji_ciklus" selected>Godišnji ciklus</option>
            <option value="incident">Incident</option>
            <option value="promena">Promena</option>
            <option value="ostalo">Ostalo</option>
        </select>
    </div>

    <button type="submit" class="btn-primary">Dodaj rizik</button>
</form>

<?php if (empty($allRisks)): ?>
    <p class="empty-state">Još uvek nema unetih rizika.</p>
<?php else: ?>
    <?php foreach ($allRisks as $risk): ?>
        <?php $treatments = $treatmentsByRisk[$risk['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/risk-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
