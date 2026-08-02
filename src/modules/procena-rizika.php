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
 *    jer je specifično za ovaj modul. Trenutne vrednosti su uvek
 *    vidljive na vrhu stranice (sažetak), a sama forma za izmenu je u
 *    posebnom modalu ("Kriterijumi procene" dugme) - isti princip kao
 *    trenutna verzija obima na ?page=obim.
 *
 * 2) risk_level NIJE generisana kolona (za razliku od risk_score) -
 *    aplikacija ga računa iz risk_score i risk_criteria pragova
 *    (calculateRiskLevel ispod). Kad se kriterijumi promene, SVI
 *    postojeći rizici se odmah preračunaju (jedan UPDATE), da
 *    risk_level nikad ne ostane zastareo u odnosu na trenutne pragove.
 *    Isto tako, kad se rizik uredi (nova verovatnoća/uticaj), njegov
 *    risk_level se preračunava odmah pri čuvanju.
 *
 * Ograničenje koje je svesno ostavljeno: ako se likelihood_scale_max
 * ili impact_scale_max naknadno smanje ispod već unetih vrednosti
 * likelihood/impact na postojećim rizicima, ti redovi se ne
 * validiraju niti ispravljaju retroaktivno.
 *
 * "Uredi" rizika NE menja status/poslednji pregled - to ostaje
 * isključivo kroz "Ažuriraj status" u kartici, koje i dalje ima samo
 * jednu radnju na meri tretmana ("Označi kao sprovedeno") - "u_toku" i
 * "ponovo_otvoreno" nisu dostupni kroz UI.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

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

$pageSlug = 'procena-rizika';

$errors = [];

$validReviewTriggers = ['godisnji_ciklus', 'incident', 'promena', 'ostalo'];

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

// --- Ažuriranje postojećeg rizika ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_risk') {
    $id                       = (int) ($_POST['id'] ?? 0);
    $title                    = trim($_POST['title'] ?? '');
    $threatDescription        = trim($_POST['threat_description'] ?? '');
    $vulnerabilityDescription = trim($_POST['vulnerability_description'] ?? '');
    $likelihood               = (int) ($_POST['likelihood'] ?? 0);
    $impact                   = (int) ($_POST['impact'] ?? 0);
    $assetId                  = trim($_POST['asset_id'] ?? '');
    $identifiedAt             = trim($_POST['identified_at'] ?? '');
    $reviewTrigger            = $_POST['review_trigger'] ?? 'godisnji_ciklus';

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
            'UPDATE risks
             SET asset_id = :asset_id, title = :title, threat_description = :threat_description,
                 vulnerability_description = :vulnerability_description, likelihood = :likelihood,
                 impact = :impact, risk_level = :risk_level, identified_at = :identified_at,
                 review_trigger = :review_trigger
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'asset_id'                  => $assetIdValue,
            'title'                     => $title,
            'threat_description'        => $threatDescription,
            'vulnerability_description' => $vulnerabilityDescription,
            'likelihood'                => $likelihood,
            'impact'                    => $impact,
            'risk_level'                => $riskLevel,
            'identified_at'             => $identifiedAt,
            'review_trigger'            => $reviewTrigger,
            'id'                        => $id,
            'org_id'                    => $organizationId,
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
    <div class="button-group">
        <button type="button" class="btn-primary" onclick="openAddRiskModal()">+ Dodaj rizik</button>
        <button type="button" class="btn-secondary" onclick="openCriteriaModal()">Kriterijumi procene</button>
    </div>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<div class="scope-current">
    <div class="card-header-row">
        <span class="card-title">Kriterijumi procene rizika</span>
    </div>
    <p class="item-meta">
        Verovatnoća 1-<?= (int) $riskCriteria['likelihood_scale_max'] ?>
        × Uticaj 1-<?= (int) $riskCriteria['impact_scale_max'] ?>
        · Nizak do <?= (int) $riskCriteria['low_threshold_max'] ?>
        · Srednji do <?= (int) $riskCriteria['medium_threshold_max'] ?>
        · iznad toga Visok
    </p>
    <?php if (!empty($riskCriteria['notes'])): ?>
        <p class="item-meta"><?= nl2br(htmlspecialchars($riskCriteria['notes'])) ?></p>
    <?php endif; ?>
