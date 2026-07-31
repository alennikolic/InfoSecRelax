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
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

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
?>

<p class="module-intro">
    Osnovna evidencija svih koji rade za firmu - zaposlenih, honorarnih saradnika
    i spoljnih dobavljača. Ova lista se koristi kroz ostale module (npr. vlasnik
    sredstva, nosilac uloge, odobravalac dokumenta), pa je zato dobro popuniti je
    pre nego što se pređe na "Uloge i odgovornosti".
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
        <label for="full_name">Ime i prezime</label>
        <input type="text" name="full_name" id="full_name" required placeholder="npr. Ana Jovanović">
    </div>

    <div class="form-row">
        <label for="employment_type">Vrsta angažovanja</label>
        <select name="employment_type" id="employment_type" required>
            <option value="">Izaberite...</option>
            <option value="zaposleni">Zaposleni</option>
            <option value="honorarni_saradnik">Honorarni saradnik</option>
            <option value="spoljni_dobavljac">Spoljni dobavljač</option>
            <option value="ostalo">Ostalo</option>
        </select>
    </div>

    <div class="form-row">
        <label for="job_title">Pozicija (opciono)</label>
        <input type="text" name="job_title" id="job_title" placeholder="npr. Knjigovođa, Administrator sistema">
    </div>

    <div class="form-row">
        <label for="email">E-mail (opciono)</label>
        <input type="text" name="email" id="email" placeholder="npr. ana.jovanovic@firma.rs">
    </div>

    <div class="form-row">
        <label for="start_date">Datum početka angažovanja (opciono)</label>
        <input type="date" name="start_date" id="start_date">
    </div>

    <button type="submit" class="btn-primary">Dodaj osobu</button>
</form>

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
