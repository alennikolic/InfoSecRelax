<?php
/**
 * src/modules/zaposleni.php
 *
 * Evidencija osoblja (personnel) - zaposleni, honorarni saradnici i
 * spoljni dobavljači koji rade za firmu. Ovo nije posvećeno jednoj
 * klauzuli standarda, nego je osnovni skup podataka od kog zavisi
 * skoro svaki drugi modul (owner_id, assigned_to, personnel_id kolone
 * kroz veći deo šeme) - zato postoji pre "Uloga i odgovornosti".
 *
 * Za razliku od dosadašnjih modula, brisanje ovde nije podrazumevana
 * radnja pri odlasku osobe iz firme: personnel.is_active i end_date
 * postoje baš zato da se osoba "deaktivira", a ne briše - trajno
 * brisanje kaskadno briše i sve povezane zapise (pristupi, obuke,
 * provere, disciplinski postupci...), što se retko kad zaista želi.
 * "Obriši" je zadržan samo za ispravku pogrešno unetog reda.
 *
 * "Uredi" (modal) menja samo opisne podatke (ime, vrsta angažovanja,
 * pozicija, e-mail, datum početka) - NE i is_active/end_date, ta dva
 * polja idu isključivo kroz Deaktiviraj/Aktiviraj ponovo dugmad, da se
 * ne zaobiđe potvrda i logika oko automatskog postavljanja datuma.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'zaposleni';

$errors = [];

$validEmploymentTypes = ['zaposleni', 'honorarni_saradnik', 'spoljni_dobavljac', 'ostalo'];

// --- Dodavanje nove osobe ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $fullName       = trim($_POST['full_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $employmentType = $_POST['employment_type'] ?? '';
    $jobTitle       = trim($_POST['job_title'] ?? '');
    $startDate      = trim($_POST['start_date'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Ime i prezime su obavezni.';
    }
    if (!in_array($employmentType, $validEmploymentTypes, true)) {
        $errors[] = 'Izaberite vrstu angažovanja.';
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Uneta e-mail adresa nije ispravna.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO personnel (organization_id, full_name, email, employment_type, job_title, start_date)
             VALUES (:org_id, :full_name, :email, :employment_type, :job_title, :start_date)'
        );
        $stmt->execute([
            'org_id'          => $organizationId,
            'full_name'       => $fullName,
            'email'           => $email !== '' ? $email : null,
            'employment_type' => $employmentType,
            'job_title'       => $jobTitle !== '' ? $jobTitle : null,
            'start_date'      => $startDate !== '' ? $startDate : null,
        ]);

        header('Location: ?page=zaposleni');
        exit;
    }
}

// --- Ažuriranje postojeće osobe ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id             = (int) ($_POST['id'] ?? 0);
    $fullName       = trim($_POST['full_name'] ?? '');
    $email          = trim($_POST['email'] ?? '');
    $employmentType = $_POST['employment_type'] ?? '';
    $jobTitle       = trim($_POST['job_title'] ?? '');
    $startDate      = trim($_POST['start_date'] ?? '');

    if ($fullName === '') {
        $errors[] = 'Ime i prezime su obavezni.';
    }
    if (!in_array($employmentType, $validEmploymentTypes, true)) {
        $errors[] = 'Izaberite vrstu angažovanja.';
    }
    if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors[] = 'Uneta e-mail adresa nije ispravna.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE personnel
             SET full_name = :full_name, email = :email, employment_type = :employment_type,
                 job_title = :job_title, start_date = :start_date
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'full_name'       => $fullName,
            'email'           => $email !== '' ? $email : null,
            'employment_type' => $employmentType,
            'job_title'       => $jobTitle !== '' ? $jobTitle : null,
            'start_date'      => $startDate !== '' ? $startDate : null,
            'id'              => $id,
            'org_id'          => $organizationId,
        ]);

        header('Location: ?page=zaposleni');
        exit;
    }
}

// --- Deaktiviranje (osoba je otišla, ali istorija ostaje) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deactivate') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'UPDATE personnel
         SET is_active = FALSE, end_date = COALESCE(end_date, CURDATE())
         WHERE id = :id AND organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=zaposleni');
    exit;
}

// --- Ponovna aktivacija ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reactivate') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'UPDATE personnel SET is_active = TRUE, end_date = NULL WHERE id = :id AND organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=zaposleni');
    exit;
}

// --- Trajno brisanje (ispravka pogrešnog unosa, ne redovno offboardovanje) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM personnel WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=zaposleni');
    exit;
}

// --- Učitavanje osoblja ---
$stmt = $pdo->prepare(
    'SELECT * FROM personnel WHERE organization_id = :org_id ORDER BY full_name'
);
$stmt->execute(['org_id' => $organizationId]);
$allPersonnel = $stmt->fetchAll();

$activePersonnel   = array_filter($allPersonnel, fn(array $p): bool => (bool) $p['is_active']);
$inactivePersonnel = array_filter($allPersonnel, fn(array $p): bool => !$p['is_active']);

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
    <button type="button" class="btn-primary" onclick="openAddPersonModal()">+ Dodaj osobu</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<div class="factor-columns">
    <div class="factor-column">
        <h3>Aktivni (<?= count($activePersonnel) ?>)</h3>
        <?php if (empty($activePersonnel)): ?>
            <p class="empty-state">Još uvek nema unetih aktivnih osoba.</p>
        <?php else: ?>
            <?php foreach ($activePersonnel as $person): ?>
                <?php include __DIR__ . '/../includes/person-card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="factor-column">
        <h3>Neaktivni (<?= count($inactivePersonnel) ?>)</h3>
        <?php if (empty($inactivePersonnel)): ?>
            <p class="empty-state">Nema deaktiviranih osoba.</p>
        <?php else: ?>
            <?php foreach ($inactivePersonnel as $person): ?>
                <?php include __DIR__ . '/../includes/person-card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="person-modal-overlay" onclick="closePersonModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="person-modal-title">Dodaj osobu</span>
            <button type="button" class="modal-close" onclick="closePersonModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="person-modal-form">
            <input type="hidden" name="action" id="person-modal-action" value="add">
            <input type="hidden" name="id" id="person-modal-id" value="">

            <div class="form-row">
                <label for="modal_full_name">Ime i prezime</label>
                <input type="text" name="full_name" id="modal_full_name" required placeholder="npr. Ana Jovanović">
            </div>

            <div class="form-row">
                <label for="modal_employment_type">Vrsta angažovanja</label>
                <select name="employment_type" id="modal_employment_type" required>
                    <option value="">Izaberite...</option>
                    <option value="zaposleni">Zaposleni</option>
                    <option value="honorarni_saradnik">Honorarni saradnik</option>
                    <option value="spoljni_dobavljac">Spoljni dobavljač</option>
                    <option value="ostalo">Ostalo</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_job_title">Pozicija (opciono)</label>
                <input type="text" name="job_title" id="modal_job_title" placeholder="npr. Knjigovođa, Administrator sistema">
            </div>

            <div class="form-row">
                <label for="modal_email">E-mail (opciono)</label>
                <input type="text" name="email" id="modal_email" placeholder="npr. ana.jovanovic@firma.rs">
            </div>

            <div class="form-row">
                <label for="modal_start_date">Datum početka angažovanja (opciono)</label>
                <input type="date" name="start_date" id="modal_start_date">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromPersonModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closePersonModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddPersonModal() {
    document.getElementById('person-modal-title').textContent = 'Dodaj osobu';
    document.getElementById('person-modal-action').value = 'add';
    document.getElementById('person-modal-id').value = '';
    document.getElementById('modal_full_name').value = '';
    document.getElementById('modal_employment_type').value = '';
    document.getElementById('modal_job_title').value = '';
    document.getElementById('modal_email').value = '';
    document.getElementById('modal_start_date').value = '';
    document.getElementById('person-modal-overlay').classList.add('is-open');
}

function openEditPersonModal(person) {
    document.getElementById('person-modal-title').textContent = 'Uredi osobu';
    document.getElementById('person-modal-action').value = 'update';
    document.getElementById('person-modal-id').value = person.id;
    document.getElementById('modal_full_name').value = person.full_name;
    document.getElementById('modal_employment_type').value = person.employment_type;
    document.getElementById('modal_job_title').value = person.job_title;
    document.getElementById('modal_email').value = person.email;
    document.getElementById('modal_start_date').value = person.start_date;
    document.getElementById('person-modal-overlay').classList.add('is-open');
}

function closePersonModal() {
    document.getElementById('person-modal-overlay').classList.remove('is-open');
}

function openHelpFromPersonModal() {
    closePersonModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closePersonModal();
    }
});
</script>