</div>

<?php if (empty($allRisks)): ?>
    <p class="empty-state">Još uvek nema unetih rizika.</p>
<?php else: ?>
    <?php foreach ($allRisks as $risk): ?>
        <?php $treatments = $treatmentsByRisk[$risk['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/risk-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="risk-modal-overlay" onclick="closeRiskModal()">
    <div class="modal-box modal-box-wide" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="risk-modal-title">Dodaj rizik</span>
            <button type="button" class="modal-close" onclick="closeRiskModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="risk-modal-form">
            <input type="hidden" name="action" id="risk-modal-action" value="add_risk">
            <input type="hidden" name="id" id="risk-modal-id" value="">

            <div class="form-row">
                <label for="modal_title">Naziv rizika</label>
                <input type="text" name="title" id="modal_title" required
                    placeholder="npr. Gubitak pristupa nalogu za e-mail hosting">
            </div>

            <div class="form-row">
                <label for="modal_threat_description">Pretnja</label>
                <textarea name="threat_description" id="modal_threat_description" rows="2" required
                    placeholder="npr. Napadač dobija pristup nalogu putem phishing napada."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_vulnerability_description">Ranjivost</label>
                <textarea name="vulnerability_description" id="modal_vulnerability_description" rows="2" required
                    placeholder="npr. Nalog nema uključenu dvofaktorsku autentifikaciju."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_asset_id">Povezano sredstvo (opciono)</label>
                <select name="asset_id" id="modal_asset_id">
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
                <label for="modal_likelihood">Verovatnoća (1-<?= (int) $riskCriteria['likelihood_scale_max'] ?>)</label>
                <select name="likelihood" id="modal_likelihood" required>
                    <?php for ($i = 1; $i <= (int) $riskCriteria['likelihood_scale_max']; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_impact">Uticaj (1-<?= (int) $riskCriteria['impact_scale_max'] ?>)</label>
                <select name="impact" id="modal_impact" required>
                    <?php for ($i = 1; $i <= (int) $riskCriteria['impact_scale_max']; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_identified_at">Datum identifikacije</label>
                <input type="date" name="identified_at" id="modal_identified_at" required>
            </div>

            <div class="form-row">
                <label for="modal_review_trigger">Razlog pregleda</label>
                <select name="review_trigger" id="modal_review_trigger">
                    <option value="godisnji_ciklus">Godišnji ciklus</option>
                    <option value="incident">Incident</option>
                    <option value="promena">Promena</option>
                    <option value="ostalo">Ostalo</option>
                </select>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromRiskModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeRiskModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="criteria-modal-overlay" onclick="closeCriteriaModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title">Kriterijumi procene rizika</span>
            <button type="button" class="modal-close" onclick="closeCriteriaModal()" aria-label="Zatvori">&times;</button>
        </div>

        <p class="item-meta">
            Rizik = verovatnoća × uticaj. Ovi pragovi određuju kada je rizik nizak,
            srednji ili visok - promena ovde odmah preračunava sve već unete rizike.
        </p>

        <form method="post">
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
                <label for="criteria_notes">Napomena (opciono)</label>
                <textarea name="notes" id="criteria_notes" rows="2"><?= htmlspecialchars($riskCriteria['notes'] ?? '') ?></textarea>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromCriteriaModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeCriteriaModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj kriterijume</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<div class="modal-overlay" id="treatment-modal-overlay" onclick="closeTreatmentModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="treatment-modal-title">Dodaj meru</span>
            <button type="button" class="modal-close" onclick="closeTreatmentModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="add_treatment">
            <input type="hidden" name="risk_id" id="treatment-modal-risk-id" value="">

            <div class="form-row">
                <label for="modal_treatment_option">Način tretmana</label>
                <select name="treatment_option" id="modal_treatment_option" required>
                    <option value="">Izaberite...</option>
                    <option value="smanjiti">Smanjiti</option>
                    <option value="izbeci">Izbeći</option>
                    <option value="preneti">Preneti</option>
                    <option value="prihvatiti">Prihvatiti</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_treatment_description">Opis mere</label>
                <textarea name="description" id="modal_treatment_description" rows="2" required
                    placeholder="npr. Uključiti dvofaktorsku autentifikaciju za sve administratorske naloge."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_treatment_owner">Nosilac mere (opciono)</label>
                <select name="owner_id" id="modal_treatment_owner">
                    <option value="">Nije dodeljen</option>
                    <?php foreach ($activePersonnelOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_treatment_due">Rok (opciono)</label>
                <input type="date" name="due_date" id="modal_treatment_due">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromTreatmentModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeTreatmentModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openAddRiskModal() {
    document.getElementById('risk-modal-title').textContent = 'Dodaj rizik';
    document.getElementById('risk-modal-action').value = 'add_risk';
    document.getElementById('risk-modal-id').value = '';
    document.getElementById('modal_title').value = '';
    document.getElementById('modal_threat_description').value = '';
    document.getElementById('modal_vulnerability_description').value = '';
    document.getElementById('modal_asset_id').value = '';
    document.getElementById('modal_likelihood').value = '';
    document.getElementById('modal_impact').value = '';
    document.getElementById('modal_identified_at').value = '<?= date('Y-m-d') ?>';
    document.getElementById('modal_review_trigger').value = 'godisnji_ciklus';
    document.getElementById('risk-modal-overlay').classList.add('is-open');
}

function openEditRiskModal(risk) {
    document.getElementById('risk-modal-title').textContent = 'Uredi rizik';
    document.getElementById('risk-modal-action').value = 'update_risk';
    document.getElementById('risk-modal-id').value = risk.id;
    document.getElementById('modal_title').value = risk.title;
    document.getElementById('modal_threat_description').value = risk.threat_description;
    document.getElementById('modal_vulnerability_description').value = risk.vulnerability_description;
    document.getElementById('modal_asset_id').value = risk.asset_id;
    document.getElementById('modal_likelihood').value = risk.likelihood;
    document.getElementById('modal_impact').value = risk.impact;
    document.getElementById('modal_identified_at').value = risk.identified_at;
    document.getElementById('modal_review_trigger').value = risk.review_trigger;
    document.getElementById('risk-modal-overlay').classList.add('is-open');
}

function closeRiskModal() {
    document.getElementById('risk-modal-overlay').classList.remove('is-open');
}

function openHelpFromRiskModal() {
    closeRiskModal();
    openHelpModal();
}

function openCriteriaModal() {
    document.getElementById('criteria-modal-overlay').classList.add('is-open');
}

function closeCriteriaModal() {
    document.getElementById('criteria-modal-overlay').classList.remove('is-open');
}

function openHelpFromCriteriaModal() {
    closeCriteriaModal();
    openHelpModal();
}

function openTreatmentModal(riskId, riskTitle) {
    document.getElementById('treatment-modal-title').textContent = 'Dodaj meru — ' + riskTitle;
    document.getElementById('treatment-modal-risk-id').value = riskId;
    document.getElementById('modal_treatment_option').value = '';
    document.getElementById('modal_treatment_description').value = '';
    document.getElementById('modal_treatment_owner').value = '';
    document.getElementById('modal_treatment_due').value = '';
    document.getElementById('treatment-modal-overlay').classList.add('is-open');
}

function closeTreatmentModal() {
    document.getElementById('treatment-modal-overlay').classList.remove('is-open');
}

function openHelpFromTreatmentModal() {
    closeTreatmentModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeRiskModal();
        closeCriteriaModal();
        closeTreatmentModal();
    }
});
</script>
