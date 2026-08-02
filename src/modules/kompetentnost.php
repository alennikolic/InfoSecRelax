<?php
/**
 * src/modules/kompetentnost.php
 *
 * Klauzula 7.2 (kompetentnost) i 7.3 / A.6.3 (svest i obuka) - jedna
 * stavka menija pokriva oba, pa su kombinovana ovde kao dve odvojene
 * sekcije na istoj stranici: zapisi o kompetentnosti (flat CRUD, modal
 * za dodavanje/uređivanje) i obuke sa prisustvom (roditelj-dete, isti
 * obrazac kao rizik i mere tretmana u procena-rizika.php). Pomoć je
 * jedna za celu stranicu, dugme živi uz prvi toolbar.
 *
 * Prisustvo na obuci ostaje dodaj/obriši u kartici, kao ranije zahtevi
 * i mere tretmana - van obima ovog prolaza.
 *
 * Van obima ove verzije: personnel_screening (A.6.1) - drugačija
 * kontrola Aneksa A, nema svoju stavku menija još.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'kompetentnost';

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

// --- Ažuriranje postojećeg zapisa o kompetentnosti ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_competence') {
    $id                 = (int) ($_POST['id'] ?? 0);
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
            'UPDATE competence_records
             SET personnel_id = :personnel_id, role_id = :role_id, required_competence = :required_competence,
                 gap_identified = :gap_identified, action_taken = :action_taken,
                 evaluated_effective = :evaluated_effective, evaluated_at = :evaluated_at
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'personnel_id'        => $personnelIdValue,
            'role_id'             => $roleIdValue,
            'required_competence' => $requiredCompetence,
            'gap_identified'      => $gapIdentified !== '' ? $gapIdentified : null,
            'action_taken'        => $actionTaken !== '' ? $actionTaken : null,
            'evaluated_effective' => $evaluatedEffectiveValue,
            'evaluated_at'        => $evaluatedAt !== '' ? $evaluatedAt : null,
            'id'                  => $id,
            'org_id'              => $organizationId,
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

// --- Ažuriranje postojeće obuke ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_training') {
    $id          = (int) ($_POST['id'] ?? 0);
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
            'UPDATE training_sessions
             SET title = :title, description = :description, held_at = :held_at, is_mandatory = :is_mandatory
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'title'        => $title,
            'description'  => $description !== '' ? $description : null,
            'held_at'      => $heldAt,
            'is_mandatory' => $isMandatory,
            'id'           => $id,
            'org_id'       => $organizationId,
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

<h3 class="section-heading">Kompetentnost (Klauzula 7.2)</h3>

<div class="toolbar">
    <button type="button" class="btn-primary" onclick="openAddCompetenceModal()">+ Dodaj zapis</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allCompetenceRecords)): ?>
    <p class="empty-state">Još uvek nema unetih zapisa o kompetentnosti.</p>
<?php else: ?>
    <?php foreach ($allCompetenceRecords as $record): ?>
        <?php include __DIR__ . '/../includes/competence-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<h3 class="section-heading">Obuke (Klauzula 7.3 / A.6.3)</h3>

<div class="toolbar">
    <button type="button" class="btn-primary" onclick="openAddTrainingModal()">+ Dodaj obuku</button>
</div>

<?php if (empty($allTrainingSessions)): ?>
    <p class="empty-state">Još uvek nema unetih obuka.</p>
<?php else: ?>
    <?php foreach ($allTrainingSessions as $session): ?>
        <?php $attendees = $attendanceBySession[$session['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/training-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="competence-modal-overlay" onclick="closeCompetenceModal()">
    <div class="modal-box modal-box-wide" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="competence-modal-title">Dodaj zapis</span>
            <button type="button" class="modal-close" onclick="closeCompetenceModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="competence-modal-form">
            <input type="hidden" name="action" id="competence-modal-action" value="add_competence">
            <input type="hidden" name="id" id="competence-modal-id" value="">

            <div class="form-row">
                <label for="modal_personnel_id">Osoba</label>
                <select name="personnel_id" id="modal_personnel_id" required>
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
                <label for="modal_role_id">Povezana uloga (opciono)</label>
                <select name="role_id" id="modal_role_id">
                    <option value="">Nije povezano</option>
                    <?php foreach ($roleOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_required_competence">Potrebna kompetencija</label>
                <textarea name="required_competence" id="modal_required_competence" rows="2" required
                    placeholder="npr. Poznavanje osnova bezbednog rukovanja ličnim podacima klijenata."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_gap_identified">Uočen nedostatak (opciono)</label>
                <textarea name="gap_identified" id="modal_gap_identified" rows="2"
                    placeholder="npr. Nema formalnu obuku iz zaštite podataka."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_action_taken">Preduzeta radnja (opciono)</label>
                <textarea name="action_taken" id="modal_action_taken" rows="2"
                    placeholder="npr. Prisustvovao/la internoj obuci o zaštiti podataka."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_evaluated_effective">Ocena efikasnosti (opciono)</label>
                <select name="evaluated_effective" id="modal_evaluated_effective">
                    <option value="">Nije ocenjeno</option>
                    <option value="1">Da, efikasno</option>
                    <option value="0">Ne, nije efikasno</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_evaluated_at">Datum ocene (opciono)</label>
                <input type="date" name="evaluated_at" id="modal_evaluated_at">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromCompetenceModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeCompetenceModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="training-modal-overlay" onclick="closeTrainingModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="training-modal-title">Dodaj obuku</span>
            <button type="button" class="modal-close" onclick="closeTrainingModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="training-modal-form">
            <input type="hidden" name="action" id="training-modal-action" value="add_training">
            <input type="hidden" name="id" id="training-modal-id" value="">

            <div class="form-row">
                <label for="modal_training_title">Naziv obuke</label>
                <input type="text" name="title" id="modal_training_title" required placeholder="npr. Prepoznavanje phishing napada">
            </div>

            <div class="form-row">
                <label for="modal_training_description">Opis (opciono)</label>
                <textarea name="description" id="modal_training_description" rows="2"
                    placeholder="npr. Radionica sa primerima stvarnih phishing mejlova primljenih u firmi."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_held_at">Datum održavanja</label>
                <input type="date" name="held_at" id="modal_held_at" required>
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_mandatory" id="modal_is_mandatory" value="1" checked>
                    Obavezna obuka
                </label>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromTrainingModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeTrainingModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddCompetenceModal() {
    document.getElementById('competence-modal-title').textContent = 'Dodaj zapis';
    document.getElementById('competence-modal-action').value = 'add_competence';
    document.getElementById('competence-modal-id').value = '';
    document.getElementById('modal_personnel_id').value = '';
    document.getElementById('modal_role_id').value = '';
    document.getElementById('modal_required_competence').value = '';
    document.getElementById('modal_gap_identified').value = '';
    document.getElementById('modal_action_taken').value = '';
    document.getElementById('modal_evaluated_effective').value = '';
    document.getElementById('modal_evaluated_at').value = '';
    document.getElementById('competence-modal-overlay').classList.add('is-open');
}

function openEditCompetenceModal(record) {
    document.getElementById('competence-modal-title').textContent = 'Uredi zapis';
    document.getElementById('competence-modal-action').value = 'update_competence';
    document.getElementById('competence-modal-id').value = record.id;
    document.getElementById('modal_personnel_id').value = record.personnel_id;
    document.getElementById('modal_role_id').value = record.role_id;
    document.getElementById('modal_required_competence').value = record.required_competence;
    document.getElementById('modal_gap_identified').value = record.gap_identified;
    document.getElementById('modal_action_taken').value = record.action_taken;
    document.getElementById('modal_evaluated_effective').value = record.evaluated_effective;
    document.getElementById('modal_evaluated_at').value = record.evaluated_at;
    document.getElementById('competence-modal-overlay').classList.add('is-open');
}

function closeCompetenceModal() {
    document.getElementById('competence-modal-overlay').classList.remove('is-open');
}

function openHelpFromCompetenceModal() {
    closeCompetenceModal();
    openHelpModal();
}

function openAddTrainingModal() {
    document.getElementById('training-modal-title').textContent = 'Dodaj obuku';
    document.getElementById('training-modal-action').value = 'add_training';
    document.getElementById('training-modal-id').value = '';
    document.getElementById('modal_training_title').value = '';
    document.getElementById('modal_training_description').value = '';
    document.getElementById('modal_held_at').value = '<?= date('Y-m-d') ?>';
    document.getElementById('modal_is_mandatory').checked = true;
    document.getElementById('training-modal-overlay').classList.add('is-open');
}

function openEditTrainingModal(session) {
    document.getElementById('training-modal-title').textContent = 'Uredi obuku';
    document.getElementById('training-modal-action').value = 'update_training';
    document.getElementById('training-modal-id').value = session.id;
    document.getElementById('modal_training_title').value = session.title;
    document.getElementById('modal_training_description').value = session.description;
    document.getElementById('modal_held_at').value = session.held_at;
    document.getElementById('modal_is_mandatory').checked = session.is_mandatory;
    document.getElementById('training-modal-overlay').classList.add('is-open');
}

function closeTrainingModal() {
    document.getElementById('training-modal-overlay').classList.remove('is-open');
}

function openHelpFromTrainingModal() {
    closeTrainingModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeCompetenceModal();
        closeTrainingModal();
    }
});
</script>
