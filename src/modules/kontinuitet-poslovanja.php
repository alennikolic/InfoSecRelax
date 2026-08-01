<?php
/**
 * src/modules/kontinuitet-poslovanja.php
 *
 * A.5.29-5.30: Bezbednost informacija tokom poremećaja / Spremnost IKT
 * sistema za kontinuitet poslovanja.
 *
 * Koristi novu tabelu continuity_plans - videti
 * db/migrations/002_add_continuity_plans.sql. Testiranje plana je
 * zaseban korak od dodavanja/brisanja (isti princip kao dodavanje nove
 * verzije dokumenta) - "Zabeleži test" upisuje rezultat, datum i
 * sledeći rok odjednom, jer testiranje suštinski jeste događaj u
 * vremenu, ne stanje koje se prosto uključuje/isključuje.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

$validTestResults = ['uspesno', 'delimicno_uspesno', 'neuspesno'];

// --- Dodavanje plana kontinuiteta ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $scenario        = trim($_POST['scenario'] ?? '');
    $planDescription = trim($_POST['plan_description'] ?? '');
    $ownerId         = trim($_POST['owner_id'] ?? '');
    $nextTestDue     = trim($_POST['next_test_due'] ?? '');

    if ($scenario === '') {
        $errors[] = 'Scenario je obavezan.';
    }
    if ($planDescription === '') {
        $errors[] = 'Opis plana je obavezan.';
    }

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

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO continuity_plans (organization_id, scenario, plan_description, owner_id, next_test_due)
             VALUES (:org_id, :scenario, :plan_description, :owner_id, :next_test_due)'
        );
        $stmt->execute([
            'org_id'           => $organizationId,
            'scenario'         => $scenario,
            'plan_description' => $planDescription,
            'owner_id'         => $ownerIdValue,
            'next_test_due'    => $nextTestDue !== '' ? $nextTestDue : null,
        ]);

        header('Location: ?page=kontinuitet-poslovanja');
        exit;
    }
}

// --- Beleženje testa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'record_test') {
    $id           = (int) ($_POST['id'] ?? 0);
    $testResult   = $_POST['test_result'] ?? '';
    $lastTestedAt = trim($_POST['last_tested_at'] ?? '');
    $nextTestDue  = trim($_POST['next_test_due'] ?? '');

    if (!in_array($testResult, $validTestResults, true)) {
        $errors[] = 'Izaberite rezultat testa.';
    }
    if ($lastTestedAt === '') {
        $errors[] = 'Datum testa je obavezan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE continuity_plans
             SET test_result = :test_result, last_tested_at = :last_tested_at, next_test_due = :next_test_due
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'test_result'    => $testResult,
            'last_tested_at' => $lastTestedAt,
            'next_test_due'  => $nextTestDue !== '' ? $nextTestDue : null,
            'id'             => $id,
            'org_id'         => $organizationId,
        ]);

        header('Location: ?page=kontinuitet-poslovanja');
        exit;
    }
}

// --- Brisanje plana ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM continuity_plans WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=kontinuitet-poslovanja');
    exit;
}

// --- Aktivne osobe za dropdown nosioca ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje planova (oni sa dospelim testom prvo, po najbližem roku) ---
$plansStmt = $pdo->prepare(
    'SELECT cp.*, p.full_name AS owner_name
     FROM continuity_plans cp
     LEFT JOIN personnel p ON p.id = cp.owner_id
     WHERE cp.organization_id = :org_id
     ORDER BY cp.next_test_due IS NULL, cp.next_test_due, cp.scenario'
);
$plansStmt->execute(['org_id' => $organizationId]);
$allPlans = $plansStmt->fetchAll();
?>

<p class="module-intro">
    A.5.29 traži da bezbednost informacija ostane održana tokom poremećaja, a
    A.5.30 spremnost IKT sistema za kontinuitet - plan za svaki realan scenario
    prekida, i redovno testiranje da li taj plan zaista radi.
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
        <label for="scenario">Scenario</label>
        <input type="text" name="scenario" id="scenario" required
            placeholder="npr. Nestanak struje u kancelariji, Pad glavnog servera">
    </div>

    <div class="form-row">
        <label for="plan_description">Plan odgovora</label>
        <textarea name="plan_description" id="plan_description" rows="3" required
            placeholder="npr. Prelazak na rad od kuće preko VPN-a, kritični sistemi hostovani u cloud-u sa 99.9% dostupnosti."></textarea>
    </div>

    <div class="form-row">
        <label for="owner_id">Nosilac plana (opciono)</label>
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
        <label for="next_test_due">Sledeći test dospeva (opciono)</label>
        <input type="date" name="next_test_due" id="next_test_due">
    </div>

    <button type="submit" class="btn-primary">Dodaj plan</button>
</form>

<?php if (empty($allPlans)): ?>
    <p class="empty-state">Još uvek nema unetih planova kontinuiteta.</p>
<?php else: ?>
    <?php foreach ($allPlans as $plan): ?>
        <?php include __DIR__ . '/../includes/continuity-plan-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
