<?php
/**
 * src/modules/interni-audit.php
 *
 * Klauzula 9.2: Interni audit.
 *
 * internal_audits -> audit_findings, isti roditelj-dete obrazac kao
 * svuda do sada. auditor_name je obično tekstualno polje, ne veza ka
 * personnel - auditor mora biti nezavisan od procesa koji proverava, pa
 * često nije iz sopstvene firme (spoljni auditor) i ne mora imati red
 * u personnel.
 *
 * Isti obrazac kao ostali moduli: toolbar sa Pomoć desno, modal za
 * dodavanje/uređivanje audita, "Dodaj nalaz" kao poseban modal otvoren
 * dugmetom u kartici.
 *
 * audit_findings.corrective_action_id namerno nije u formi -
 * corrective_actions je deljena tabela i sa security_events
 * (incidenti.php), pa čeka budući korektivne-mere.php da se gradi kad
 * oba izvora postoje, ne napola.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'interni-audit';

$errors = [];

$validSeverities = ['nizak', 'srednji', 'visok'];

// --- Dodavanje internog audita ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_audit') {
    $auditDate         = trim($_POST['audit_date'] ?? '');
    $scope             = trim($_POST['scope'] ?? '');
    $auditorName       = trim($_POST['auditor_name'] ?? '');
    $isExternalAuditor = isset($_POST['is_external_auditor']) ? 1 : 0;
    $reportReference   = trim($_POST['report_reference'] ?? '');

    if ($auditDate === '') {
        $errors[] = 'Datum audita je obavezan.';
    }
    if ($auditorName === '') {
        $errors[] = 'Ime auditora je obavezno.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO internal_audits
                (organization_id, audit_date, scope, auditor_name, is_external_auditor, report_reference)
             VALUES
                (:org_id, :audit_date, :scope, :auditor_name, :is_external_auditor, :report_reference)'
        );
        $stmt->execute([
            'org_id'              => $organizationId,
            'audit_date'          => $auditDate,
            'scope'               => $scope !== '' ? $scope : null,
            'auditor_name'        => $auditorName,
            'is_external_auditor' => $isExternalAuditor,
            'report_reference'    => $reportReference !== '' ? $reportReference : null,
        ]);

        header('Location: ?page=interni-audit');
        exit;
    }
}

// --- Ažuriranje postojećeg audita ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_audit') {
    $id                = (int) ($_POST['id'] ?? 0);
    $auditDate         = trim($_POST['audit_date'] ?? '');
    $scope             = trim($_POST['scope'] ?? '');
    $auditorName       = trim($_POST['auditor_name'] ?? '');
    $isExternalAuditor = isset($_POST['is_external_auditor']) ? 1 : 0;
    $reportReference   = trim($_POST['report_reference'] ?? '');

    if ($auditDate === '') {
        $errors[] = 'Datum audita je obavezan.';
    }
    if ($auditorName === '') {
        $errors[] = 'Ime auditora je obavezno.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE internal_audits
             SET audit_date = :audit_date, scope = :scope, auditor_name = :auditor_name,
                 is_external_auditor = :is_external_auditor, report_reference = :report_reference
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'audit_date'          => $auditDate,
            'scope'               => $scope !== '' ? $scope : null,
            'auditor_name'        => $auditorName,
            'is_external_auditor' => $isExternalAuditor,
            'report_reference'    => $reportReference !== '' ? $reportReference : null,
            'id'                  => $id,
            'org_id'              => $organizationId,
        ]);

        header('Location: ?page=interni-audit');
        exit;
    }
}

// --- Brisanje audita (nalazi se brišu kaskadno preko FK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_audit') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM internal_audits WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=interni-audit');
    exit;
}

// --- Dodavanje nalaza ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_finding') {
    $internalAuditId = (int) ($_POST['internal_audit_id'] ?? 0);
    $description     = trim($_POST['description'] ?? '');
    $severity        = $_POST['severity'] ?? 'srednji';

    $auditCheck = $pdo->prepare('SELECT id FROM internal_audits WHERE id = :id AND organization_id = :org_id');
    $auditCheck->execute(['id' => $internalAuditId, 'org_id' => $organizationId]);

    if ($auditCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznat audit.';
    }
    if ($description === '') {
        $errors[] = 'Opis nalaza je obavezan.';
    }
    if (!in_array($severity, $validSeverities, true)) {
        $errors[] = 'Izaberite ozbiljnost.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO audit_findings (internal_audit_id, description, severity)
             VALUES (:internal_audit_id, :description, :severity)'
        );
        $stmt->execute([
            'internal_audit_id' => $internalAuditId,
            'description'       => $description,
            'severity'          => $severity,
        ]);

        header('Location: ?page=interni-audit');
        exit;
    }
}

// --- Brisanje nalaza ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_finding') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE f FROM audit_findings f
         INNER JOIN internal_audits a ON a.id = f.internal_audit_id
         WHERE f.id = :id AND a.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=interni-audit');
    exit;
}

// --- Učitavanje audita (najnoviji prvo) ---
$auditsStmt = $pdo->prepare(
    'SELECT * FROM internal_audits WHERE organization_id = :org_id ORDER BY audit_date DESC'
);
$auditsStmt->execute(['org_id' => $organizationId]);
$allAudits = $auditsStmt->fetchAll();

// --- Nalazi za sve audite ove organizacije, grupisani po internal_audit_id ---
$findingsStmt = $pdo->prepare(
    'SELECT f.*
     FROM audit_findings f
     INNER JOIN internal_audits a ON a.id = f.internal_audit_id
     WHERE a.organization_id = :org_id
     ORDER BY f.created_at ASC'
);
$findingsStmt->execute(['org_id' => $organizationId]);

$findingsByAudit = [];
foreach ($findingsStmt->fetchAll() as $finding) {
    $findingsByAudit[$finding['internal_audit_id']][] = $finding;
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
    <button type="button" class="btn-primary" onclick="openAddAuditModal()">+ Dodaj audit</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allAudits)): ?>
    <p class="empty-state">Još uvek nema unetih audita.</p>
<?php else: ?>
    <?php foreach ($allAudits as $audit): ?>
        <?php $findings = $findingsByAudit[$audit['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/audit-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="audit-modal-overlay" onclick="closeAuditModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="audit-modal-title">Dodaj audit</span>
            <button type="button" class="modal-close" onclick="closeAuditModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="audit-modal-form">
            <input type="hidden" name="action" id="audit-modal-action" value="add_audit">
            <input type="hidden" name="id" id="audit-modal-id" value="">

            <div class="form-row">
                <label for="modal_audit_date">Datum audita</label>
                <input type="date" name="audit_date" id="modal_audit_date" required>
            </div>

            <div class="form-row">
                <label for="modal_scope">Obim audita (opciono)</label>
                <textarea name="scope" id="modal_scope" rows="2"
                    placeholder="npr. Kontrola pristupa i upravljanje pristupnim pravima za sve sisteme."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_auditor_name">Ime auditora</label>
                <input type="text" name="auditor_name" id="modal_auditor_name" required
                    placeholder="npr. Marko Marković (mora biti nezavisan od procesa koji se proverava)">
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="is_external_auditor" id="modal_is_external_auditor" value="1">
                    Spoljni auditor
                </label>
            </div>

            <div class="form-row">
                <label for="modal_report_reference">Referenca na izveštaj (opciono)</label>
                <input type="text" name="report_reference" id="modal_report_reference"
                    placeholder="npr. /dokumenti/interni-audit-2026-Q1.pdf">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromAuditModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeAuditModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="finding-modal-overlay" onclick="closeFindingModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="finding-modal-title">Dodaj nalaz</span>
            <button type="button" class="modal-close" onclick="closeFindingModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="add_finding">
            <input type="hidden" name="internal_audit_id" id="finding-modal-audit-id" value="">

            <div class="form-row">
                <label for="modal_finding_description">Opis nalaza</label>
                <textarea name="description" id="modal_finding_description" rows="2" required
                    placeholder="npr. Tri od deset proverenih naloga nemaju uključenu dvofaktorsku autentifikaciju."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_finding_severity">Ozbiljnost</label>
                <select name="severity" id="modal_finding_severity">
                    <option value="nizak">Nizak</option>
                    <option value="srednji">Srednji</option>
                    <option value="visok">Visok</option>
                </select>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromFindingModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeFindingModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddAuditModal() {
    document.getElementById('audit-modal-title').textContent = 'Dodaj audit';
    document.getElementById('audit-modal-action').value = 'add_audit';
    document.getElementById('audit-modal-id').value = '';
    document.getElementById('modal_audit_date').value = '<?= date('Y-m-d') ?>';
    document.getElementById('modal_scope').value = '';
    document.getElementById('modal_auditor_name').value = '';
    document.getElementById('modal_is_external_auditor').checked = false;
    document.getElementById('modal_report_reference').value = '';
    document.getElementById('audit-modal-overlay').classList.add('is-open');
}

function openEditAuditModal(audit) {
    document.getElementById('audit-modal-title').textContent = 'Uredi audit';
    document.getElementById('audit-modal-action').value = 'update_audit';
    document.getElementById('audit-modal-id').value = audit.id;
    document.getElementById('modal_audit_date').value = audit.audit_date;
    document.getElementById('modal_scope').value = audit.scope;
    document.getElementById('modal_auditor_name').value = audit.auditor_name;
    document.getElementById('modal_is_external_auditor').checked = audit.is_external_auditor;
    document.getElementById('modal_report_reference').value = audit.report_reference;
    document.getElementById('audit-modal-overlay').classList.add('is-open');
}

function closeAuditModal() {
    document.getElementById('audit-modal-overlay').classList.remove('is-open');
}

function openHelpFromAuditModal() {
    closeAuditModal();
    openHelpModal();
}

function openFindingModal(auditId, auditLabel) {
    document.getElementById('finding-modal-title').textContent = 'Dodaj nalaz — ' + auditLabel;
    document.getElementById('finding-modal-audit-id').value = auditId;
    document.getElementById('modal_finding_description').value = '';
    document.getElementById('modal_finding_severity').value = 'srednji';
    document.getElementById('finding-modal-overlay').classList.add('is-open');
}

function closeFindingModal() {
    document.getElementById('finding-modal-overlay').classList.remove('is-open');
}

function openHelpFromFindingModal() {
    closeFindingModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeAuditModal();
        closeFindingModal();
    }
});
</script>
