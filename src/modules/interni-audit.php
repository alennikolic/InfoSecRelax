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
 * audit_findings.corrective_action_id namerno nije u formi -
 * corrective_actions je deljena tabela i sa security_events
 * (incidenti.php), pa čeka budući korektivne-mere.php da se gradi kad
 * oba izvora postoje, ne napola.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

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
?>

<p class="module-intro">
    Klauzula 9.2 traži planirane interne audite koji proveravaju da li ISMS
    ispunjava sopstvene zahteve i zahteve standarda - auditor mora biti
    nezavisan od procesa koji proverava.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="factor-form">
    <input type="hidden" name="action" value="add_audit">

    <div class="form-row">
        <label for="audit_date">Datum audita</label>
        <input type="date" name="audit_date" id="audit_date" required value="<?= date('Y-m-d') ?>">
    </div>

    <div class="form-row">
        <label for="scope">Obim audita (opciono)</label>
        <textarea name="scope" id="scope" rows="2"
            placeholder="npr. Kontrola pristupa i upravljanje pristupnim pravima za sve sisteme."></textarea>
    </div>

    <div class="form-row">
        <label for="auditor_name">Ime auditora</label>
        <input type="text" name="auditor_name" id="auditor_name" required
            placeholder="npr. Marko Marković (mora biti nezavisan od procesa koji se proverava)">
    </div>

    <div class="form-row form-row-inline">
        <label class="checkbox-label">
            <input type="checkbox" name="is_external_auditor" value="1">
            Spoljni auditor
        </label>
    </div>

    <div class="form-row">
        <label for="report_reference">Referenca na izveštaj (opciono)</label>
        <input type="text" name="report_reference" id="report_reference"
            placeholder="npr. /dokumenti/interni-audit-2026-Q1.pdf">
    </div>

    <button type="submit" class="btn-primary">Dodaj audit</button>
</form>

<?php if (empty($allAudits)): ?>
    <p class="empty-state">Još uvek nema unetih audita.</p>
<?php else: ?>
    <?php foreach ($allAudits as $audit): ?>
        <?php $findings = $findingsByAudit[$audit['id']] ?? []; ?>
        <?php include __DIR__ . '/../includes/audit-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
