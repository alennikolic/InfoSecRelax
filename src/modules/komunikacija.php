<?php
/**
 * src/modules/komunikacija.php
 *
 * Klauzula 7.4: Komunikacija.
 *
 * Najjednostavniji modul do sada - communications_plan nema nijedan FK,
 * samo četiri obavezna tekstualna polja koja odgovaraju na klasična
 * pitanja komunikacionog plana: šta (what_is_communicated), kome
 * (audience), kada (trigger_condition), kako (channel). Isti obrazac
 * kao ostali moduli: toolbar sa Pomoć desno, modal za dodavanje i
 * uređivanje.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'komunikacija';

$errors = [];

// --- Dodavanje nove stavke komunikacionog plana ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $whatIsCommunicated = trim($_POST['what_is_communicated'] ?? '');
    $audience           = trim($_POST['audience'] ?? '');
    $triggerCondition   = trim($_POST['trigger_condition'] ?? '');
    $channel            = trim($_POST['channel'] ?? '');

    if ($whatIsCommunicated === '') {
        $errors[] = 'Šta se komunicira je obavezno.';
    }
    if ($audience === '') {
        $errors[] = 'Kome se komunicira je obavezno.';
    }
    if ($triggerCondition === '') {
        $errors[] = 'Kada se komunicira je obavezno.';
    }
    if ($channel === '') {
        $errors[] = 'Kako se komunicira je obavezno.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO communications_plan
                (organization_id, what_is_communicated, audience, trigger_condition, channel)
             VALUES
                (:org_id, :what_is_communicated, :audience, :trigger_condition, :channel)'
        );
        $stmt->execute([
            'org_id'               => $organizationId,
            'what_is_communicated' => $whatIsCommunicated,
            'audience'             => $audience,
            'trigger_condition'    => $triggerCondition,
            'channel'              => $channel,
        ]);

        header('Location: ?page=komunikacija');
        exit;
    }
}

// --- Ažuriranje postojeće stavke ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id                 = (int) ($_POST['id'] ?? 0);
    $whatIsCommunicated = trim($_POST['what_is_communicated'] ?? '');
    $audience           = trim($_POST['audience'] ?? '');
    $triggerCondition   = trim($_POST['trigger_condition'] ?? '');
    $channel            = trim($_POST['channel'] ?? '');

    if ($whatIsCommunicated === '') {
        $errors[] = 'Šta se komunicira je obavezno.';
    }
    if ($audience === '') {
        $errors[] = 'Kome se komunicira je obavezno.';
    }
    if ($triggerCondition === '') {
        $errors[] = 'Kada se komunicira je obavezno.';
    }
    if ($channel === '') {
        $errors[] = 'Kako se komunicira je obavezno.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE communications_plan
             SET what_is_communicated = :what_is_communicated, audience = :audience,
                 trigger_condition = :trigger_condition, channel = :channel
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'what_is_communicated' => $whatIsCommunicated,
            'audience'             => $audience,
            'trigger_condition'    => $triggerCondition,
            'channel'              => $channel,
            'id'                   => $id,
            'org_id'               => $organizationId,
        ]);

        header('Location: ?page=komunikacija');
        exit;
    }
}

// --- Brisanje stavke ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM communications_plan WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=komunikacija');
    exit;
}

// --- Učitavanje komunikacionog plana ---
$stmt = $pdo->prepare(
    'SELECT * FROM communications_plan WHERE organization_id = :org_id ORDER BY created_at DESC'
);
$stmt->execute(['org_id' => $organizationId]);
$allCommunications = $stmt->fetchAll();

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
    <button type="button" class="btn-primary" onclick="openAddCommunicationModal()">+ Dodaj stavku</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if (empty($allCommunications)): ?>
    <p class="empty-state">Još uvek nema unetih stavki komunikacionog plana.</p>
<?php else: ?>
    <?php foreach ($allCommunications as $item): ?>
        <?php include __DIR__ . '/../includes/communication-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>

<div class="modal-overlay" id="communication-modal-overlay" onclick="closeCommunicationModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="communication-modal-title">Dodaj stavku</span>
            <button type="button" class="modal-close" onclick="closeCommunicationModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="communication-modal-form">
            <input type="hidden" name="action" id="communication-modal-action" value="add">
            <input type="hidden" name="id" id="communication-modal-id" value="">

            <div class="form-row">
                <label for="modal_what_is_communicated">Šta se komunicira</label>
                <textarea name="what_is_communicated" id="modal_what_is_communicated" rows="2" required
                    placeholder="npr. Obaveštenje o planiranom održavanju sistema"></textarea>
            </div>

            <div class="form-row">
                <label for="modal_audience">Kome (ciljna publika)</label>
                <input type="text" name="audience" id="modal_audience" required placeholder="npr. Svi zaposleni">
            </div>

            <div class="form-row">
                <label for="modal_trigger_condition">Kada (okidač)</label>
                <input type="text" name="trigger_condition" id="modal_trigger_condition" required
                    placeholder="npr. Najmanje 3 dana pre planiranog održavanja">
            </div>

            <div class="form-row">
                <label for="modal_channel">Kako (kanal)</label>
                <input type="text" name="channel" id="modal_channel" required placeholder="npr. E-mail celoj firmi">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromCommunicationModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeCommunicationModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddCommunicationModal() {
    document.getElementById('communication-modal-title').textContent = 'Dodaj stavku';
    document.getElementById('communication-modal-action').value = 'add';
    document.getElementById('communication-modal-id').value = '';
    document.getElementById('modal_what_is_communicated').value = '';
    document.getElementById('modal_audience').value = '';
    document.getElementById('modal_trigger_condition').value = '';
    document.getElementById('modal_channel').value = '';
    document.getElementById('communication-modal-overlay').classList.add('is-open');
}

function openEditCommunicationModal(item) {
    document.getElementById('communication-modal-title').textContent = 'Uredi stavku';
    document.getElementById('communication-modal-action').value = 'update';
    document.getElementById('communication-modal-id').value = item.id;
    document.getElementById('modal_what_is_communicated').value = item.what_is_communicated;
    document.getElementById('modal_audience').value = item.audience;
    document.getElementById('modal_trigger_condition').value = item.trigger_condition;
    document.getElementById('modal_channel').value = item.channel;
    document.getElementById('communication-modal-overlay').classList.add('is-open');
}

function closeCommunicationModal() {
    document.getElementById('communication-modal-overlay').classList.remove('is-open');
}

function openHelpFromCommunicationModal() {
    closeCommunicationModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeCommunicationModal();
    }
});
</script>
