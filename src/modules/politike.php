<?php
/**
 * src/modules/politike.php
 *
 * Klauzula 5.2 / A.5.1: Politike bezbednosti informacija.
 *
 * Poslednji modul, i lakši od većine zahvaljujući postojećoj
 * infrastrukturi: politika je tanak red u policies koji upućuje na
 * documents (isto createDocument()/recordDocumentVersion() iz
 * includes/document-helpers.php kao u dokumenti.php, bez ponovnog
 * pisanja insert/verzionisanje logike).
 *
 * policy_acknowledgments prati potvrde zaposlenih - isto pojednostavljenje
 * kao training_attendance u kompetentnost.php: dodavanje potvrde odmah
 * znači "potvrđeno" (acknowledged_at = NOW() u trenutku dodavanja), bez
 * odvojenog koraka "dodeljeno pa naknadno potvrđeno".
 *
 * Brisanje politike briše documents red (ne policies direktno) - cela
 * kaskada (policy, verzije, potvrde) automatski nestaje preko FK lanca
 * documents -> policies -> policy_acknowledgments i documents ->
 * document_versions.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/document-helpers.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'politike';

$errors = [];

$validPolicyTypes     = ['opsta', 'tematska'];
$validClassifications = ['javno', 'interno', 'poverljivo', 'strogo_poverljivo'];

// --- Dodavanje nove politike (dokument + policy red) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_policy') {
    $title                  = trim($_POST['title'] ?? '');
    $policyType             = $_POST['policy_type'] ?? '';
    $topic                  = trim($_POST['topic'] ?? '');
    $acknowledgmentRequired = isset($_POST['acknowledgment_required']) ? 1 : 0;
    $classification         = $_POST['classification'] ?? 'interno';
    $currentVersion         = trim($_POST['current_version'] ?? '1.0');
    $ownerId                = trim($_POST['owner_id'] ?? '');
    $approvedBy             = trim($_POST['approved_by'] ?? '');
    $approvedAt             = trim($_POST['approved_at'] ?? '');
    $nextReviewDue          = trim($_POST['next_review_due'] ?? '');

    if ($title === '') {
        $errors[] = 'Naziv politike je obavezan.';
    }
    if (!in_array($policyType, $validPolicyTypes, true)) {
        $errors[] = 'Izaberite vrstu politike.';
    }
    if (!in_array($classification, $validClassifications, true)) {
        $errors[] = 'Izaberite klasifikaciju.';
    }
    if ($currentVersion === '') {
        $errors[] = 'Oznaka verzije je obavezna.';
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

    $approvedByValue = null;
    if ($approvedBy !== '') {
        $approvedByValue = (int) $approvedBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $approvedByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani odobravalac nije pronađen.';
            $approvedByValue = null;
        }
    }

    if (empty($errors)) {
        $documentId = createDocument($pdo, $organizationId, [
            'title'           => $title,
            'doc_type'        => 'politika',
            'classification'  => $classification,
            'current_version' => $currentVersion,
            'owner_id'        => $ownerIdValue,
            'approved_by'     => $approvedByValue,
            'approved_at'     => $approvedAt !== '' ? $approvedAt : null,
            'next_review_due' => $nextReviewDue !== '' ? $nextReviewDue : null,
        ]);

        $stmt = $pdo->prepare(
            'INSERT INTO policies (organization_id, document_id, policy_type, topic, acknowledgment_required)
             VALUES (:org_id, :document_id, :policy_type, :topic, :acknowledgment_required)'
        );
        $stmt->execute([
            'org_id'                  => $organizationId,
            'document_id'             => $documentId,
            'policy_type'             => $policyType,
            'topic'                   => $topic !== '' ? $topic : null,
            'acknowledgment_required' => $acknowledgmentRequired,
        ]);

        header('Location: ?page=politike');
        exit;
    }
}

// --- Ažuriranje postojeće politike (naziv, vrsta, klasifikacija, vlasnik,
// odobrenje - NE i oznaka verzije, ta ide isključivo kroz "Nova verzija"
// ispod, da se ne pokvari veza sa istorijom verzija) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_policy') {
    $id                     = (int) ($_POST['id'] ?? 0);
    $title                  = trim($_POST['title'] ?? '');
    $policyType             = $_POST['policy_type'] ?? '';
    $topic                  = trim($_POST['topic'] ?? '');
    $acknowledgmentRequired = isset($_POST['acknowledgment_required']) ? 1 : 0;
    $classification         = $_POST['classification'] ?? 'interno';
    $ownerId                = trim($_POST['owner_id'] ?? '');
    $approvedBy             = trim($_POST['approved_by'] ?? '');
    $approvedAt             = trim($_POST['approved_at'] ?? '');
    $nextReviewDue          = trim($_POST['next_review_due'] ?? '');

    $policyCheck = $pdo->prepare('SELECT document_id FROM policies WHERE id = :id AND organization_id = :org_id');
    $policyCheck->execute(['id' => $id, 'org_id' => $organizationId]);
    $documentId = $policyCheck->fetchColumn();

    if ($documentId === false) {
        $errors[] = 'Nepoznata politika.';
    }
    if ($title === '') {
        $errors[] = 'Naziv politike je obavezan.';
    }
    if (!in_array($policyType, $validPolicyTypes, true)) {
        $errors[] = 'Izaberite vrstu politike.';
    }
    if (!in_array($classification, $validClassifications, true)) {
        $errors[] = 'Izaberite klasifikaciju.';
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

    $approvedByValue = null;
    if ($approvedBy !== '') {
        $approvedByValue = (int) $approvedBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $approvedByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani odobravalac nije pronađen.';
            $approvedByValue = null;
        }
    }

    if (empty($errors)) {
        $pdo->prepare(
            'UPDATE documents SET title = :title, classification = :classification,
                owner_id = :owner_id, approved_by = :approved_by, approved_at = :approved_at,
                next_review_due = :next_review_due
             WHERE id = :document_id AND organization_id = :org_id'
        )->execute([
            'title'           => $title,
            'classification'  => $classification,
            'owner_id'        => $ownerIdValue,
            'approved_by'     => $approvedByValue,
            'approved_at'     => $approvedAt !== '' ? $approvedAt : null,
            'next_review_due' => $nextReviewDue !== '' ? $nextReviewDue : null,
            'document_id'     => $documentId,
            'org_id'          => $organizationId,
        ]);

        $pdo->prepare(
            'UPDATE policies SET policy_type = :policy_type, topic = :topic,
                acknowledgment_required = :acknowledgment_required
             WHERE id = :id AND organization_id = :org_id'
        )->execute([
            'policy_type'             => $policyType,
            'topic'                   => $topic !== '' ? $topic : null,
            'acknowledgment_required' => $acknowledgmentRequired,
            'id'                      => $id,
            'org_id'                  => $organizationId,
        ]);

        header('Location: ?page=politike');
        exit;
    }
}

// --- Dodavanje nove verzije postojeće politike ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_version') {
    $policyId      = (int) ($_POST['policy_id'] ?? 0);
    $versionNumber = trim($_POST['version_number'] ?? '');
    $changeSummary = trim($_POST['change_summary'] ?? '');
    $changedBy     = trim($_POST['changed_by'] ?? '');

    $policyCheck = $pdo->prepare('SELECT document_id FROM policies WHERE id = :id AND organization_id = :org_id');
    $policyCheck->execute(['id' => $policyId, 'org_id' => $organizationId]);
    $documentId = $policyCheck->fetchColumn();

    if ($documentId === false) {
        $errors[] = 'Nepoznata politika.';
    }
    if ($versionNumber === '') {
        $errors[] = 'Oznaka nove verzije je obavezna.';
    }

    $changedByValue = null;
    if ($changedBy !== '') {
        $changedByValue = (int) $changedBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $changedByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrana osoba nije pronađena.';
            $changedByValue = null;
        }
    }

    if (empty($errors)) {
        recordDocumentVersion($pdo, (int) $documentId, $versionNumber, [
            'changed_by'     => $changedByValue,
            'change_summary' => $changeSummary !== '' ? $changeSummary : null,
        ]);

        header('Location: ?page=politike');
        exit;
    }
}

// --- Dodavanje potvrde zaposlenog ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_acknowledgment') {
    $policyId    = (int) ($_POST['policy_id'] ?? 0);
    $personnelId = trim($_POST['personnel_id'] ?? '');

    $policyCheck = $pdo->prepare('SELECT id FROM policies WHERE id = :id AND organization_id = :org_id');
    $policyCheck->execute(['id' => $policyId, 'org_id' => $organizationId]);

    if ($policyCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznata politika.';
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
        // INSERT IGNORE zbog UNIQUE KEY (policy_id, personnel_id) - ista
        // osoba se ne može potvrditi dvaput za istu politiku.
        $pdo->prepare(
            'INSERT IGNORE INTO policy_acknowledgments (policy_id, personnel_id, acknowledged_at)
             VALUES (:policy_id, :personnel_id, NOW())'
        )->execute(['policy_id' => $policyId, 'personnel_id' => $personnelIdValue]);

        header('Location: ?page=politike');
        exit;
    }
}

// --- Brisanje potvrde ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_acknowledgment') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE pa FROM policy_acknowledgments pa
         INNER JOIN policies p ON p.id = pa.policy_id
         WHERE pa.id = :id AND p.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=politike');
    exit;
}

// --- Brisanje politike (preko documents - kaskada čisti sve povezano) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_policy') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE d FROM documents d
         INNER JOIN policies p ON p.document_id = d.id
         WHERE p.id = :id AND p.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=politike');
    exit;
}

// --- Aktivne osobe za dropdown-e ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje politika (sa podacima dokumenta) ---
$policiesStmt = $pdo->prepare(
    'SELECT p.*, d.title, d.classification, d.current_version, d.next_review_due,
            d.approved_at, d.owner_id, d.approved_by,
            o.full_name AS owner_name, ab.full_name AS approved_by_name
     FROM policies p
     INNER JOIN documents d ON d.id = p.document_id
     LEFT JOIN personnel o ON o.id = d.owner_id
     LEFT JOIN personnel ab ON ab.id = d.approved_by
     WHERE p.organization_id = :org_id
     ORDER BY p.policy_type, d.title'
);
$policiesStmt->execute(['org_id' => $organizationId]);
$allPolicies = $policiesStmt->fetchAll();

// --- Istorija verzija za sve politike ove organizacije, grupisana po document_id ---
$versionsStmt = $pdo->prepare(
    'SELECT v.*, per.full_name AS changed_by_name
     FROM document_versions v
     INNER JOIN policies p ON p.document_id = v.document_id
     LEFT JOIN personnel per ON per.id = v.changed_by
     WHERE p.organization_id = :org_id
     ORDER BY v.created_at DESC'
);
$versionsStmt->execute(['org_id' => $organizationId]);

$versionsByPolicy = [];
foreach ($versionsStmt->fetchAll() as $version) {
    $versionsByPolicy[$version['document_id']][] = $version;
}

// --- Potvrde za sve politike ove organizacije, grupisane po policy_id ---
$acknowledgmentsStmt = $pdo->prepare(
    'SELECT pa.*, per.full_name AS person_name
     FROM policy_acknowledgments pa
     INNER JOIN policies p ON p.id = pa.policy_id
     INNER JOIN personnel per ON per.id = pa.personnel_id
     WHERE p.organization_id = :org_id
     ORDER BY per.full_name'
);
$acknowledgmentsStmt->execute(['org_id' => $organizationId]);

$acknowledgmentsByPolicy = [];
foreach ($acknowledgmentsStmt->fetchAll() as $acknowledgment) {
    $acknowledgmentsByPolicy[$acknowledgment['policy_id']][] = $acknowledgment;
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

<div class="toolbar">
    <button type="button" class="btn-primary" onclick="openAddPolicyModal()">+ Dodaj politiku</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allPolicies)): ?>
    <p class="empty-state">Još uvek nema unetih politika.</p>
<?php else: ?>
    <?php foreach ($allPolicies as $policy): ?>
        <?php $versions = $versionsByPolicy[$policy['document_id']] ?? []; ?>
        <?php $acknowledgments = $acknowledgmentsByPolicy[$policy['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/policy-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="policy-modal-overlay" onclick="closePolicyModal()">
    <div class="modal-box modal-box-wide" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="policy-modal-title">Dodaj politiku</span>
            <button type="button" class="modal-close" onclick="closePolicyModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="policy-modal-form">
            <input type="hidden" name="action" id="policy-modal-action" value="add_policy">
            <input type="hidden" name="id" id="policy-modal-id" value="">

            <div class="form-row">
                <label for="modal_title">Naziv politike</label>
                <input type="text" name="title" id="modal_title" required
                    placeholder="npr. Politika bezbednosti informacija, Politika kontrole pristupa">
            </div>

            <div class="form-row">
                <label for="modal_policy_type">Vrsta</label>
                <select name="policy_type" id="modal_policy_type" required>
                    <option value="">Izaberite...</option>
                    <option value="opsta">Opšta (Klauzula 5.2)</option>
                    <option value="tematska">Tematska (A.5.1)</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_topic">Tema (opciono, za tematske politike)</label>
                <input type="text" name="topic" id="modal_topic"
                    placeholder="npr. kontrola_pristupa, backup, rad_na_daljinu, kriptografija">
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="acknowledgment_required" id="modal_acknowledgment_required" value="1" checked>
                    Zahteva potvrdu zaposlenih
                </label>
            </div>

            <div class="form-row">
                <label for="modal_classification">Klasifikacija</label>
                <select name="classification" id="modal_classification">
                    <option value="javno">Javno</option>
                    <option value="interno">Interno</option>
                    <option value="poverljivo">Poverljivo</option>
                    <option value="strogo_poverljivo">Strogo poverljivo</option>
                </select>
            </div>

            <div class="form-row" id="modal-current-version-row">
                <label for="modal_current_version">Oznaka verzije</label>
                <input type="text" name="current_version" id="modal_current_version" value="1.0" required>
            </div>

            <div class="form-row">
                <label for="modal_owner_id">Vlasnik politike (opciono)</label>
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
                <label for="modal_approved_by">Odobrio (opciono)</label>
                <select name="approved_by" id="modal_approved_by">
                    <option value="">Nije dodeljen</option>
                    <?php foreach ($activePersonnelOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_approved_at">Datum odobrenja (opciono)</label>
                <input type="date" name="approved_at" id="modal_approved_at">
            </div>

            <div class="form-row">
                <label for="modal_next_review_due">Sledeći pregled dospeva (opciono)</label>
                <input type="date" name="next_review_due" id="modal_next_review_due">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromPolicyModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closePolicyModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<div class="modal-overlay" id="policy-version-modal-overlay" onclick="closePolicyVersionModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="policy-version-modal-title">Nova verzija</span>
            <button type="button" class="modal-close" onclick="closePolicyVersionModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="add_version">
            <input type="hidden" name="policy_id" id="policy-version-modal-policy-id" value="">

            <div class="form-row">
                <label for="modal_version_number">Nova verzija</label>
                <input type="text" name="version_number" id="modal_version_number" required
                    placeholder="npr. 1.1">
            </div>

            <div class="form-row">
                <label for="modal_change_summary">Šta je izmenjeno (opciono)</label>
                <textarea name="change_summary" id="modal_change_summary" rows="2"></textarea>
            </div>

            <div class="form-row">
                <label for="modal_changed_by">Izmenio (opciono)</label>
                <select name="changed_by" id="modal_changed_by">
                    <option value="">Nije dodeljen</option>
                    <?php foreach ($activePersonnelOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromPolicyVersionModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closePolicyVersionModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function openAddPolicyModal() {
    document.getElementById('policy-modal-title').textContent = 'Dodaj politiku';
    document.getElementById('policy-modal-action').value = 'add_policy';
    document.getElementById('policy-modal-id').value = '';
    document.getElementById('modal_title').value = '';
    document.getElementById('modal_policy_type').value = '';
    document.getElementById('modal_topic').value = '';
    document.getElementById('modal_acknowledgment_required').checked = true;
    document.getElementById('modal_classification').value = 'interno';
    document.getElementById('modal_owner_id').value = '';
    document.getElementById('modal_approved_by').value = '';
    document.getElementById('modal_approved_at').value = '';
    document.getElementById('modal_next_review_due').value = '';
    document.getElementById('modal-current-version-row').classList.remove('is-hidden');
    document.getElementById('modal_current_version').required = true;
    document.getElementById('modal_current_version').value = '1.0';
    document.getElementById('policy-modal-overlay').classList.add('is-open');
}

function openEditPolicyModal(policy) {
    document.getElementById('policy-modal-title').textContent = 'Uredi politiku';
    document.getElementById('policy-modal-action').value = 'update_policy';
    document.getElementById('policy-modal-id').value = policy.id;
    document.getElementById('modal_title').value = policy.title;
    document.getElementById('modal_policy_type').value = policy.policy_type;
    document.getElementById('modal_topic').value = policy.topic;
    document.getElementById('modal_acknowledgment_required').checked = policy.acknowledgment_required;
    document.getElementById('modal_classification').value = policy.classification;
    document.getElementById('modal_owner_id').value = policy.owner_id;
    document.getElementById('modal_approved_by').value = policy.approved_by;
    document.getElementById('modal_approved_at').value = policy.approved_at;
    document.getElementById('modal_next_review_due').value = policy.next_review_due;
    document.getElementById('modal-current-version-row').classList.add('is-hidden');
    document.getElementById('modal_current_version').required = false;
    document.getElementById('policy-modal-overlay').classList.add('is-open');
}

function closePolicyModal() {
    document.getElementById('policy-modal-overlay').classList.remove('is-open');
}

function openHelpFromPolicyModal() {
    closePolicyModal();
    openHelpModal();
}

function openPolicyVersionModal(policyId, policyTitle) {
    document.getElementById('policy-version-modal-title').textContent = 'Nova verzija — ' + policyTitle;
    document.getElementById('policy-version-modal-policy-id').value = policyId;
    document.getElementById('modal_version_number').value = '';
    document.getElementById('modal_change_summary').value = '';
    document.getElementById('modal_changed_by').value = '';
    document.getElementById('policy-version-modal-overlay').classList.add('is-open');
}

function closePolicyVersionModal() {
    document.getElementById('policy-version-modal-overlay').classList.remove('is-open');
}

function openHelpFromPolicyVersionModal() {
    closePolicyVersionModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closePolicyModal();
        closePolicyVersionModal();
    }
});
</script>
