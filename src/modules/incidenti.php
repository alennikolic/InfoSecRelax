<?php
/**
 * src/modules/incidenti.php
 *
 * A.5.24-5.28: Upravljanje bezbednosnim incidentima.
 *
 * Jedna tabela (security_events) pokriva ceo ciklus - prijava (A.5.24),
 * procena (A.5.25), koren uzroka kao pouka (A.5.27) i referenca na
 * dokaze (A.5.28). Odgovor na incident (A.5.26) ovde nije poseban
 * korak/tabela, nego se opisuje kroz koren uzroka i status.
 *
 * Prijava je namerno minimalna (opis + ko prijavljuje, opciono) - modal
 * za "+ Prijavi događaj" i "Uredi" menjaju samo ta dva polja. Procena
 * (ishod, ozbiljnost, koren uzroka, dokazi) je poseban modal ("Pokreni
 * procenu", otvoren dugmetom u kartici) - rezultati ostaju prikazani u
 * telu kartice bez obzira da li je modal otvoren, menja se samo to gde
 * živi FORMA za unos.
 *
 * Formalne korektivne mere (corrective_actions, Klauzula 10.2) su
 * namerno van obima ovog modula - ta tabela je deljena i sa
 * audit_findings (budući interni-audit.php), pa čeka da se izgradi kad
 * oba izvora postoje, da se ne gradi napola.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'incidenti';

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

// --- Ažuriranje prijave (samo opis i ko prijavljuje - NE dira procenu) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_report') {
    $id          = (int) ($_POST['id'] ?? 0);
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
            'UPDATE security_events SET description = :description, reported_by = :reported_by
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'description' => $description,
            'reported_by' => $reportedByValue,
            'id'          => $id,
            'org_id'      => $organizationId,
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
    <button type="button" class="btn-primary" onclick="openAddEventModal()">+ Prijavi događaj</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allEvents)): ?>
    <p class="empty-state">Još uvek nema prijavljenih događaja.</p>
<?php else: ?>
    <?php foreach ($allEvents as $event): ?>
        <?php include __DIR__ . '/../includes/incident-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="event-modal-overlay" onclick="closeEventModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="event-modal-title">Prijavi događaj</span>
            <button type="button" class="modal-close" onclick="closeEventModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="event-modal-form">
            <input type="hidden" name="action" id="event-modal-action" value="add">
            <input type="hidden" name="id" id="event-modal-id" value="">

            <div class="form-row">
                <label for="modal_description">Opis događaja</label>
                <textarea name="description" id="modal_description" rows="3" required
                    placeholder="npr. Zaposleni je prijavio sumnjiv e-mail koji traži podatke za prijavu na sistem."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_reported_by">Prijavio (opciono, ostavi prazno za anonimnu prijavu)</label>
                <select name="reported_by" id="modal_reported_by">
                    <option value="">Anonimno</option>
                    <?php foreach ($activePersonnelOptions as $option): ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromEventModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeEventModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="assessment-modal-overlay" onclick="closeAssessmentModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title">Ažuriraj procenu</span>
            <button type="button" class="modal-close" onclick="closeAssessmentModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="update_assessment">
            <input type="hidden" name="id" id="assessment-modal-id" value="">

            <div class="form-row">
                <label for="modal_assessment_outcome">Ishod procene</label>
                <select name="assessment_outcome" id="modal_assessment_outcome">
                    <option value="na_cekanju">Na čekanju</option>
                    <option value="lazna_uzbuna">Lažna uzbuna</option>
                    <option value="potvrdjen_incident">Potvrđen incident</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_severity">Ozbiljnost</label>
                <select name="severity" id="modal_severity">
                    <option value="">Nije određeno</option>
                    <option value="nizak">Nizak</option>
                    <option value="srednji">Srednji</option>
                    <option value="visok">Visok</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_root_cause">Koren uzroka (opciono)</label>
                <textarea name="root_cause" id="modal_root_cause" rows="2"
                    placeholder="npr. Zaposleni nisu prošli obuku o prepoznavanju phishing e-mailova."></textarea>
            </div>

            <div class="form-row">
                <label for="modal_evidence_reference">Referenca na dokaze (opciono)</label>
                <input type="text" name="evidence_reference" id="modal_evidence_reference"
                    placeholder="npr. Snimak ekrana sačuvan u /dokazi/incident-42.png">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromAssessmentModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeAssessmentModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddEventModal() {
    document.getElementById('event-modal-title').textContent = 'Prijavi događaj';
    document.getElementById('event-modal-action').value = 'add';
    document.getElementById('event-modal-id').value = '';
    document.getElementById('modal_description').value = '';
    document.getElementById('modal_reported_by').value = '';
    document.getElementById('event-modal-overlay').classList.add('is-open');
}

function openEditEventModal(event_) {
    document.getElementById('event-modal-title').textContent = 'Uredi prijavu';
    document.getElementById('event-modal-action').value = 'update_report';
    document.getElementById('event-modal-id').value = event_.id;
    document.getElementById('modal_description').value = event_.description;
    document.getElementById('modal_reported_by').value = event_.reported_by;
    document.getElementById('event-modal-overlay').classList.add('is-open');
}

function closeEventModal() {
    document.getElementById('event-modal-overlay').classList.remove('is-open');
}

function openHelpFromEventModal() {
    closeEventModal();
    openHelpModal();
}

function openAssessmentModal(event_) {
    document.getElementById('assessment-modal-id').value = event_.id;
    document.getElementById('modal_assessment_outcome').value = event_.assessment_outcome;
    document.getElementById('modal_severity').value = event_.severity;
    document.getElementById('modal_root_cause').value = event_.root_cause;
    document.getElementById('modal_evidence_reference').value = event_.evidence_reference;
    document.getElementById('assessment-modal-overlay').classList.add('is-open');
}

function closeAssessmentModal() {
    document.getElementById('assessment-modal-overlay').classList.remove('is-open');
}

function openHelpFromAssessmentModal() {
    closeAssessmentModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeEventModal();
        closeAssessmentModal();
    }
});
</script>
