<?php
/**
 * src/modules/organizacije.php
 *
 * Jedina stranica dostupna super adminu (videti GRANA 2 u index.php).
 * Super admin nema organization_id/role_id - ne prolazi kroz
 * role_page_permissions, pa ovaj modul ne poziva
 * requirePagePermission() nego samo requireSuperAdmin() kao dodatnu
 * odbranu (index.php već garantuje granu, ovo je "pojas i tregeri").
 *
 * Kreiranje nove organizacije uvek ide u tri koraka u jednoj
 * transakciji: (1) INSERT organizations, (2) ensureAdministratorRole()
 * - kreira "Administrator" rolu sa 'puno' na sve trenutne stranice
 * menija, (3) INSERT prvog korisnika te organizacije, sa tom rolom.
 * Ako bilo koji korak pukne, ništa se ne upisuje (rollback) - firma
 * nikad ne ostaje "napola" kreirana, bez ijednog naloga koji može da
 * joj pristupi.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$pdo = getDbConnection();
requireSuperAdmin();

$errors = [];

// --- Kreiranje nove organizacije + prvog administratora ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_organization') {
    csrfRequireValid();

    $orgName       = trim((string) ($_POST['org_name'] ?? ''));
    $adminEmail    = trim((string) ($_POST['admin_email'] ?? ''));
    $adminPassword = (string) ($_POST['admin_password'] ?? '');

    if ($orgName === '') {
        $errors[] = 'Naziv firme je obavezan.';
    }
    if ($adminEmail === '' || !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Unesite ispravan email za administratora firme.';
    }
    if (strlen($adminPassword) < 8) {
        $errors[] = 'Početna lozinka administratora mora imati bar 8 karaktera.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare('INSERT INTO organizations (name) VALUES (:name)')
                ->execute(['name' => $orgName]);
            $organizationId = (int) $pdo->lastInsertId();

            $roleId = ensureAdministratorRole($pdo, $organizationId);

            $pdo->prepare(
                'INSERT INTO users (organization_id, role_id, email, password_hash, is_active)
                 VALUES (:org_id, :role_id, :email, :hash, TRUE)'
            )->execute([
                'org_id' => $organizationId,
                'role_id' => $roleId,
                'email'  => $adminEmail,
                'hash'   => password_hash($adminPassword, PASSWORD_DEFAULT),
            ]);

            $pdo->commit();

            header('Location: ?page=organizacije&created=1');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $errors[] = $e->getCode() === '23000'
                ? 'Firma ili email administratora već postoje.'
                : 'Greška pri kreiranju firme: ' . $e->getMessage();
        }
    }
}

// --- Spisak postojećih organizacija, sa brojem korisnika ---
$organizations = $pdo->query(
    'SELECT o.id, o.name, o.certification_status, o.created_at,
            COUNT(u.id) AS user_count
     FROM organizations o
     LEFT JOIN users u ON u.organization_id = o.id
     GROUP BY o.id
     ORDER BY o.created_at DESC'
)->fetchAll();

$certificationLabels = [
    'priprema'         => 'U pripremi',
    'sertifikovano'    => 'Sertifikovano',
    'nadzorna_provera' => 'Nadzorna provera',
    'resertifikacija'  => 'Resertifikacija',
];
?>

<?php if (isset($_GET['created'])): ?>
<div class="alert alert-success">
    <p>Firma je uspešno kreirana, zajedno sa nalogom administratora.</p>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title">Nova firma</span>
    </div>
    <form method="post" action="?page=organizacije" class="stacked-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_organization">
        <label class="form-field">
            <span>Naziv firme</span>
            <input type="text" name="org_name" required>
        </label>
        <label class="form-field">
            <span>Email administratora firme</span>
            <input type="email" name="admin_email" required>
        </label>
        <label class="form-field">
            <span>Početna lozinka administratora</span>
            <input type="password" name="admin_password" required minlength="8">
        </label>
        <button type="submit" class="btn-primary">Kreiraj firmu</button>
    </form>
</div>

<?php if (empty($organizations)): ?>
    <p class="empty-state">Još uvek nema kreiranih firmi.</p>
<?php else: ?>
    <?php foreach ($organizations as $org): ?>
    <div class="factor-card">
        <div class="card-header-row">
            <span class="card-title"><?= htmlspecialchars($org['name']) ?></span>
            <span class="status-badge is-neutral">
                <?= htmlspecialchars($certificationLabels[$org['certification_status']] ?? $org['certification_status']) ?>
            </span>
        </div>
        <p class="item-meta">
            <?= (int) $org['user_count'] ?> korisnik(a) · kreirano <?= htmlspecialchars((string) $org['created_at']) ?>
        </p>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
