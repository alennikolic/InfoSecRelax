<?php
/**
 * src/modules/ciljevi.php
 *
 * Klauzula 6.2: Ciljevi bezbednosti informacija i planiranje njihovog
 * ostvarenja.
 *
 * Prost CRUD, isti obrazac kao uloge.php/sredstva.php - dodavanje,
 * promena statusa (inline forma, isti obrazac kao kod rizika u
 * procena-rizika.php), brisanje. Pet polja u formi direktno odgovaraju
 * na "pet pitanja" iz standarda za plan ostvarenja: šta
 * (what_will_be_done), koji resursi (resources_required), ko
 * (owner_id), kada (due_date), kako se meri uspeh (evaluation_method).
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

$validStatuses = ['planiran', 'u_toku', 'ostvaren', 'neostvaren'];

// --- Dodavanje novog cilja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $title             = trim($_POST['title'] ?? '');
    $whatWillBeDone    = trim($_POST['what_will_be_done'] ?? '');
    $resourcesRequired = trim($_POST['resources_required'] ?? '');
    $ownerId           = trim($_POST['owner_id'] ?? '');
    $dueDate           = trim($_POST['due_date'] ?? '');
    $evaluationMethod  = trim($_POST['evaluation_method'] ?? '');
    $linkedRiskId      = trim($_POST['linked_risk_id'] ?? '');

    if ($title === '') {
        $errors[] = 'Naziv cilja je obavezan.';
    }
    if ($whatWillBeDone === '') {
        $errors[] = 'Opis šta će biti urađeno je obavezan.';
    }

    // Nosilac, ako je izabran, mora stvarno postojati u ovoj organizaciji.
    $ownerIdValue = null;
    if ($ownerId !== '') {
        $ownerIdValue = (int) $ownerId;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $ownerIdValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani nosilac nije pronađen.';
            $ownerIdValue = null;
        }
    }

    // Isto i povezani rizik.
    $linkedRiskIdValue = null;
    if ($linkedRiskId !== '') {
        $linkedRiskIdValue = (int) $linkedRiskId;
        $riskCheck = $pdo->prepare('SELECT id FROM risks WHERE id = :id AND organization_id = :org_id');
        $riskCheck->execute(['id' => $linkedRiskIdValue, 'org_id' => $organizationId]);

        if ($riskCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani rizik nije pronađen.';
            $linkedRiskIdValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO objectives
                (organization_id, title, linked_risk_id, what_will_be_done, resources_required,
                 owner_id, due_date, evaluation_method)
             VALUES
                (:org_id, :title, :linked_risk_id, :what_will_be_done, :resources_required,
                 :owner_id, :due_date, :evaluation_method)'
        );
        $stmt->execute([
            'org_id'             => $organizationId,
            'title'              => $title,
            'linked_risk_id'     => $linkedRiskIdValue,
            'what_will_be_done'  => $whatWillBeDone,
            'resources_required' => $resourcesRequired !== '' ? $resourcesRequired : null,
            'owner_id'           => $ownerIdValue,
            'due_date'           => $dueDate !== '' ? $dueDate : null,
            'evaluation_method'  => $evaluationMethod !== '' ? $evaluationMethod : null,
        ]);

        header('Location: ?page=ciljevi');
        exit;
    }
}

// --- Promena statusa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id        = (int) ($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if (in_array($newStatus, $validStatuses, true)) {
        $pdo->prepare(
            'UPDATE objectives SET status = :status WHERE id = :id AND organization_id = :org_id'
        )->execute(['status' => $newStatus, 'id' => $id, 'org_id' => $organizationId]);
    }

    header('Location: ?page=ciljevi');
    exit;
}

// --- Brisanje cilja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM objectives WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=ciljevi');
    exit;
}

// --- Aktivne osobe za dropdown nosioca ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Rizici za dropdown ---
$risksStmt = $pdo->prepare('SELECT id, title FROM risks WHERE organization_id = :org_id ORDER BY title');
$risksStmt->execute(['org_id' => $organizationId]);
$riskOptions = $risksStmt->fetchAll();

// --- Učitavanje ciljeva (oni sa rokom prvo, po najbližem roku) ---
$objectivesStmt = $pdo->prepare(
    'SELECT o.*, p.full_name AS owner_name, r.title AS risk_title
     FROM objectives o
     LEFT JOIN personnel p ON p.id = o.owner_id
     LEFT JOIN risks r ON r.id = o.linked_risk_id
     WHERE o.organization_id = :org_id
     ORDER BY o.due_date IS NULL, o.due_date, o.title'
);
$objectivesStmt->execute(['org_id' => $organizationId]);
$allObjectives = $objectivesStmt->fetchAll();
?>

<p class="module-intro">
    Klauzula 6.2 traži merljive ciljeve bezbednosti informacija, usklađene sa
    politikom, i plan njihovog ostvarenja - šta će biti urađeno, koji resursi
    su potrebni, ko je odgovoran, do kada, i kako će se meriti uspeh.
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
        <label for="title">Naziv cilja</label>
        <input type="text" name="title" id="title" required
            placeholder="npr. Smanjiti prosečno vreme ukidanja pristupa nakon odlaska zaposlenog">
    </div>

    <div class="form-row">
        <label for="what_will_be_done">Šta će biti urađeno</label>
        <textarea name="what_will_be_done" id="what_will_be_done" rows="2" required
            placeholder="npr. Uvesti checklistu za offboarding koja uključuje ukidanje pristupa u roku od 24h."></textarea>
    </div>

    <div class="form-row">
        <label for="resources_required">Potrebni resursi (opciono)</label>
        <textarea name="resources_required" id="resources_required" rows="2"
            placeholder="npr. Nekoliko sati administratora sistema mesečno."></textarea>
    </div>

    <div class="form-row">
        <label for="owner_id">Nosilac (opciono)</label>
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
        <label for="due_date">Rok (opciono)</label>
        <input type="date" name="due_date" id="due_date">
    </div>

    <div class="form-row">
        <label for="evaluation_method">Kako će se meriti uspeh (opciono)</label>
        <textarea name="evaluation_method" id="evaluation_method" rows="2"
            placeholder="npr. Pokazatelj 'vreme ukidanja pristupa', cilj ispod 24h u proseku."></textarea>
    </div>

    <div class="form-row">
        <label for="linked_risk_id">Povezan rizik (opciono)</label>
        <select name="linked_risk_id" id="linked_risk_id">
            <option value="">Nije povezano</option>
            <?php foreach ($riskOptions as $option): ?>
                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn-primary">Dodaj cilj</button>
</form>

<?php if (empty($allObjectives)): ?>
    <p class="empty-state">Još uvek nema unetih ciljeva.</p>
<?php else: ?>
    <?php foreach ($allObjectives as $objective): ?>
        <?php include __DIR__ . '/../includes/objective-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
