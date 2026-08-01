<?php
/**
 * src/modules/kompetentnost.php
 *
 * Klauzula 7.2 (kompetentnost) i 7.3 / A.6.3 (svest i obuka) - jedna
 * stavka menija pokriva oba, pa su kombinovana ovde kao dve odvojene
 * sekcije na istoj stranici: zapisi o kompetentnosti (flat CRUD) i
 * obuke sa prisustvom (roditelj-dete, isti obrazac kao rizik i mere
 * tretmana u procena-rizika.php).
 *
 * Van obima ove verzije: personnel_screening (A.6.1) - drugačija
 * kontrola Aneksa A, nema svoju stavku menija još.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

// --- Dodavanje zapisa o kompetentnosti ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_competence') {
    $personnelId        = trim($_POST['personnel_id'] ?? '');
    $roleId             = trim($_POST['role_id'] ?? '');
    $requiredCompetence = trim($_POST['required_competence'] ?? '');
    $gapIdentified      = trim($_POST['gap_identified'] ?? '');
    $actionTaken        = trim($_POST['action_taken'] ?? '');
    $evaluatedEffective = $_POST['evaluated_effective'] ?? '';
    $evaluatedAt        = trim($_POST['evaluated_at'] ?? '');

    $personnelIdValue = null;
    if ($personnelId !== '') {
        $personnelIdValue = (int) $personnelId;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $personnelIdValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrana osoba nije pronađena.';
            $personnelIdValue = null;
        }
    } else {
        $errors[] = 'Osoba je obavezna.';
    }

    if ($requiredCompetence === '') {
        $errors[] = 'Opis potrebne kompetencije je obavezan.';
    }

    $roleIdValue = null;
    if ($roleId !== '') {
        $roleIdValue = (int) $roleId;
        $roleCheck = $pdo->prepare('SELECT id FROM roles_responsibilities WHERE id = :id AND organization_id = :org_id');
        $roleCheck->execute(['id' => $roleIdValue, 'org_id' => $organizationId]);

        if ($roleCheck->fetchColumn() === false) {
            $errors[] = 'Izabrana uloga nije pronađena.';
            $roleIdValue = null;
        }
    }

    $evaluatedEffectiveValue = null;
    if ($evaluatedEffective === '1') {
        $evaluatedEffectiveValue = 1;
    } elseif ($evaluatedEffective === '0') {
        $evaluatedEffectiveValue = 0;
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO competence_records
                (organization_id, personnel_id, role_id, required_competence, gap_identified,
                 action_taken, evaluated_effective, evaluated_at)
             VALUES
                (:org_id, :personnel_id, :role_id, :required_competence, :gap_identified,
                 :action_taken, :evaluated_effective, :evaluated_at)'
        );
        $stmt->execute([
            'org_id'              => $organizationId,
            'personnel_id'        => $personnelIdValue,
            'role_id'             => $roleIdValue,
            'required_competence' => $requiredCompetence,
            'gap_identified'      => $gapIdentified !== '' ? $gapIdentified : null,
            'action_taken'        => $actionTaken !== '' ? $actionTaken : null,
            'evaluated_effective' => $evaluatedEffectiveValue,
            'evaluated_at'        => $evaluatedAt !== '' ? $evaluatedAt : null,
        ]);

        header('Location: ?page=kompetentnost');
        exit;
    }
}

// --- Brisanje zapisa o kompetentnosti ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_competence') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM competence_records WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=kompetentnost');
    exit;
}

// --- Dodavanje obuke ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_training') {
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $heldAt      = trim($_POST['held_at'] ?? '');
    $isMandatory = isset($_POST['is_mandatory']) ? 1 : 0;

    if ($title === '') {
        $errors[] = 'Naziv obuke je obavezan.';
    }
    if ($heldAt === '') {
        $errors[] = 'Datum održavanja je obavezan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO training_sessions (organization_id, title, description, held_at, is_mandatory)
             VALUES (:org_id, :title, :description, :held_at, :is_mandatory)'
        );
        $stmt->execute([
            'org_id'       => $organizationId,
            'title'        => $title,
            'description'  => $description !== '' ? $description : null,
            'held_at'      => $heldAt,
            'is_mandatory' => $isMandatory,
        ]);

        header('Location: ?page=kompetentnost');
        exit;
    }
}

// --- Brisanje obuke (prisustva se brišu kaskadno preko FK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_training') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM training_sessions WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=kompetentnost');
    exit;
}

// --- Dodavanje prisustva na obuci ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_attendance') {
    $trainingSessionId = (int) ($_POST['training_session_id'] ?? 0);
    $personnelId        = trim($_POST['personnel_id'] ?? '');

    $sessionCheck = $pdo->prepare('SELECT id FROM training_sessions WHERE id = :id AND organization_id = :org_id');
    $sessionCheck->execute(['id' => $trainingSessionId, 'org_id' => $organizationId]);

    if ($sessionCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznata obuka.';
    }

    $personnelIdValue = null;
    if ($personnelId !== '') {
        $personnelIdValue = (int) $personnelId;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $personnelIdValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrana osoba nije pronađena.';
            $personnelIdValue = null;
        }
    } else {
        $errors[] = 'Izaberite osobu.';
    }

    if (empty($errors)) {
        // INSERT IGNORE zbog UNIQUE KEY (training_session_id, personnel_id) -
        // ista osoba se ne može dodati dvaput na istu obuku.
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO training_attendance (training_session_id, personnel_id, completed_at)
             VALUES (:session_id, :personnel_id, NOW())'
        );
        $stmt->execute([
            'session_id'   => $trainingSessionId,
            'personnel_id' => $personnelIdValue,
        ]);

        header('Location: ?page=kompetentnost');
        exit;
    }
}

// --- Brisanje prisustva ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_attendance') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE a FROM training_attendance a
         INNER JOIN training_sessions t ON t.id = a.training_session_id
         WHERE a.id = :id AND t.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=kompetentnost');
    exit;
}

// --- Aktivne osobe (koriste oba dela stranice) ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Uloge za dropdown u kompetentnosti ---
$rolesStmt = $pdo->prepare(
    'SELECT id, role_name FROM roles_responsibilities WHERE organization_id = :org_id ORDER BY role_name'
);
$rolesStmt->execute(['org_id' => $organizationId]);
$roleOptions = $rolesStmt->fetchAll();

// --- Učitavanje zapisa o kompetentnosti ---
$competenceStmt = $pdo->prepare(
    'SELECT c.*, p.full_name AS person_name, r.role_name
     FROM competence_records c
     INNER JOIN personnel p ON p.id = c.personnel_id
     LEFT JOIN roles_responsibilities r ON r.id = c.role_id
     WHERE c.organization_id = :org_id
     ORDER BY c.created_at DESC'
);
$competenceStmt->execute(['org_id' => $organizationId]);
$allCompetenceRecords = $competenceStmt->fetchAll();

// --- Učitavanje obuka (najnovije prvo) ---
$trainingStmt = $pdo->prepare(
    'SELECT * FROM training_sessions WHERE organization_id = :org_id ORDER BY held_at DESC'
);
$trainingStmt->execute(['org_id' => $organizationId]);
$allTrainingSessions = $trainingStmt->fetchAll();

// --- Prisustva za sve obuke ove organizacije, grupisana po training_session_id ---
$attendanceStmt = $pdo->prepare(
    'SELECT a.*, p.full_name AS person_name
     FROM training_attendance a
     INNER JOIN training_sessions t ON t.id = a.training_session_id
     INNER JOIN personnel p ON p.id = a.personnel_id
     WHERE t.organization_id = :org_id
     ORDER BY p.full_name'
);
$attendanceStmt->execute(['org_id' => $organizationId]);

$attendanceBySession = [];
foreach ($attendanceStmt->fetchAll() as $attendance) {
    $attendanceBySession[$attendance['training_session_id']][] = $attendance;
}
?>

<p class="module-intro">
    Klauzula 7.2 traži da se obezbedi kompetentnost ljudi čiji rad utiče na
    bezbednost informacija, a 7.3 svest i obuku - obe su ovde na istoj strani,
    pošto su usko povezane.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<h3 class="section-heading">Kompetentnost (Klauzula 7.2)</h3>

<form method="post" class="factor-form">
    <input type="hidden" name="action" value="add_competence">

    <div class="form-row">
        <label for="personnel_id">Osoba</label>
        <select name="personnel_id" id="personnel_id" required>
            <option value="">Izaberite...</option>
            <?php foreach ($activePersonnelOptions as $option): ?>
                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($activePersonnelOptions)): ?>
            <p class="item-meta">Nema unetih aktivnih osoba - prvo ih dodaj na stranici "Zaposleni i saradnici".</p>
        <?php endif; ?>
    </div>

    <div class="form-row">
        <label for="role_id">Povezana uloga (opciono)</label>
        <select name="role_id" id="role_id">
            <option value="">Nije povezano</option>
            <?php foreach ($roleOptions as $option): ?>
                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['role_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row">
        <label for="required_competence">Potrebna kompetencija</label>
        <textarea name="required_competence" id="required_competence" rows="2" required
            placeholder="npr. Poznavanje osnova bezbednog rukovanja ličnim podacima klijenata."></textarea>
    </div>

    <div class="form-row">
        <label for="gap_identified">Uočen nedostatak (opciono)</label>
        <textarea name="gap_identified" id="gap_identified" rows="2"
            placeholder="npr. Nema formalnu obuku iz zaštite podataka."></textarea>
    </div>

    <div class="form-row">
        <label for="action_taken">Preduzeta radnja (opciono)</label>
        <textarea name="action_taken" id="action_taken" rows="2"
            placeholder="npr. Prisustvovao/la internoj obuci o zaštiti podataka."></textarea>
    </div>

    <div class="form-row">
        <label for="evaluated_effective">Ocena efikasnosti (opciono)</label>
        <select name="evaluated_effective" id="evaluated_effective">
            <option value="">Nije ocenjeno</option>
            <option value="1">Da, efikasno</option>
            <option value="0">Ne, nije efikasno</option>
        </select>
    </div>

    <div class="form-row">
        <label for="evaluated_at">Datum ocene (opciono)</label>
        <input type="date" name="evaluated_at" id="evaluated_at">
    </div>

    <button type="submit" class="btn-primary">Dodaj zapis</button>
</form>

<?php if (empty($allCompetenceRecords)): ?>
    <p class="empty-state">Još uvek nema unetih zapisa o kompetentnosti.</p>
<?php else: ?>
    <?php foreach ($allCompetenceRecords as $record): ?>
        <?php include __DIR__ . '/../includes/competence-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<h3 class="section-heading">Obuke (Klauzula 7.3 / A.6.3)</h3>

<form method="post" class="factor-form">
    <input type="hidden" name="action" value="add_training">

    <div class="form-row">
        <label for="title">Naziv obuke</label>
        <input type="text" name="title" id="title" required placeholder="npr. Prepoznavanje phishing napada">
    </div>

    <div class="form-row">
        <label for="description">Opis (opciono)</label>
        <textarea name="description" id="description" rows="2"
            placeholder="npr. Radionica sa primerima stvarnih phishing mejlova primljenih u firmi."></textarea>
    </div>

    <div class="form-row">
        <label for="held_at">Datum održavanja</label>
        <input type="date" name="held_at" id="held_at" required value="<?= date('Y-m-d') ?>">
    </div>

    <div class="form-row form-row-inline">
        <label class="checkbox-label">
            <input type="checkbox" name="is_mandatory" value="1" checked>
            Obavezna obuka
        </label>
    </div>

    <button type="submit" class="btn-primary">Dodaj obuku</button>
</form>

<?php if (empty($allTrainingSessions)): ?>
    <p class="empty-state">Još uvek nema unetih obuka.</p>
<?php else: ?>
    <?php foreach ($allTrainingSessions as $session): ?>
        <?php $attendees = $attendanceBySession[$session['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/training-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
