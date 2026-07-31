<?php
/**
 * modules/kontekst.php - Klauzula 4.1: Kontekst organizacije.
 *
 * Prvi potpuno funkcionalan modul u aplikaciji. Uspostavlja obrazac
 * (forma za dodavanje + prikaz + brisanje, sve nad jednom tabelom) koji
 * će se ponoviti, uz manje izmene, kroz većinu ostalih modula.
 */

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

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
?>

<p class="module-intro">
    Klauzula 4.1 traži da identifikujete spoljne i unutrašnje faktore koji utiču na vašu firmu
    i na to da li će sistem bezbednosti informacija zaista postići svoju svrhu.
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
        <label for="factor_type">Vrsta faktora</label>
        <select name="factor_type" id="factor_type" required>
            <option value="">Izaberite...</option>
            <option value="spoljni">Spoljni</option>
            <option value="unutrasnji">Unutrašnji</option>
        </select>
    </div>

    <div class="form-row">
        <label for="category">Kategorija (opciono)</label>
        <input type="text" name="category" id="category" placeholder="npr. zakonski, tržište, tehnologija">
    </div>

    <div class="form-row">
        <label for="description">Opis faktora</label>
        <textarea name="description" id="description" rows="3" required
            placeholder="npr. Zakon o zaštiti podataka o ličnosti zahteva posebnu pažnju pri obradi ličnih podataka klijenata."></textarea>
    </div>

    <button type="submit" class="btn-primary">Dodaj faktor</button>
</form>

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
