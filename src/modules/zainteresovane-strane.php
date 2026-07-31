<?php
/**
 * src/modules/zainteresovane-strane.php
 *
 * Klauzula 4.2: Razumevanje potreba i očekivanja zainteresovanih strana.
 *
 * Isti obrazac kao modules/kontekst.php (forma + prikaz + brisanje, PRG
 * na svakom upisu), proširen na odnos roditelj-dete: jedna
 * zainteresovana strana (interested_parties) ima nula ili više zahteva
 * (interested_party_requirements). Tabela zahteva nema svoju
 * organization_id kolonu, pa se multi-tenant provera radi preko JOIN-a
 * na interested_parties u svakom upitu koji dira zahteve.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

// --- Dodavanje nove zainteresovane strane ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_party') {
    $name      = trim($_POST['name'] ?? '');
    $partyType = $_POST['party_type'] ?? '';

    if ($name === '') {
        $errors[] = 'Naziv zainteresovane strane je obavezan.';
    }
    if (!in_array($partyType, ['interna', 'eksterna'], true)) {
        $errors[] = 'Izaberite da li je strana interna ili eksterna.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO interested_parties (organization_id, name, party_type)
             VALUES (:org_id, :name, :party_type)'
        );
        $stmt->execute([
            'org_id'     => $organizationId,
            'name'       => $name,
            'party_type' => $partyType,
        ]);

        header('Location: ?page=zainteresovane-strane');
        exit;
    }
}

// --- Dodavanje zahteva postojećoj strani ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_requirement') {
    $interestedPartyId = (int) ($_POST['interested_party_id'] ?? 0);
    $requirement        = trim($_POST['requirement'] ?? '');
    $addressedByIsms    = isset($_POST['addressed_by_isms']) ? 1 : 0;
    $notes              = trim($_POST['notes'] ?? '');

    // Strana mora da postoji i da pripada ovoj organizaciji - sprečava
    // dodavanje zahteva pod tuđu zainteresovanu stranu preko izmenjenog POST-a.
    $partyCheck = $pdo->prepare(
        'SELECT id FROM interested_parties WHERE id = :id AND organization_id = :org_id'
    );
    $partyCheck->execute(['id' => $interestedPartyId, 'org_id' => $organizationId]);

    if ($partyCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznata zainteresovana strana.';
    }
    if ($requirement === '') {
        $errors[] = 'Opis zahteva je obavezan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO interested_party_requirements
                (interested_party_id, requirement, addressed_by_isms, notes)
             VALUES (:party_id, :requirement, :addressed, :notes)'
        );
        $stmt->execute([
            'party_id'    => $interestedPartyId,
            'requirement' => $requirement,
            'addressed'   => $addressedByIsms,
            'notes'       => $notes !== '' ? $notes : null,
        ]);

        header('Location: ?page=zainteresovane-strane');
        exit;
    }
}

// --- Brisanje zainteresovane strane (zahtevi se brišu kaskadno preko FK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_party') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM interested_parties WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=zainteresovane-strane');
    exit;
}

// --- Brisanje pojedinačnog zahteva ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_requirement') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE r FROM interested_party_requirements r
         INNER JOIN interested_parties p ON p.id = r.interested_party_id
         WHERE r.id = :id AND p.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=zainteresovane-strane');
    exit;
}

// --- Učitavanje strana i njihovih zahteva ---
$partiesStmt = $pdo->prepare(
    'SELECT * FROM interested_parties WHERE organization_id = :org_id ORDER BY name'
);
$partiesStmt->execute(['org_id' => $organizationId]);
$allParties = $partiesStmt->fetchAll();

$requirementsStmt = $pdo->prepare(
    'SELECT r.* FROM interested_party_requirements r
     INNER JOIN interested_parties p ON p.id = r.interested_party_id
     WHERE p.organization_id = :org_id
     ORDER BY r.created_at ASC'
);
$requirementsStmt->execute(['org_id' => $organizationId]);

$requirementsByParty = [];
foreach ($requirementsStmt->fetchAll() as $requirement) {
    $requirementsByParty[$requirement['interested_party_id']][] = $requirement;
}

$internalParties = array_filter($allParties, fn(array $p): bool => $p['party_type'] === 'interna');
$externalParties = array_filter($allParties, fn(array $p): bool => $p['party_type'] === 'eksterna');
?>

<p class="module-intro">
    Klauzula 4.2 traži da identifikujete zainteresovane strane relevantne za sistem
    bezbednosti informacija, njihove zahteve, i da odredite koji od tih zahteva
    će biti pokriveni kroz sam ISMS.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" class="factor-form">
    <input type="hidden" name="action" value="add_party">

    <div class="form-row">
        <label for="party_type">Vrsta strane</label>
        <select name="party_type" id="party_type" required>
            <option value="">Izaberite...</option>
            <option value="interna">Interna</option>
            <option value="eksterna">Eksterna</option>
        </select>
    </div>

    <div class="form-row">
        <label for="name">Naziv</label>
        <input type="text" name="name" id="name" required
            placeholder="npr. Klijenti, Zaposleni, Nadzorni organ, Dobavljač hostinga">
    </div>

    <button type="submit" class="btn-primary">Dodaj zainteresovanu stranu</button>
</form>

<div class="factor-columns">
    <div class="factor-column">
        <h3>Interne strane (<?= count($internalParties) ?>)</h3>
        <?php if (empty($internalParties)): ?>
            <p class="empty-state">Još uvek nema unetih internih strana.</p>
        <?php else: ?>
            <?php foreach ($internalParties as $party): ?>
                <?php $requirements = $requirementsByParty[$party['id']] ?? []; ?>
                <?php include __DIR__ . '/../includes/party-card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="factor-column">
        <h3>Eksterne strane (<?= count($externalParties) ?>)</h3>
        <?php if (empty($externalParties)): ?>
            <p class="empty-state">Još uvek nema unetih eksternih strana.</p>
        <?php else: ?>
            <?php foreach ($externalParties as $party): ?>
                <?php $requirements = $requirementsByParty[$party['id']] ?? []; ?>
                <?php include __DIR__ . '/../includes/party-card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
