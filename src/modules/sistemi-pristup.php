<?php
/**
 * src/modules/sistemi-pristup.php
 *
 * Klauzula 8.1 / A.8.1-8.5: Sistemi i kontrola pristupa.
 *
 * Pokriva samo systems i access_grants - equipment, storage_media i
 * backup_records su iz šeme tematski blizu (A.7.9/7.10/7.14,
 * A.5.30/8.13), ali nemaju svoju stavku menija, pa nisu ovde da se ne
 * mešaju različite kontrole pod jedan naslov ("Sistemi i pristup").
 *
 * supplier_id na sistemu namerno nije u formi - dobavljaci.php (sledeći
 * modul) tek treba da postoji da bi dropdown imao iz čega da bira.
 *
 * access_grants ima dve radnje pored dodavanja: "Ukini pristup" (soft -
 * postavlja status=ukinut i revoked_at=NOW(), koristi se za budući
 * pokazatelj vremena ukidanja pristupa iz Klauzule 9.1) i "Obriši"
 * (hard delete, samo za ispravku pogrešnog unosa - isti princip kao
 * deaktiviranje/brisanje u zaposleni.php). revoked_by nije u formi jer
 * nema autentifikacije - ne postoji "ko je trenutno ulogovan" da se
 * automatski upiše.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

$validHostingTypes  = ['cloud', 'lokalno', 'hibridno'];
$validCriticalities = ['nizak', 'srednji', 'visok'];
$validAccessLevels  = ['standardni', 'privilegovan'];

// --- Dodavanje sistema ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_system') {
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $hostingType = $_POST['hosting_type'] ?? 'cloud';
    $criticality = $_POST['criticality'] ?? 'srednji';
    $ownerId     = trim($_POST['owner_id'] ?? '');

    if ($name === '') {
        $errors[] = 'Naziv sistema je obavezan.';
    }
    if (!in_array($hostingType, $validHostingTypes, true)) {
        $errors[] = 'Izaberite tip hostinga.';
    }
    if (!in_array($criticality, $validCriticalities, true)) {
        $errors[] = 'Izaberite kritičnost.';
    }

    $ownerIdValue = null;
    if ($ownerId !== '') {
        $ownerIdValue = (int) $ownerId;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $ownerIdValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani vlasnik nije pronađen.';
            $ownerIdValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO systems (organization_id, name, description, owner_id, hosting_type, criticality)
             VALUES (:org_id, :name, :description, :owner_id, :hosting_type, :criticality)'
        );
        $stmt->execute([
            'org_id'       => $organizationId,
            'name'         => $name,
            'description'  => $description !== '' ? $description : null,
            'owner_id'     => $ownerIdValue,
            'hosting_type' => $hostingType,
            'criticality'  => $criticality,
        ]);

        header('Location: ?page=sistemi-pristup');
        exit;
    }
}

// --- Brisanje sistema (pristupi se brišu kaskadno preko FK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_system') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM systems WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=sistemi-pristup');
    exit;
}

// --- Dodavanje pristupa sistemu ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_access') {
    $systemId    = (int) ($_POST['system_id'] ?? 0);
    $personnelId = trim($_POST['personnel_id'] ?? '');
    $accessLevel = $_POST['access_level'] ?? 'standardni';
    $scopeNote   = trim($_POST['scope_note'] ?? '');
    $grantedBy   = trim($_POST['granted_by'] ?? '');

    $systemCheck = $pdo->prepare('SELECT id FROM systems WHERE id = :id AND organization_id = :org_id');
    $systemCheck->execute(['id' => $systemId, 'org_id' => $organizationId]);

    if ($systemCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznat sistem.';
    }
    if (!in_array($accessLevel, $validAccessLevels, true)) {
        $errors[] = 'Izaberite nivo pristupa.';
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

    $grantedByValue = null;
    if ($grantedBy !== '') {
        $grantedByValue = (int) $grantedBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $grantedByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Osoba koja je odobrila pristup nije pronađena.';
            $grantedByValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO access_grants
                (organization_id, system_id, personnel_id, access_level, scope_note, granted_by)
             VALUES
                (:org_id, :system_id, :personnel_id, :access_level, :scope_note, :granted_by)'
        );
        $stmt->execute([
            'org_id'       => $organizationId,
            'system_id'    => $systemId,
            'personnel_id' => $personnelIdValue,
            'access_level' => $accessLevel,
            'scope_note'   => $scopeNote !== '' ? $scopeNote : null,
            'granted_by'   => $grantedByValue,
        ]);

        header('Location: ?page=sistemi-pristup');
        exit;
    }
}

// --- Ukidanje pristupa (soft - status i revoked_at, ostaje istorija) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revoke_access') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        "UPDATE access_grants
         SET status = 'ukinut', revoked_at = COALESCE(revoked_at, NOW())
         WHERE id = :id AND organization_id = :org_id"
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=sistemi-pristup');
    exit;
}

// --- Brisanje pristupa (samo za ispravku pogrešnog unosa) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_access') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM access_grants WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=sistemi-pristup');
    exit;
}

// --- Aktivne osobe za dropdown-e ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje sistema (sa imenom vlasnika) ---
$systemsStmt = $pdo->prepare(
    'SELECT s.*, p.full_name AS owner_name
     FROM systems s
     LEFT JOIN personnel p ON p.id = s.owner_id
     WHERE s.organization_id = :org_id
     ORDER BY s.name'
);
$systemsStmt->execute(['org_id' => $organizationId]);
$allSystems = $systemsStmt->fetchAll();

// --- Pristupi za sve sisteme ove organizacije, grupisani po system_id ---
$accessStmt = $pdo->prepare(
    'SELECT a.*, p.full_name AS person_name, g.full_name AS granted_by_name
     FROM access_grants a
     INNER JOIN systems s ON s.id = a.system_id
     INNER JOIN personnel p ON p.id = a.personnel_id
     LEFT JOIN personnel g ON g.id = a.granted_by
     WHERE s.organization_id = :org_id
     ORDER BY a.status, p.full_name'
);
$accessStmt->execute(['org_id' => $organizationId]);

$accessBySystem = [];
foreach ($accessStmt->fetchAll() as $access) {
    $accessBySystem[$access['system_id']][] = $access;
}
?>

<p class="module-intro">
    Klauzula 8.1 i A.8.2-8.5 traže popis sistema i kontrolu ko ima pristup
    čemu - posebno privilegovan pristup, koji uvek treba posebno opravdanje.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="factor-form">
    <input type="hidden" name="action" value="add_system">

    <div class="form-row">
        <label for="name">Naziv sistema</label>
        <input type="text" name="name" id="name" required placeholder="npr. CRM za klijente">
    </div>

    <div class="form-row">
        <label for="description">Opis (opciono)</label>
        <textarea name="description" id="description" rows="2"
            placeholder="npr. Cloud aplikacija za praćenje klijenata i njihovih zahteva."></textarea>
    </div>

    <div class="form-row">
        <label for="hosting_type">Tip hostinga</label>
        <select name="hosting_type" id="hosting_type">
            <option value="cloud" selected>Cloud</option>
            <option value="lokalno">Lokalno</option>
            <option value="hibridno">Hibridno</option>
        </select>
    </div>

    <div class="form-row">
        <label for="criticality">Kritičnost</label>
        <select name="criticality" id="criticality">
            <option value="nizak">Nizak</option>
            <option value="srednji" selected>Srednji</option>
            <option value="visok">Visok</option>
        </select>
    </div>

    <div class="form-row">
        <label for="owner_id">Vlasnik sistema (opciono)</label>
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

    <button type="submit" class="btn-primary">Dodaj sistem</button>
</form>

<?php if (empty($allSystems)): ?>
    <p class="empty-state">Još uvek nema unetih sistema.</p>
<?php else: ?>
    <?php foreach ($allSystems as $system): ?>
        <?php $accessGrants = $accessBySystem[$system['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/system-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
