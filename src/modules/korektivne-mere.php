<?php
/**
 * src/modules/korektivne-mere.php
 *
 * Klauzula 10.2: Neusaglašenosti i korektivne mere.
 *
 * Poslednji deo slagalice - corrective_actions je deljena tabela sa
 * source_security_event_id (incidenti.php) i source_audit_finding_id
 * (interni-audit.php), namerno ostavljena po strani u oba ta modula
 * dok ne postoji mesto koje objedinjuje oba izvora. Ovde postoji.
 *
 * Kad se korektivna mera doda sa izvorom iz bezbednosnog incidenta,
 * automatski se ažurira i security_events.corrective_action_id na tom
 * incidentu (kružna veza iz šeme - incident zna svoju "glavnu"
 * korektivnu meru, mera zna svoj izvor).
 *
 * Isti obrazac kao ostali moduli: toolbar sa Pomoć desno, modal za
 * dodavanje/uređivanje. "Uredi" NAMERNO ne dira izvor
 * (source_security_event_id/source_audit_finding_id) ni status - izvor
 * se postavlja samo pri dodavanju (menjanje bi zahtevalo usklađivanje
 * kružne veze sa security_events, nepotrebna složenost za redak
 * slučaj), a status ostaje inline forma kao kod rizika i ciljeva.
 * Prelazak statusa na "provereno_efikasno" i dalje automatski
 * postavlja effectiveness_confirmed_at na danas (samo ako već nije
 * ručno unet).
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'korektivne-mere';

$errors = [];

$validStatuses = ['otvoreno', 'sprovedeno', 'provereno_efikasno', 'ponovo_otvoreno'];

// --- Dodavanje korektivne mere ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $description           = trim($_POST['description'] ?? '');
    $sourceSecurityEventId = trim($_POST['source_security_event_id'] ?? '');
    $sourceAuditFindingId  = trim($_POST['source_audit_finding_id'] ?? '');
    $rootCauseGeneralized  = trim($_POST['root_cause_generalized'] ?? '');
    $ownerId               = trim($_POST['owner_id'] ?? '');
    $dueDate               = trim($_POST['due_date'] ?? '');

    if ($description === '') {
        $errors[] = 'Opis korektivne mere je obavezan.';
    }

    $sourceSecurityEventIdValue = null;
    if ($sourceSecurityEventId !== '') {
        $sourceSecurityEventIdValue = (int) $sourceSecurityEventId;
        $eventCheck = $pdo->prepare('SELECT id FROM security_events WHERE id = :id AND organization_id = :org_id');
        $eventCheck->execute(['id' => $sourceSecurityEventIdValue, 'org_id' => $organizationId]);

        if ($eventCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani incident nije pronađen.';
            $sourceSecurityEventIdValue = null;
        }
    }

    $sourceAuditFindingIdValue = null;
    if ($sourceAuditFindingId !== '') {
        $sourceAuditFindingIdValue = (int) $sourceAuditFindingId;
        $findingCheck = $pdo->prepare(
            'SELECT f.id FROM audit_findings f
             INNER JOIN internal_audits a ON a.id = f.internal_audit_id
             WHERE f.id = :id AND a.organization_id = :org_id'
        );
        $findingCheck->execute(['id' => $sourceAuditFindingIdValue, 'org_id' => $organizationId]);

        if ($findingCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani nalaz nije pronađen.';
            $sourceAuditFindingIdValue = null;
        }
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
            'INSERT INTO corrective_actions
                (organization_id, source_security_event_id, source_audit_finding_id, description,
                 root_cause_generalized, owner_id, due_date)
             VALUES
                (:org_id, :source_security_event_id, :source_audit_finding_id, :description,
                 :root_cause_generalized, :owner_id, :due_date)'
        );
        $stmt->execute([
            'org_id'                   => $organizationId,
            'source_security_event_id' => $sourceSecurityEventIdValue,
            'source_audit_finding_id'  => $sourceAuditFindingIdValue,
            'description'              => $description,
            'root_cause_generalized'   => $rootCauseGeneralized !== '' ? $rootCauseGeneralized : null,
            'owner_id'                 => $ownerIdValue,
            'due_date'                 => $dueDate !== '' ? $dueDate : null,
        ]);

        $newCorrectiveActionId = (int) $pdo->lastInsertId();

        // Kružna veza: incident dobija referencu na svoju "glavnu" korektivnu meru.
        if ($sourceSecurityEventIdValue !== null) {
            $pdo->prepare(
                'UPDATE security_events SET corrective_action_id = :corrective_action_id
                 WHERE id = :id AND organization_id = :org_id'
            )->execute([
                'corrective_action_id' => $newCorrectiveActionId,
                'id'                   => $sourceSecurityEventIdValue,
                'org_id'               => $organizationId,
            ]);
        }

        header('Location: ?page=korektivne-mere');
        exit;
    }
}

// --- Ažuriranje postojeće mere (NE menja izvor ni status) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id                   = (int) ($_POST['id'] ?? 0);
    $description          = trim($_POST['description'] ?? '');
    $rootCauseGeneralized = trim($_POST['root_cause_generalized'] ?? '');
    $ownerId              = trim($_POST['owner_id'] ?? '');
    $dueDate              = trim($_POST['due_date'] ?? '');

    if ($description === '') {
        $errors[] = 'Opis korektivne mere je obavezan.';
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
            'UPDATE corrective_actions
             SET description = :description, root_cause_generalized = :root_cause_generalized,
                 owner_id = :owner_id, due_date = :due_date
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'description'            => $description,
            'root_cause_generalized' => $rootCauseGeneralized !== '' ? $rootCauseGeneralized : null,
            'owner_id'               => $ownerIdValue,
            'due_date'               => $dueDate !== '' ? $dueDate : null,
            'id'                     => $id,
            'org_id'                 => $organizationId,
        ]);

        header('Location: ?page=korektivne-mere');
        exit;
    }
}

// --- Promena statusa ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $id        = (int) ($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';

    if (in_array($newStatus, $validStatuses, true)) {
        if ($newStatus === 'provereno_efikasno') {
            $stmt = $pdo->prepare(
                'UPDATE corrective_actions
                 SET status = :status, effectiveness_confirmed_at = COALESCE(effectiveness_confirmed_at, CURDATE())
                 WHERE id = :id AND organization_id = :org_id'
            );
        } else {
            $stmt = $pdo->prepare(
                'UPDATE corrective_actions SET status = :status WHERE id = :id AND organization_id = :org_id'
            );
        }
        $stmt->execute(['status' => $newStatus, 'id' => $id, 'org_id' => $organizationId]);
    }

    header('Location: ?page=korektivne-mere');
    exit;
}

// --- Brisanje korektivne mere ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM corrective_actions WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=korektivne-mere');
    exit;
}

// --- Aktivne osobe za dropdown nosioca ---
$personnelStmt = $pdo->prepare(
    'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
);
$personnelStmt->execute(['org_id' => $organizationId]);
$activePersonnelOptions = $personnelStmt->fetchAll();

// --- Bezbednosni incidenti za dropdown izvora ---
$eventsStmt = $pdo->prepare(
    'SELECT id, reported_at, description FROM security_events WHERE organization_id = :org_id ORDER BY reported_at DESC'
);
$eventsStmt->execute(['org_id' => $organizationId]);
$eventOptions = $eventsStmt->fetchAll();

// --- Nalazi internog audita za dropdown izvora ---
$findingsStmt = $pdo->prepare(
    'SELECT f.id, f.description, a.audit_date
     FROM audit_findings f
     INNER JOIN internal_audits a ON a.id = f.internal_audit_id
     WHERE a.organization_id = :org_id
     ORDER BY a.audit_date DESC'
);
$findingsStmt->execute(['org_id' => $organizationId]);
$findingOptions = $findingsStmt->fetchAll();

// --- Učitavanje korektivnih mera (sa podacima o izvoru) ---
$actionsStmt = $pdo->prepare(
    'SELECT ca.*, p.full_name AS owner_name,
            se.description AS source_event_description, se.reported_at AS source_event_reported_at,
            af.description AS source_finding_description, ia.audit_date AS source_finding_audit_date
     FROM corrective_actions ca
     LEFT JOIN personnel p ON p.id = ca.owner_id
     LEFT JOIN security_events se ON se.id = ca.source_security_event_id
     LEFT JOIN audit_findings af ON af.id = ca.source_audit_finding_id
     LEFT JOIN internal_audits ia ON ia.id = af.internal_audit_id
     WHERE ca.organization_id = :org_id
     ORDER BY ca.status, ca.created_at DESC'
);
$actionsStmt->execute(['org_id' => $organizationId]);
$allActions = $actionsStmt->fetchAll();

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
    <button type="button" class="btn-primary" onclick="openAddActionModal()">+ Dodaj korektivnu meru</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allActions)): ?>
    <p class="empty-state">Još uvek nema unetih korektivnih mera.</p>
<?php else: ?>
    <?php foreach ($allActions as $correctiveAction): ?>
        <?php include __DIR__ . '/../includes/corrective-action-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="action-modal-overlay" onclick="closeActionModal()">
    <div class="modal-box modal-box-wide" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="action-modal-title">Dodaj korektivnu meru</span>
            <button type="button" class="modal-close" onclick="closeActionModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="action-modal-form">
            <input type="hidden" name="action" id="action-modal-action" value="add">
            <input type="hidden" name="id" id="action-modal-id" value="">

            <div class="form-row">
                <label for="modal_description">Opis korektivne mere</label>
                <textarea name="description" id="modal_description" rows="2" required
                    placeholder="npr. Uvesti obaveznu dvofaktorsku autentifikaciju za sve administratorske naloge."></textarea>
            </div>

            <div class="form-row" id="modal-source-row">
                <label for="modal_source_security_event_id">Izvor: bezbednosni incident (opciono)</label>
                <select name="source_security_event_id" id="modal_source_security_event_id">
                    <option value="">Nije povezano</option>
                    <?php foreach ($eventOptions as $option): ?>
                        <?php
                            $eventLabel = substr((string) $option['reported_at'], 0, 10) . ' - ' . substr($option['description'], 0, 50);
                            if (strlen($option['description']) > 50) {
                                $eventLabel .= '...';
                            }
                        ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($eventLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($eventOptions)): ?>
                    <p class="item-meta">Nema unetih incidenata - opciono ih dodaj na stranici "Upravljanje incidentima".</p>
                <?php endif; ?>
            </div>

            <div class="form-row" id="modal-source-finding-row">
                <label for="modal_source_audit_finding_id">Izvor: nalaz internog audita (opciono)</label>
                <select name="source_audit_finding_id" id="modal_source_audit_finding_id">
                    <option value="">Nije povezano</option>
                    <?php foreach ($findingOptions as $option): ?>
                        <?php
                            $findingLabel = $option['audit_date'] . ' - ' . substr($option['description'], 0, 50);
                            if (strlen($option['description']) > 50) {
                                $findingLabel .= '...';
                            }
                        ?>
                        <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($findingLabel) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($findingOptions)): ?>
                    <p class="item-meta">Nema unetih nalaza - opciono ih dodaj na stranici "Interni audit".</p>
                <?php endif; ?>
            </div>

            <div class="form-row">
                <label for="modal_root_cause_generalized">Da li se slično može desiti i drugde (opciono)</label>
                <textarea name="root_cause_generalized" id="modal_root_cause_generalized" rows="2"
                    placeholder="npr. Isti propust je moguć i na drugim sistemima koji koriste istu vrstu naloga."></textarea>
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
                <label for="modal_due_date">Rok (opciono)</label>
                <input type="date" name="due_date" id="modal_due_date">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromActionModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeActionModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddActionModal() {
    document.getElementById('action-modal-title').textContent = 'Dodaj korektivnu meru';
    document.getElementById('action-modal-action').value = 'add';
    document.getElementById('action-modal-id').value = '';
    document.getElementById('modal_description').value = '';
    document.getElementById('modal_source_security_event_id').value = '';
    document.getElementById('modal_source_audit_finding_id').value = '';
    document.getElementById('modal_root_cause_generalized').value = '';
    document.getElementById('modal_owner_id').value = '';
    document.getElementById('modal_due_date').value = '';
    document.getElementById('modal-source-row').classList.remove('is-hidden');
    document.getElementById('modal-source-finding-row').classList.remove('is-hidden');
    document.getElementById('action-modal-overlay').classList.add('is-open');
}

function openEditActionModal(correctiveAction) {
    document.getElementById('action-modal-title').textContent = 'Uredi korektivnu meru';
    document.getElementById('action-modal-action').value = 'update';
    document.getElementById('action-modal-id').value = correctiveAction.id;
    document.getElementById('modal_description').value = correctiveAction.description;
    document.getElementById('modal_root_cause_generalized').value = correctiveAction.root_cause_generalized;
    document.getElementById('modal_owner_id').value = correctiveAction.owner_id;
    document.getElementById('modal_due_date').value = correctiveAction.due_date;
    // Izvor se ne menja kroz uređivanje - sakriven da se ne stvori utisak
    // da menja polje koje ova akcija ne dira (videti napomenu u docbloku).
    document.getElementById('modal-source-row').classList.add('is-hidden');
    document.getElementById('modal-source-finding-row').classList.add('is-hidden');
    document.getElementById('action-modal-overlay').classList.add('is-open');
}

function closeActionModal() {
    document.getElementById('action-modal-overlay').classList.remove('is-open');
}

function openHelpFromActionModal() {
    closeActionModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeActionModal();
    }
});
</script>
