<?php
/**
 * src/modules/uloge.php
 *
 * Klauzula 5.3 / A.5.2: Uloge i odgovornosti za bezbednost informacija.
 *
 * Prost CRUD (dodaj/uredi/prikaži/obriši), isti obrazac kao
 * kontekst.php: toolbar sa Pomoć desno, modal za dodavanje i
 * uređivanje. Jedina posebnost je opciono polje assigned_to, koje se
 * bira iz personnel - namerno samo aktivne osobe, da se nova uloga ne
 * dodeli nekome ko je već otišao iz firme. Brisanje osobe u
 * zaposleni.php ne briše ništa ovde: assigned_to FK ima ON DELETE SET
 * NULL, uloga samo ostane bez nosioca.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'uloge';

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

// --- Ažuriranje postojeće uloge ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id                = (int) ($_POST['id'] ?? 0);
    $roleName          = trim($_POST['role_name'] ?? '');
    $description       = trim($_POST['description'] ?? '');
    $assignedTo        = trim($_POST['assigned_to'] ?? '');
    $authorityLevel    = trim($_POST['authority_level'] ?? '');
    $relatedControlRef = trim($_POST['related_control_ref'] ?? '');

    if ($roleName === '') {
        $errors[] = 'Naziv uloge je obavezan.';
    }

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
            'UPDATE roles_responsibilities
             SET role_name = :role_name, description = :description, assigned_to = :assigned_to,
                 authority_level = :authority_level, related_control_ref = :related_control_ref
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'role_name'           => $roleName,
            'description'         => $description !== '' ? $description : null,
            'assigned_to'         => $assignedToId,
            'authority_level'     => $authorityLevel !== '' ? $authorityLevel : null,
            'related_control_ref' => $relatedControlRef !== '' ? $relatedControlRef : null,
            'id'                  => $id,
            'org_id'              => $organizationId,
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
    <button type="button" class="btn-primary" onclick="openAddRoleModal()">+ Dodaj ulogu</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allRoles)): ?>
    <p class="empty-state">Još uvek nema unetih uloga.</p>
<?php else: ?>
    <?php foreach ($allRoles as $role): ?>
        <?php include __DIR__ . '/../includes/role-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="role-modal-overlay" onclick="closeRoleModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="role-modal-title">Dodaj ulogu</span>
            <button type="button" class="modal-close" onclick="closeRoleModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="role-modal-form">
            <input type="hidden" name="action" id="role-modal-action" value="add">
            <input type="hidden" name="id" id="role-modal-id" value="">

            <div class="form-row">
                <label for="modal_role_name">Naziv uloge</label>
                <input type="text" name="role_name" id="modal_role_name" required
                    placeholder="npr. Koordinator ISMS-a, Vlasnik sredstva - serveri">
            </div>

            <div class="form-row">
                <label for="modal_description">Opis (opciono)</label>
                <textarea name="description" id="modal_description" rows="2"
                    placeholder="npr. Koordinira aktivnosti ISMS-a, prati rokove i priprema materijale za pregled menadžmenta."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_assigned_to">Nosilac uloge (opciono)</label>
                <select name="assigned_to" id="modal_assigned_to">
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
                <label for="modal_authority_level">Nivo ovlašćenja (opciono)</label>
                <input type="text" name="authority_level" id="modal_authority_level"
                    placeholder="npr. Može da odobrava izuzetke do srednjeg nivoa rizika">
            </div>

            <div class="form-row">
                <label for="modal_related_control_ref">Povezana kontrola (opciono)</label>
                <input type="text" name="related_control_ref" id="modal_related_control_ref" placeholder="npr. A.5.2">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromRoleModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeRoleModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddRoleModal() {
    document.getElementById('role-modal-title').textContent = 'Dodaj ulogu';
    document.getElementById('role-modal-action').value = 'add';
    document.getElementById('role-modal-id').value = '';
    document.getElementById('modal_role_name').value = '';
    document.getElementById('modal_description').value = '';
    document.getElementById('modal_assigned_to').value = '';
    document.getElementById('modal_authority_level').value = '';
    document.getElementById('modal_related_control_ref').value = '';
    document.getElementById('role-modal-overlay').classList.add('is-open');
}

function openEditRoleModal(role) {
    document.getElementById('role-modal-title').textContent = 'Uredi ulogu';
    document.getElementById('role-modal-action').value = 'update';
    document.getElementById('role-modal-id').value = role.id;
    document.getElementById('modal_role_name').value = role.role_name;
    document.getElementById('modal_description').value = role.description;
    document.getElementById('modal_assigned_to').value = role.assigned_to;
    document.getElementById('modal_authority_level').value = role.authority_level;
    document.getElementById('modal_related_control_ref').value = role.related_control_ref;
    document.getElementById('role-modal-overlay').classList.add('is-open');
}

function closeRoleModal() {
    document.getElementById('role-modal-overlay').classList.remove('is-open');
}

function openHelpFromRoleModal() {
    closeRoleModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeRoleModal();
    }
});
</script>
