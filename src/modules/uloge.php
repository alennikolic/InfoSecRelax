<?php
/**
 * src/modules/uloge.php
 *
 * Klauzula 5.3 / A.5.2: Uloge i odgovornosti za bezbednost informacija.
 *
 * Prost CRUD (dodaj/prikaži/obriši), isti obrazac kao kontekst.php.
 * Jedina razlika je opciono polje assigned_to, koje se bira iz
 * personnel - namerno samo aktivne osobe, da se nova uloga ne dodeli
 * nekome ko je već otišao iz firme. Brisanje osobe u zaposleni.php ne
 * briše ništa ovde: assigned_to FK ima ON DELETE SET NULL, uloga samo
 * ostane bez nosioca.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

// --- Dodavanje nove uloge ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $roleName          = trim($_POST['role_name'] ?? '');
    $description       = trim($_POST['description'] ?? '');
    $assignedTo        = trim($_POST['assigned_to'] ?? '');
    $authorityLevel    = trim($_POST['authority_level'] ?? '');
    $relatedControlRef = trim($_POST['related_control_ref'] ?? '');

    if ($roleName === '') {
        $errors[] = 'Naziv uloge je obavezan.';
    }

    // Ako je nosilac izabran, mora stvarno postojati u ovoj organizaciji -
    // sprečava dodelu preko izmenjenog POST-a osobi iz tuđe organizacije.
    $assignedToId = null;
    if ($assignedTo !== '') {
        $assignedToId = (int) $assignedTo;
        $personCheck = $pdo->prepare(
            'SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id'
        );
        $personCheck->execute(['id' => $assignedToId, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrana osoba nije pronađena.';
            $assignedToId = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO roles_responsibilities
                (organization_id, role_name, description, assigned_to, authority_level, related_control_ref)
             VALUES (:org_id, :role_name, :description, :assigned_to, :authority_level, :related_control_ref)'
        );
        $stmt->execute([
            'org_id'              => $organizationId,
            'role_name'           => $roleName,
            'description'         => $description !== '' ? $description : null,
            'assigned_to'         => $assignedToId,
            'authority_level'     => $authorityLevel !== '' ? $authorityLevel : null,
            'related_control_ref' => $relatedControlRef !== '' ? $relatedControlRef : null,
        ]);

        header('Location: ?page=uloge');
        exit;
    }
}

// --- Brisanje uloge ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM roles_responsibilities WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=uloge');
    exit;
}

// --- Aktivne osobe za dropdown ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje uloga (sa imenom nosioca preko LEFT JOIN-a) ---
$rolesStmt = $pdo->prepare(
    'SELECT r.*, p.full_name AS assigned_name
     FROM roles_responsibilities r
     LEFT JOIN personnel p ON p.id = r.assigned_to
     WHERE r.organization_id = :org_id
     ORDER BY r.role_name'
);
$rolesStmt->execute(['org_id' => $organizationId]);
$allRoles = $rolesStmt->fetchAll();
?>

<p class="module-intro">
    Klauzula 5.3 traži da se uloge i odgovornosti relevantne za bezbednost
    informacija dodele i saopšte - odgovornost bez jasnog ovlašćenja je
    nepotpuna, zato svaka uloga ima i polje za nivo ovlašćenja.
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
        <label for="role_name">Naziv uloge</label>
        <input type="text" name="role_name" id="role_name" required
            placeholder="npr. Koordinator ISMS-a, Vlasnik sredstva - serveri">
    </div>

    <div class="form-row">
        <label for="description">Opis (opciono)</label>
        <textarea name="description" id="description" rows="2"
            placeholder="npr. Koordinira aktivnosti ISMS-a, prati rokove i priprema materijale za pregled menadžmenta."></textarea>
    </div>

    <div class="form-row">
        <label for="assigned_to">Nosilac uloge (opciono)</label>
        <select name="assigned_to" id="assigned_to">
            <option value="">Nije dodeljeno</option>
            <?php foreach ($activePersonnelOptions as $option): ?>
                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (empty($activePersonnelOptions)): ?>
            <p class="item-meta">Nema unetih aktivnih osoba - prvo ih dodaj na stranici "Zaposleni i saradnici".</p>
        <?php endif; ?>
    </div>

    <div class="form-row">
        <label for="authority_level">Nivo ovlašćenja (opciono)</label>
        <input type="text" name="authority_level" id="authority_level"
            placeholder="npr. Može da odobrava izuzetke do srednjeg nivoa rizika">
    </div>

    <div class="form-row">
        <label for="related_control_ref">Povezana kontrola (opciono)</label>
        <input type="text" name="related_control_ref" id="related_control_ref" placeholder="npr. A.5.2">
    </div>

    <button type="submit" class="btn-primary">Dodaj ulogu</button>
</form>

<?php if (empty($allRoles)): ?>
    <p class="empty-state">Još uvek nema unetih uloga.</p>
<?php else: ?>
    <?php foreach ($allRoles as $role): ?>
        <?php include __DIR__ . '/../includes/role-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
