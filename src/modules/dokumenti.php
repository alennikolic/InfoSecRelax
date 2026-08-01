<?php
/**
 * src/modules/dokumenti.php
 *
 * Klauzula 7.5: Dokumentovane informacije.
 *
 * Koristi deljene helper funkcije iz includes/document-helpers.php
 * (createDocument, recordDocumentVersion) umesto da sam piše insert i
 * verzionisanje - iste funkcije će kasnije koristiti i politike.php,
 * koji dodaje samo tanak red u tabeli policies povrh istog dokumenta.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/document-helpers.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

$validDocTypes        = ['politika', 'procedura', 'registar', 'zapisnik', 'ostalo'];
$validClassifications = ['javno', 'interno', 'poverljivo', 'strogo_poverljivo'];

// --- Dodavanje novog dokumenta ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_document') {
    $title          = trim($_POST['title'] ?? '');
    $docType        = $_POST['doc_type'] ?? '';
    $classification = $_POST['classification'] ?? 'interno';
    $currentVersion = trim($_POST['current_version'] ?? '1.0');
    $fileReference  = trim($_POST['file_reference'] ?? '');
    $ownerId        = trim($_POST['owner_id'] ?? '');
    $approvedBy     = trim($_POST['approved_by'] ?? '');
    $approvedAt     = trim($_POST['approved_at'] ?? '');
    $nextReviewDue  = trim($_POST['next_review_due'] ?? '');

    if ($title === '') {
        $errors[] = 'Naziv dokumenta je obavezan.';
    }
    if (!in_array($docType, $validDocTypes, true)) {
        $errors[] = 'Izaberite vrstu dokumenta.';
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
        createDocument($pdo, $organizationId, [
            'title'           => $title,
            'doc_type'        => $docType,
            'classification'  => $classification,
            'current_version' => $currentVersion,
            'file_reference'  => $fileReference !== '' ? $fileReference : null,
            'owner_id'        => $ownerIdValue,
            'approved_by'     => $approvedByValue,
            'approved_at'     => $approvedAt !== '' ? $approvedAt : null,
            'next_review_due' => $nextReviewDue !== '' ? $nextReviewDue : null,
        ]);

        header('Location: ?page=dokumenti');
        exit;
    }
}

// --- Dodavanje nove verzije postojećeg dokumenta ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_version') {
    $documentId    = (int) ($_POST['document_id'] ?? 0);
    $versionNumber = trim($_POST['version_number'] ?? '');
    $changeSummary = trim($_POST['change_summary'] ?? '');
    $fileReference = trim($_POST['file_reference'] ?? '');
    $changedBy     = trim($_POST['changed_by'] ?? '');

    $documentCheck = $pdo->prepare('SELECT id FROM documents WHERE id = :id AND organization_id = :org_id');
    $documentCheck->execute(['id' => $documentId, 'org_id' => $organizationId]);

    if ($documentCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznat dokument.';
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
        recordDocumentVersion($pdo, $documentId, $versionNumber, [
            'changed_by'     => $changedByValue,
            'change_summary' => $changeSummary !== '' ? $changeSummary : null,
            'file_reference' => $fileReference !== '' ? $fileReference : null,
        ]);

        header('Location: ?page=dokumenti');
        exit;
    }
}

// --- Brisanje dokumenta (istorija verzija se briše kaskadno preko FK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_document') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM documents WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=dokumenti');
    exit;
}

// --- Aktivne osobe za dropdown-e ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje dokumenata ---
$documentsStmt = $pdo->prepare(
    'SELECT d.*, o.full_name AS owner_name, a.full_name AS approved_by_name
     FROM documents d
     LEFT JOIN personnel o ON o.id = d.owner_id
     LEFT JOIN personnel a ON a.id = d.approved_by
     WHERE d.organization_id = :org_id
     ORDER BY d.doc_type, d.title'
);
$documentsStmt->execute(['org_id' => $organizationId]);
$allDocuments = $documentsStmt->fetchAll();

// --- Istorija verzija za sve dokumente ove organizacije, grupisana po document_id ---
$versionsStmt = $pdo->prepare(
    'SELECT v.*, p.full_name AS changed_by_name
     FROM document_versions v
     INNER JOIN documents d ON d.id = v.document_id
     LEFT JOIN personnel p ON p.id = v.changed_by
     WHERE d.organization_id = :org_id
     ORDER BY v.created_at DESC'
);
$versionsStmt->execute(['org_id' => $organizationId]);

$versionsByDocument = [];
foreach ($versionsStmt->fetchAll() as $version) {
    $versionsByDocument[$version['document_id']][] = $version;
}
?>

<p class="module-intro">
    Klauzula 7.5 traži kontrolu dokumentovanih informacija - jasno ko je
    vlasnik, ko odobrava, kada se dokument ponovo pregleda, i istoriju
    izmena kroz verzije.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="factor-form">
    <input type="hidden" name="action" value="add_document">

    <div class="form-row">
        <label for="title">Naziv dokumenta</label>
        <input type="text" name="title" id="title" required placeholder="npr. Procedura za rezervne kopije">
    </div>

    <div class="form-row">
        <label for="doc_type">Vrsta dokumenta</label>
        <select name="doc_type" id="doc_type" required>
            <option value="">Izaberite...</option>
            <option value="politika">Politika</option>
            <option value="procedura">Procedura</option>
            <option value="registar">Registar</option>
            <option value="zapisnik">Zapisnik</option>
            <option value="ostalo">Ostalo</option>
        </select>
    </div>

    <div class="form-row">
        <label for="classification">Klasifikacija</label>
        <select name="classification" id="classification">
            <option value="javno">Javno</option>
            <option value="interno" selected>Interno</option>
            <option value="poverljivo">Poverljivo</option>
            <option value="strogo_poverljivo">Strogo poverljivo</option>
        </select>
    </div>

    <div class="form-row">
        <label for="current_version">Oznaka verzije</label>
        <input type="text" name="current_version" id="current_version" value="1.0" required>
    </div>

    <div class="form-row">
        <label for="file_reference">Putanja ili URL do fajla (opciono)</label>
        <input type="text" name="file_reference" id="file_reference" placeholder="npr. /dokumenti/procedura-backup.pdf">
    </div>

    <div class="form-row">
        <label for="owner_id">Vlasnik dokumenta (opciono)</label>
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

    <div class="form-row">
        <label for="approved_by">Odobrio (opciono)</label>
        <select name="approved_by" id="approved_by">
            <option value="">Nije dodeljen</option>
            <?php foreach ($activePersonnelOptions as $option): ?>
                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="form-row">
        <label for="approved_at">Datum odobrenja (opciono)</label>
        <input type="date" name="approved_at" id="approved_at">
    </div>

    <div class="form-row">
        <label for="next_review_due">Sledeći pregled dospeva (opciono)</label>
        <input type="date" name="next_review_due" id="next_review_due">
    </div>

    <button type="submit" class="btn-primary">Dodaj dokument</button>
</form>

<?php if (empty($allDocuments)): ?>
    <p class="empty-state">Još uvek nema unetih dokumenata.</p>
<?php else: ?>
    <?php foreach ($allDocuments as $document): ?>
        <?php $versions = $versionsByDocument[$document['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/document-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
