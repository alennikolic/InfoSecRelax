<?php
/**
 * src/modules/zainteresovane-strane.php
 *
 * Klauzula 4.2: Razumevanje potreba i očekivanja zainteresovanih strana.
 *
 * Isti obrazac kao kontekst.php: modal za dodavanje/uređivanje strane
 * (name + party_type), toolbar sa Pomoć desno, deljeni modal pomoći
 * (view-only, uređivanje centralno na pomoc-uredjivanje.php).
 *
 * Zahtevi (interested_party_requirements) ostaju dodaj/obriši - nemaju
 * svoje uređivanje, to je ugnježden nivo ispod glavnog CRUD-a i van
 * obima ovog prolaza. Tabela zahteva nema svoju organization_id
 * kolonu, pa se multi-tenant provera radi preko JOIN-a na
 * interested_parties u svakom upitu koji dira zahteve.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'zainteresovane-strane';

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

// --- Ažuriranje postojeće zainteresovane strane ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_party') {
    $id        = (int) ($_POST['id'] ?? 0);
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
            'UPDATE interested_parties SET name = :name, party_type = :party_type
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'name'       => $name,
            'party_type' => $partyType,
            'id'         => $id,
            'org_id'     => $organizationId,
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
    <button type="button" class="btn-primary" onclick="openAddPartyModal()">+ Dodaj zainteresovanu stranu</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

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

<div class="modal-overlay" id="party-modal-overlay" onclick="closePartyModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="party-modal-title">Dodaj zainteresovanu stranu</span>
            <button type="button" class="modal-close" onclick="closePartyModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="party-modal-form">
            <input type="hidden" name="action" id="party-modal-action" value="add_party">
            <input type="hidden" name="id" id="party-modal-id" value="">

            <div class="form-row">
                <label for="modal_party_type">Vrsta strane</label>
                <select name="party_type" id="modal_party_type" required>
                    <option value="">Izaberite...</option>
                    <option value="interna">Interna</option>
                    <option value="eksterna">Eksterna</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_party_name">Naziv</label>
                <input type="text" name="name" id="modal_party_name" required
                    placeholder="npr. Klijenti, Zaposleni, Nadzorni organ, Dobavljač hostinga">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromPartyModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closePartyModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="requirement-modal-overlay" onclick="closeRequirementModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="requirement-modal-title">Dodaj zahtev</span>
            <button type="button" class="modal-close" onclick="closeRequirementModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="requirement-modal-form">
            <input type="hidden" name="action" value="add_requirement">
            <input type="hidden" name="interested_party_id" id="requirement-modal-party-id" value="">

            <div class="form-row">
                <label for="modal_requirement">Zahtev</label>
                <textarea name="requirement" id="modal_requirement" rows="3" required
                    placeholder="npr. Klijenti očekuju da njihovi lični podaci budu zaštićeni od neovlašćenog pristupa."></textarea>
            </div>

            <div class="form-row form-row-inline">
                <label class="checkbox-label">
                    <input type="checkbox" name="addressed_by_isms" id="modal_addressed_by_isms" value="1" checked>
                    Pokriveno kroz ISMS
                </label>
            </div>

            <div class="form-row">
                <label for="modal_requirement_notes">Napomena (opciono)</label>
                <input type="text" name="notes" id="modal_requirement_notes">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromRequirementModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeRequirementModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddPartyModal() {
    document.getElementById('party-modal-title').textContent = 'Dodaj zainteresovanu stranu';
    document.getElementById('party-modal-action').value = 'add_party';
    document.getElementById('party-modal-id').value = '';
    document.getElementById('modal_party_type').value = '';
    document.getElementById('modal_party_name').value = '';
    document.getElementById('party-modal-overlay').classList.add('is-open');
}

function openEditPartyModal(party) {
    document.getElementById('party-modal-title').textContent = 'Uredi zainteresovanu stranu';
    document.getElementById('party-modal-action').value = 'update_party';
    document.getElementById('party-modal-id').value = party.id;
    document.getElementById('modal_party_type').value = party.party_type;
    document.getElementById('modal_party_name').value = party.name;
    document.getElementById('party-modal-overlay').classList.add('is-open');
}

function closePartyModal() {
    document.getElementById('party-modal-overlay').classList.remove('is-open');
}

function openHelpFromPartyModal() {
    closePartyModal();
    openHelpModal();
}

function openAddRequirementModal(partyId, partyName) {
    document.getElementById('requirement-modal-title').textContent = 'Dodaj zahtev — ' + partyName;
    document.getElementById('requirement-modal-party-id').value = partyId;
    document.getElementById('modal_requirement').value = '';
    document.getElementById('modal_addressed_by_isms').checked = true;
    document.getElementById('modal_requirement_notes').value = '';
    document.getElementById('requirement-modal-overlay').classList.add('is-open');
}

function closeRequirementModal() {
    document.getElementById('requirement-modal-overlay').classList.remove('is-open');
}

function openHelpFromRequirementModal() {
    closeRequirementModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closePartyModal();
        closeRequirementModal();
    }
});
</script>
