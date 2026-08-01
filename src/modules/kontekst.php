<?php
/**
 * src/modules/kontekst.php - Klauzula 4.1: Kontekst organizacije.
 *
 * Prvi potpuno funkcionalan modul u aplikaciji, i prvi sa pravim
 * uređivanjem (modal, action=update). Modal pomoći ovde je samo
 * prikaz - uređivanje teksta se radi centralno na
 * modules/pomoc-uredjivanje.php, ne po svakoj stranici posebno.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'kontekst';

$errors = [];

// --- Dodavanje novog faktora ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add') {
    $factorType  = $_POST['factor_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category'] ?? '');

    if (!in_array($factorType, ['spoljni', 'unutrasnji'], true)) {
        $errors[] = 'Izaberite da li je faktor spoljni ili unutrašnji.';
    }
    if ($description === '') {
        $errors[] = 'Opis faktora je obavezan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO context_factors (organization_id, factor_type, description, category)
             VALUES (:org_id, :factor_type, :description, :category)'
        );
        $stmt->execute([
            'org_id'      => $organizationId,
            'factor_type' => $factorType,
            'description' => $description,
            'category'    => $category !== '' ? $category : null,
        ]);

        header('Location: ?page=kontekst');
        exit;
    }
}

// --- Ažuriranje postojećeg faktora ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $id          = (int) ($_POST['id'] ?? 0);
    $factorType  = $_POST['factor_type'] ?? '';
    $description = trim($_POST['description'] ?? '');
    $category    = trim($_POST['category'] ?? '');

    if (!in_array($factorType, ['spoljni', 'unutrasnji'], true)) {
        $errors[] = 'Izaberite da li je faktor spoljni ili unutrašnji.';
    }
    if ($description === '') {
        $errors[] = 'Opis faktora je obavezan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE context_factors
             SET factor_type = :factor_type, description = :description, category = :category
             WHERE id = :id AND organization_id = :org_id'
        );
        $stmt->execute([
            'factor_type' => $factorType,
            'description' => $description,
            'category'    => $category !== '' ? $category : null,
            'id'          => $id,
            'org_id'      => $organizationId,
        ]);

        header('Location: ?page=kontekst');
        exit;
    }
}

// --- Brisanje faktora ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare('DELETE FROM context_factors WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=kontekst');
    exit;
}

// --- Učitavanje postojećih faktora ---
$stmt = $pdo->prepare(
    'SELECT * FROM context_factors WHERE organization_id = :org_id ORDER BY created_at DESC'
);
$stmt->execute(['org_id' => $organizationId]);
$allFactors = $stmt->fetchAll();

$externalFactors = array_filter($allFactors, fn(array $f): bool => $f['factor_type'] === 'spoljni');
$internalFactors = array_filter($allFactors, fn(array $f): bool => $f['factor_type'] === 'unutrasnji');

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
    <button type="button" class="btn-primary" onclick="openAddFactorModal()">+ Dodaj faktor</button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<div class="factor-columns">
    <div class="factor-column">
        <h3>Spoljni faktori (<?= count($externalFactors) ?>)</h3>
        <?php if (empty($externalFactors)): ?>
            <p class="empty-state">Još uvek nema unetih spoljnih faktora.</p>
        <?php else: ?>
            <?php foreach ($externalFactors as $factor): ?>
                <?php include __DIR__ . '/../includes/factor-card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="factor-column">
        <h3>Unutrašnji faktori (<?= count($internalFactors) ?>)</h3>
        <?php if (empty($internalFactors)): ?>
            <p class="empty-state">Još uvek nema unetih unutrašnjih faktora.</p>
        <?php else: ?>
            <?php foreach ($internalFactors as $factor): ?>
                <?php include __DIR__ . '/../includes/factor-card.php'; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<div class="modal-overlay" id="factor-modal-overlay" onclick="closeFactorModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title" id="factor-modal-title">Dodaj faktor</span>
            <button type="button" class="modal-close" onclick="closeFactorModal()" aria-label="Zatvori">&times;</button>
        </div>

        <form method="post" id="factor-modal-form">
            <input type="hidden" name="action" id="factor-modal-action" value="add">
            <input type="hidden" name="id" id="factor-modal-id" value="">

            <div class="form-row">
                <label for="modal_factor_type">Vrsta faktora</label>
                <select name="factor_type" id="modal_factor_type" required>
                    <option value="">Izaberite...</option>
                    <option value="spoljni">Spoljni</option>
                    <option value="unutrasnji">Unutrašnji</option>
                </select>
            </div>

            <div class="form-row">
                <label for="modal_category">Kategorija (opciono)</label>
                <input type="text" name="category" id="modal_category" placeholder="npr. zakonski, tržište, tehnologija">
            </div>

            <div class="form-row">
                <label for="modal_description">Opis faktora</label>
                <textarea name="description" id="modal_description" rows="4" required
                    placeholder="npr. Zakon o zaštiti podataka o ličnosti zahteva posebnu pažnju pri obradi ličnih podataka klijenata."></textarea>
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromFactorModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeFactorModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">Sačuvaj</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openAddFactorModal() {
    document.getElementById('factor-modal-title').textContent = 'Dodaj faktor';
    document.getElementById('factor-modal-action').value = 'add';
    document.getElementById('factor-modal-id').value = '';
    document.getElementById('modal_factor_type').value = '';
    document.getElementById('modal_category').value = '';
    document.getElementById('modal_description').value = '';
    document.getElementById('factor-modal-overlay').classList.add('is-open');
}

function openEditFactorModal(factor) {
    document.getElementById('factor-modal-title').textContent = 'Uredi faktor';
    document.getElementById('factor-modal-action').value = 'update';
    document.getElementById('factor-modal-id').value = factor.id;
    document.getElementById('modal_factor_type').value = factor.factor_type;
    document.getElementById('modal_category').value = factor.category;
    document.getElementById('modal_description').value = factor.description;
    document.getElementById('factor-modal-overlay').classList.add('is-open');
}

function closeFactorModal() {
    document.getElementById('factor-modal-overlay').classList.remove('is-open');
}

function openHelpFromFactorModal() {
    closeFactorModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeFactorModal();
    }
});
</script>
