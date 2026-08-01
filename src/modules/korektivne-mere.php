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
 * Flat CRUD (bez ugnježdene dece), sa promenom statusa preko inline
 * forme kao kod rizika i ciljeva. Prelazak statusa na
 * "provereno_efikasno" automatski postavlja effectiveness_confirmed_at
 * na danas (samo ako već nije ručno unet).
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

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
?>

<p class="module-intro">
    Klauzula 10.2 traži da se na neusaglašenosti reaguje korektivnim merama -
    ne samo da se otkloni posledica, nego da se proveri da li se isto može
    desiti i negde drugde, i da li je mera zaista bila efikasna.
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
        <label for="description">Opis korektivne mere</label>
        <textarea name="description" id="description" rows="2" required
            placeholder="npr. Uvesti obaveznu dvofaktorsku autentifikaciju za sve administratorske naloge."></textarea>
    </div>

    <div class="form-row">
        <label for="source_security_event_id">Izvor: bezbednosni incident (opciono)</label>
        <select name="source_security_event_id" id="source_security_event_id">
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

    <div class="form-row">
        <label for="source_audit_finding_id">Izvor: nalaz internog audita (opciono)</label>
        <select name="source_audit_finding_id" id="source_audit_finding_id">
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
        <label for="root_cause_generalized">Da li se slično može desiti i drugde (opciono)</label>
        <textarea name="root_cause_generalized" id="root_cause_generalized" rows="2"
            placeholder="npr. Isti propust je moguć i na drugim sistemima koji koriste istu vrstu naloga."></textarea>
    </div>

    <div class="form-row">
        <label for="owner_id">Nosilac (opciono)</label>
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
        <label for="due_date">Rok (opciono)</label>
        <input type="date" name="due_date" id="due_date">
    </div>

    <button type="submit" class="btn-primary">Dodaj korektivnu meru</button>
</form>

<?php if (empty($allActions)): ?>
    <p class="empty-state">Još uvek nema unetih korektivnih mera.</p>
<?php else: ?>
    <?php foreach ($allActions as $correctiveAction): ?>
        <?php include __DIR__ . '/../includes/corrective-action-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
