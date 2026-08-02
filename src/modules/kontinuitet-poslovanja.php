<?php
/**
 * src/modules/kontinuitet-poslovanja.php
 *
 * A.5.29-5.30: Bezbednost informacija tokom poremećaja / Spremnost IKT
 * sistema za kontinuitet poslovanja.
 *
 * Koristi tabelu continuity_plans - videti
 * db/migrations/002_add_continuity_plans.sql. Isti obrazac kao ostali
 * moduli: toolbar sa Pomoć desno, modal za dodavanje/uređivanje plana.
 * Testiranje plana je zaseban modal ("Zabeleži test", otvoren dugmetom
 * u kartici) - upisuje rezultat, datum i sledeći rok odjednom, jer
 * testiranje suštinski jeste događaj u vremenu, ne stanje koje se
 * prosto uključuje/isključuje - isti princip kao "Pokreni procenu" u
 * incidenti.php.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'kontinuitet-poslovanja';

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

// --- Ažuriranje postojećeg plana (NE menja rezultate testiranja) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id              = (int) ($_POST['id'] ?? 0);
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
            'UPDATE continuity_plans
             SET scenario = :scenario, plan_description = :plan_description, owner_id = :owner_id,
                 next_test_due = :next_test_due
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'scenario'         => $scenario,
            'plan_description' => $planDescription,
            'owner_id'         => $ownerIdValue,
            'next_test_due'    => $nextTestDue !== '' ? $nextTestDue : null,
            'id'               => $id,
            'org_id'           => $organizationId,
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
    <button type="button" class="btn-primary" onclick="openAddPlanModal()">+ Dodaj plan</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allPlans)): ?>
    <p class="empty-state">Još uvek nema unetih planova kontinuiteta.</p>
<?php else: ?>
    <?php foreach ($allPlans as $plan): ?>
        <?php include __DIR__ . '/../includes/continuity-plan-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="plan-modal-overlay" onclick="closePlanModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="plan-modal-title">Dodaj plan</span>
            <button type="button" class="modal-close" onclick="closePlanModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="plan-modal-form">
            <input type="hidden" name="action" id="plan-modal-action" value="add">
            <input type="hidden" name="id" id="plan-modal-id" value="">

            <div class="form-row">
                <label for="modal_scenario">Scenario</label>
                <input type="text" name="scenario" id="modal_scenario" required
                    placeholder="npr. Nestanak struje u kancelariji, Pad glavnog servera">
            </div>

            <div class="form-row">
                <label for="modal_plan_description">Plan odgovora</label>
                <textarea name="plan_description" id="modal_plan_description" rows="3" required
                    placeholder="npr. Prelazak na rad od kuće preko VPN-a, kritični sistemi hostovani u cloud-u sa 99.9% dostupnosti."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_owner_id">Nosilac plana (opciono)</label>
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
                <label for="modal_next_test_due">Sledeći test dospeva (opciono)</label>
                <input type="date" name="next_test_due" id="modal_next_test_due">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromPlanModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closePlanModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="test-modal-overlay" onclick="closeTestModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="test-modal-title">Zabeleži test</span>
            <button type="button" class="modal-close" onclick="closeTestModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="record_test">
            <input type="hidden" name="id" id="test-modal-plan-id" value="">

            <div class="form-row">
                <label for="modal_test_result">Rezultat testa</label>
                <select name="test_result" id="modal_test_result" required>
                    <option value="uspesno">Uspešno</option>
                    <option value="delimicno_uspesno">Delimično uspešno</option>
                    <option value="neuspesno">Neuspešno</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_last_tested_at">Datum testa</label>
                <input type="date" name="last_tested_at" id="modal_last_tested_at" required>
            </div>

            <div class="form-row">
                <label for="modal_next_test_due_record">Sledeći test dospeva (opciono)</label>
                <input type="date" name="next_test_due" id="modal_next_test_due_record">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromTestModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeTestModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddPlanModal() {
    document.getElementById('plan-modal-title').textContent = 'Dodaj plan';
    document.getElementById('plan-modal-action').value = 'add';
    document.getElementById('plan-modal-id').value = '';
    document.getElementById('modal_scenario').value = '';
    document.getElementById('modal_plan_description').value = '';
    document.getElementById('modal_owner_id').value = '';
    document.getElementById('modal_next_test_due').value = '';
    document.getElementById('plan-modal-overlay').classList.add('is-open');
}

function openEditPlanModal(plan) {
    document.getElementById('plan-modal-title').textContent = 'Uredi plan';
    document.getElementById('plan-modal-action').value = 'update';
    document.getElementById('plan-modal-id').value = plan.id;
    document.getElementById('modal_scenario').value = plan.scenario;
    document.getElementById('modal_plan_description').value = plan.plan_description;
    document.getElementById('modal_owner_id').value = plan.owner_id;
    document.getElementById('modal_next_test_due').value = plan.next_test_due;
    document.getElementById('plan-modal-overlay').classList.add('is-open');
}

function closePlanModal() {
    document.getElementById('plan-modal-overlay').classList.remove('is-open');
}

function openHelpFromPlanModal() {
    closePlanModal();
    openHelpModal();
}

function openTestModal(planId, planScenario) {
    document.getElementById('test-modal-title').textContent = 'Zabeleži test — ' + planScenario;
    document.getElementById('test-modal-plan-id').value = planId;
    document.getElementById('modal_test_result').value = 'uspesno';
    document.getElementById('modal_last_tested_at').value = '<?= date('Y-m-d') ?>';
    document.getElementById('modal_next_test_due_record').value = '';
    document.getElementById('test-modal-overlay').classList.add('is-open');
}

function closeTestModal() {
    document.getElementById('test-modal-overlay').classList.remove('is-open');
}

function openHelpFromTestModal() {
    closeTestModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closePlanModal();
        closeTestModal();
    }
});
</script>
