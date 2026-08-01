<?php
/**
 * src/modules/komunikacija.php
 *
 * Klauzula 7.4: Komunikacija.
 *
 * Najjednostavniji modul do sada - communications_plan nema nijedan FK,
 * samo četiri obavezna tekstualna polja koja odgovaraju na klasična
 * pitanja komunikacionog plana: šta (what_is_communicated), kome
 * (audience), kada (trigger_condition), kako (channel). Prost CRUD,
 * dodaj/prikaži/obriši, bez podele po tipu jer nema ENUM kolonu.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

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
?>

<p class="module-intro">
    Klauzula 7.4 traži da se odredi šta treba komunicirati, kome, kada i kako -
    unutar firme i prema spoljnim stranama (npr. klijentima, regulatoru).
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
        <label for="what_is_communicated">Šta se komunicira</label>
        <textarea name="what_is_communicated" id="what_is_communicated" rows="2" required
            placeholder="npr. Obaveštenje o planiranom održavanju sistema"></textarea>
    </div>

    <div class="form-row">
        <label for="audience">Kome (ciljna publika)</label>
        <input type="text" name="audience" id="audience" required placeholder="npr. Svi zaposleni">
    </div>

    <div class="form-row">
        <label for="trigger_condition">Kada (okidač)</label>
        <input type="text" name="trigger_condition" id="trigger_condition" required
            placeholder="npr. Najmanje 3 dana pre planiranog održavanja">
    </div>

    <div class="form-row">
        <label for="channel">Kako (kanal)</label>
        <input type="text" name="channel" id="channel" required placeholder="npr. E-mail celoj firmi">
    </div>

    <button type="submit" class="btn-primary">Dodaj stavku</button>
</form>

<?php if (empty($allCommunications)): ?>
    <p class="empty-state">Još uvek nema unetih stavki komunikacionog plana.</p>
<?php else: ?>
    <?php foreach ($allCommunications as $item): ?>
        <?php include __DIR__ . '/../includes/communication-card.php'; ?>
    <?php endforeach; ?>
<?php endif; ?>
