<?php
/**
 * src/modules/incidenti.php
 *
 * A.5.24-5.28: Upravljanje bezbednosnim incidentima.
 *
 * Jedna tabela (security_events) pokriva ceo ciklus - prijava (A.5.24),
 * procena (A.5.25), koren uzroka kao pouka (A.5.27) i referenca na
 * dokaze (A.5.28) - u skladu sa komentarom u db/init.sql. Odgovor na
 * incident (A.5.26) ovde nije poseban korak/tabela, nego se opisuje
 * kroz koren uzroka i status.
 *
 * Formalne korektivne mere (corrective_actions, Klauzula 10.2) su
 * namerno van obima ovog modula - ta tabela je deljena i sa
 * audit_findings (budući interni-audit.php), pa čeka da se izgradi kad
 * oba izvora postoje, da se ne gradi napola.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

$validAssessmentOutcomes = ['na_cekanju', 'lazna_uzbuna', 'potvrdjen_incident'];
$validSeverities         = ['nizak', 'srednji', 'visok'];

// --- Prijava novog događaja ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $description = trim($_POST['description'] ?? '');
    $reportedBy  = trim($_POST['reported_by'] ?? '');

    if ($description === '') {
        $errors[] = 'Opis događaja je obavezan.';
    }

    $reportedByValue = null;
    if ($reportedBy !== '') {
        $reportedByValue = (int) $reportedBy;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $reportedByValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Osoba koja prijavljuje nije pronađena.';
            $reportedByValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO security_events (organization_id, reported_by, description)
             VALUES (:org_id, :reported_by, :description)'
        );
        $stmt->execute([
            'org_id'      => $organizationId,
            'reported_by' => $reportedByValue,
            'description' => $description,
        ]);

        header('Location: ?page=incidenti');
        exit;
    }
}

// --- Ažuriranje procene (A.5.25, A.5.27, A.5.28) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_assessment') {
    $id                = (int) ($_POST['id'] ?? 0);
    $assessmentOutcome = $_POST['assessment_outcome'] ?? 'na_cekanju';
    $severity          = $_POST['severity'] ?? '';
    $rootCause         = trim($_POST['root_cause'] ?? '');
    $evidenceReference = trim($_POST['evidence_reference'] ?? '');

    if (!in_array($assessmentOutcome, $validAssessmentOutcomes, true)) {
        $errors[] = 'Izaberite ishod procene.';
    }

    $severityValue = null;
    if ($severity !== '') {
        if (!in_array($severity, $validSeverities, true)) {
            $errors[] = 'Izaberite ispravnu ozbiljnost.';
        } else {
            $severityValue = $severity;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE security_events
             SET assessment_outcome = :assessment_outcome,
                 severity = :severity,
                 root_cause = :root_cause,
                 evidence_reference = :evidence_reference
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'assessment_outcome' => $assessmentOutcome,
            'severity'           => $severityValue,
            'root_cause'         => $rootCause !== '' ? $rootCause : null,
            'evidence_reference' => $evidenceReference !== '' ? $evidenceReference : null,
            'id'                 => $id,
            'org_id'             => $organizationId,
        ]);

        header('Location: ?page=incidenti');
        exit;
    }
}

// --- Zatvaranje incidenta ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'close') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'UPDATE security_events SET closed_at = COALESCE(closed_at, NOW())
         WHERE id = :id AND organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=incidenti');
    exit;
}

// --- Ponovno otvaranje incidenta ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reopen') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'UPDATE security_events SET closed_at = NULL WHERE id = :id AND organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=incidenti');
    exit;
}

// --- Brisanje incidenta (samo za ispravku pogrešnog unosa) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM security_events WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=incidenti');
    exit;
}

// --- Aktivne osobe za dropdown prijavljivača ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Učitavanje incidenata (otvoreni prvo, pa najnoviji prvo) ---
$eventsStmt = $pdo->prepare(
    'SELECT e.*, p.full_name AS reporter_name
     FROM security_events e
     LEFT JOIN personnel p ON p.id = e.reported_by
     WHERE e.organization_id = :org_id
     ORDER BY (e.closed_at IS NOT NULL), e.reported_at DESC'
);
$eventsStmt->execute(['org_id' => $organizationId]);
$allEvents = $eventsStmt->fetchAll();
?>

<p class="module-intro">
    A.5.24-5.28 traže planiran način prijave bezbednosnih događaja, njihovu
    procenu, i učenje iz potvrđenih incidenata kroz analizu uzroka. Formalne
    korektivne mere (Klauzula 10.2) dolaze u budućem modulu.
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
        <label for="description">Opis događaja</label>
        <textarea name="description" id="description" rows="3" required
            placeholder="npr. Zaposleni je prijavio sumnjiv e-mail koji traži podatke za prijavu na sistem."></textarea>
    </div>

    <div class="form-row">
        <label for="reported_by">Prijavio (opciono, ostavi prazno za anonimnu prijavu)</label>
        <select name="reported_by" id="reported_by">
            <option value="">Anonimno</option>
            <?php foreach ($activePersonnelOptions as $option): ?>
                <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit" class="btn-primary">Prijavi događaj</button>
</form>

<?php if (empty($allEvents)): ?>
    <p class="empty-state">Još uvek nema prijavljenih događaja.</p>
<?php else: ?>
    <?php foreach ($allEvents as $event): ?>
        <?php include __DIR__ . '/../includes/incident-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
