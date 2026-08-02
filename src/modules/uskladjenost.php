<?php
/**
 * src/modules/uskladjenost.php
 *
 * A.5.31-5.36: Usklađenost.
 *
 * Jedna tabela (compliance_items) pokriva svih šest kontrola,
 * razlikovanih preko control_ref - videti
 * db/migrations/003_add_compliance_items.sql. Svesna odluka da se NE
 * koristi postojeća legal_requirements tabela (koja pokriva samo
 * A.5.31): ona ostaje u šemi neiskorišćena, isto kao equipment,
 * storage_media i slične tabele bez svoje stavke menija - jedan
 * dosledan registar za svih šest kontrola je jednostavniji za
 * održavanje od dva paralelna mehanizma.
 *
 * Isti obrazac kao ostali moduli: toolbar sa Pomoć desno, modal za
 * dodavanje/uređivanje stavke. Promena statusa ostaje ugrađena forma
 * (jednostavan dropdown + dugme, automatski beleži last_reviewed_at) -
 * isti princip kao status kod rizika/ciljeva/promena, "Uredi" je NE
 * dira.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'uskladjenost';

$errors = [];

$validControlRefs = ['5.31', '5.32', '5.33', '5.34', '5.35', '5.36'];
$validStatuses     = ['usaglaseno', 'delimicno', 'neusaglaseno', 'nije_primenjivo'];

// --- Dodavanje stavke usklađenosti ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $controlRef    = $_POST['control_ref'] ?? '';
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $status        = $_POST['status'] ?? 'delimicno';
    $ownerId       = trim($_POST['owner_id'] ?? '');
    $nextReviewDue = trim($_POST['next_review_due'] ?? '');

    if (!in_array($controlRef, $validControlRefs, true)) {
        $errors[] = 'Izaberite kontrolu.';
    }
    if ($title === '') {
        $errors[] = 'Naziv stavke je obavezan.';
    }
    if (!in_array($status, $validStatuses, true)) {
        $errors[] = 'Izaberite status usklađenosti.';
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
            'INSERT INTO compliance_items
                (organization_id, control_ref, title, description, status, owner_id, next_review_due)
             VALUES
                (:org_id, :control_ref, :title, :description, :status, :owner_id, :next_review_due)'
        );
        $stmt->execute([
            'org_id'          => $organizationId,
            'control_ref'     => $controlRef,
            'title'           => $title,
            'description'     => $description !== '' ? $description : null,
            'status'          => $status,
            'owner_id'        => $ownerIdValue,
            'next_review_due' => $nextReviewDue !== '' ? $nextReviewDue : null,
        ]);

        header('Location: ?page=uskladjenost');
        exit;
    }
}

// --- Ažuriranje postojeće stavke (NE menja status) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id            = (int) ($_POST['id'] ?? 0);
    $controlRef    = $_POST['control_ref'] ?? '';
    $title         = trim($_POST['title'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $ownerId       = trim($_POST['owner_id'] ?? '');
    $nextReviewDue = trim($_POST['next_review_due'] ?? '');

    if (!in_array($controlRef, $validControlRefs, true)) {
        $errors[] = 'Izaberite kontrolu.';
    }
    if ($title === '') {
        $errors[] = 'Naziv stavke je obavezan.';
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
            'UPDATE compliance_items
             SET control_ref = :control_ref, title = :title, description = :description,
                 owner_id = :owner_id, next_review_due = :next_review_due
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'control_ref'     => $controlRef,
            'title'           => $title,
            'description'     => $description !== '' ? $description : null,
            'owner_id'        => $ownerIdValue,
            'next_review_due' => $nextReviewDue !== '' ? $nextReviewDue : null,
            'id'              => $id,
            'org_id'          => $organizationId,
        ]);

        header('Location: ?page=uskladjenost');
        exit;
    }
}

// --- Promena statusa (ujedno beleži pregled) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id        = (int) ($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if (in_array($newStatus, $validStatuses, true)) {
        $pdo->prepare(
            'UPDATE compliance_items SET status = :status, last_reviewed_at = CURDATE()
             WHERE id = :id AND organization_id = :org_id'
        )->execute(['status' => $newStatus, 'id' => $id, 'org_id' => $organizationId]);
    }

    header('Location: ?page=uskladjenost');
    exit;
}

// --- Brisanje stavke ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM compliance_items WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=uskladjenost');
    exit;
}

// --- Aktivne osobe za dropdown ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje stavki usklađenosti ---
$itemsStmt = $pdo->prepare(
    'SELECT ci.*, p.full_name AS owner_name
     FROM compliance_items ci
     LEFT JOIN personnel p ON p.id = ci.owner_id
     WHERE ci.organization_id = :org_id
     ORDER BY ci.control_ref, ci.title'
);
$itemsStmt->execute(['org_id' => $organizationId]);
$allItems = $itemsStmt->fetchAll();

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
    <button type="button" class="btn-primary" onclick="openAddComplianceModal()">+ Dodaj stavku</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allItems)): ?>
    <p class="empty-state">Još uvek nema unetih stavki usklađenosti.</p>
<?php else: ?>
    <?php foreach ($allItems as $item): ?>
        <?php include __DIR__ . '/../includes/compliance-item-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="compliance-modal-overlay" onclick="closeComplianceModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="compliance-modal-title">Dodaj stavku</span>
            <button type="button" class="modal-close" onclick="closeComplianceModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="compliance-modal-form">
            <input type="hidden" name="action" id="compliance-modal-action" value="add">
            <input type="hidden" name="id" id="compliance-modal-id" value="">

            <div class="form-row">
                <label for="modal_control_ref">Kontrola</label>
                <select name="control_ref" id="modal_control_ref" required>
                    <option value="">Izaberite...</option>
                    <option value="5.31">5.31 - Pravni, statutarni, regulatorni i ugovorni zahtevi</option>
                    <option value="5.32">5.32 - Prava intelektualne svojine</option>
                    <option value="5.33">5.33 - Zaštita zapisa</option>
                    <option value="5.34">5.34 - Privatnost i zaštita ličnih podataka</option>
                    <option value="5.35">5.35 - Nezavisna provera bezbednosti informacija</option>
                    <option value="5.36">5.36 - Usklađenost sa politikama, pravilima i standardima</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_title">Naziv stavke</label>
                <input type="text" name="title" id="modal_title" required
                    placeholder="npr. Zakon o zaštiti podataka o ličnosti">
            </div>

            <div class="form-row">
                <label for="modal_description">Opis (opciono)</label>
                <textarea name="description" id="modal_description" rows="2"
                    placeholder="npr. Zahteva pravni osnov za obradu i evidenciju aktivnosti obrade ličnih podataka klijenata."></textarea>
            </div>

            <div class="form-row" id="modal-status-row">
                <label for="modal_status">Status usklađenosti</label>
                <select name="status" id="modal_status">
                    <option value="usaglaseno">Usaglašeno</option>
                    <option value="delimicno">Delimično usaglašeno</option>
                    <option value="neusaglaseno">Neusaglašeno</option>
                    <option value="nije_primenjivo">Nije primenjivo</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_owner_id">Nosilac (opciono)</label>
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
                <label for="modal_next_review_due">Sledeći pregled dospeva (opciono)</label>
                <input type="date" name="next_review_due" id="modal_next_review_due">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromComplianceModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeComplianceModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddComplianceModal() {
    document.getElementById('compliance-modal-title').textContent = 'Dodaj stavku';
    document.getElementById('compliance-modal-action').value = 'add';
    document.getElementById('compliance-modal-id').value = '';
    document.getElementById('modal_control_ref').value = '';
    document.getElementById('modal_title').value = '';
    document.getElementById('modal_description').value = '';
    document.getElementById('modal_status').value = 'delimicno';
    document.getElementById('modal_owner_id').value = '';
    document.getElementById('modal_next_review_due').value = '';
    document.getElementById('modal-status-row').classList.remove('is-hidden');
    document.getElementById('compliance-modal-overlay').classList.add('is-open');
}

function openEditComplianceModal(item) {
    document.getElementById('compliance-modal-title').textContent = 'Uredi stavku';
    document.getElementById('compliance-modal-action').value = 'update';
    document.getElementById('compliance-modal-id').value = item.id;
    document.getElementById('modal_control_ref').value = item.control_ref;
    document.getElementById('modal_title').value = item.title;
    document.getElementById('modal_description').value = item.description;
    document.getElementById('modal_owner_id').value = item.owner_id;
    document.getElementById('modal_next_review_due').value = item.next_review_due;
    // Status se ne menja kroz ovu formu kad se uređuje postojeća stavka -
    // sakriveno da se ne stvori utisak da menja polje koje ova akcija ne dira.
    document.getElementById('modal-status-row').classList.add('is-hidden');
    document.getElementById('compliance-modal-overlay').classList.add('is-open');
}

function closeComplianceModal() {
    document.getElementById('compliance-modal-overlay').classList.remove('is-open');
}

function openHelpFromComplianceModal() {
    closeComplianceModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeComplianceModal();
    }
});
</script>
