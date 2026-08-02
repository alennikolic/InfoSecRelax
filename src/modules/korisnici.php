<?php
/**
 * src/modules/korisnici.php
 *
 * Upravljanje korisničkim nalozima UNUTAR SOPSTVENE organizacije
 * (dodavanje, dodela role, aktiviranje/deaktiviranje). Ne postoji
 * brisanje naloga - isti princip kao personnel.is_active - istorija
 * (last_login_at, ko je šta radio) ostaje netaknuta.
 *
 * $user i $currentPermission su već postavljeni u index.php pre nego
 * što je ovaj fajl učitan (RBAC provera za ?page=korisnici se već
 * desila tamo) - isti obrazac kao $menu/$currentItem u header.php.
 * $currentPermission === 'puno' otključava formu za dodavanje i
 * dugmad za izmenu; 'citanje' prikazuje samo spisak.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$pdo             = getDbConnection();
$organizationId  = (int) $user['organization_id'];
$canManage       = $currentPermission === PERMISSION_FULL;

$errors = [];

// --- Dodavanje korisnika ---
if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_user') {
    csrfRequireValid();

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $roleId   = (int) ($_POST['role_id'] ?? 0);

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Unesite ispravan email.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Lozinka mora imati bar 8 karaktera.';
    }
    if ($roleId <= 0) {
        $errors[] = 'Izaberite rolu.';
    }

    if (empty($errors)) {
        // Rola mora pripadati OVOJ organizaciji - sprečava da neko
        // ručno pošalje role_id druge firme.
        $roleCheck = $pdo->prepare('SELECT id FROM roles WHERE id = :id AND organization_id = :org_id');
        $roleCheck->execute(['id' => $roleId, 'org_id' => $organizationId]);

        if ($roleCheck->fetchColumn() === false) {
            $errors[] = 'Izabrana rola nije pronađena.';
        } else {
            try {
                $pdo->prepare(
                    'INSERT INTO users (organization_id, role_id, email, password_hash, is_active)
                     VALUES (:org_id, :role_id, :email, :hash, TRUE)'
                )->execute([
                    'org_id'  => $organizationId,
                    'role_id' => $roleId,
                    'email'   => $email,
                    'hash'    => password_hash($password, PASSWORD_DEFAULT),
                ]);

                header('Location: ?page=korisnici&added=1');
                exit;
            } catch (PDOException $e) {
                $errors[] = $e->getCode() === '23000'
                    ? 'Nalog sa tim email-om već postoji.'
                    : 'Greška pri čuvanju: ' . $e->getMessage();
            }
        }
    }
}

// --- Aktiviranje / deaktiviranje naloga ---
if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_active') {
    csrfRequireValid();

    $targetUserId = (int) ($_POST['user_id'] ?? 0);

    if ($targetUserId === (int) $user['id']) {
        $errors[] = 'Ne možete deaktivirati sopstveni nalog.';
    } else {
        $pdo->prepare(
            'UPDATE users SET is_active = NOT is_active
             WHERE id = :id AND organization_id = :org_id'
        )->execute(['id' => $targetUserId, 'org_id' => $organizationId]);

        header('Location: ?page=korisnici');
        exit;
    }
}

// --- Podaci za prikaz ---
$rolesStmt = $pdo->prepare('SELECT id, name FROM roles WHERE organization_id = :org_id ORDER BY name');
$rolesStmt->execute(['org_id' => $organizationId]);
$roles = $rolesStmt->fetchAll();

$usersStmt = $pdo->prepare(
    'SELECT u.id, u.email, u.is_active, u.last_login_at, r.name AS role_name
     FROM users u
     LEFT JOIN roles r ON r.id = u.role_id
     WHERE u.organization_id = :org_id
     ORDER BY u.email'
);
$usersStmt->execute(['org_id' => $organizationId]);
$users = $usersStmt->fetchAll();
?>

<?php if (isset($_GET['added'])): ?>
<div class="alert alert-success">
    <p>Korisnik je uspešno dodat.</p>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title">Novi korisnik</span>
    </div>
    <?php if (empty($roles)): ?>
        <p class="empty-state">
            Nema definisanih rola. Prvo napravite bar jednu rolu na
            <a href="?page=role-pristup">Role i prava pristupa</a>.
        </p>
    <?php else: ?>
    <form method="post" action="?page=korisnici" class="stacked-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_user">
        <label class="form-field">
            <span>Email</span>
            <input type="email" name="email" required>
        </label>
        <label class="form-field">
            <span>Početna lozinka</span>
            <input type="password" name="password" required minlength="8">
        </label>
        <label class="form-field">
            <span>Rola</span>
            <select name="role_id" required>
                <?php foreach ($roles as $role): ?>
                <option value="<?= (int) $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button type="submit" class="btn-primary">Dodaj korisnika</button>
    </form>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (empty($users)): ?>
    <p class="empty-state">Još uvek nema korisnika u ovoj organizaciji.</p>
<?php else: ?>
    <?php foreach ($users as $row): ?>
    <div class="factor-card">
        <div class="card-header-row">
            <span class="card-title"><?= htmlspecialchars($row['email']) ?></span>
            <span class="status-badge <?= $row['is_active'] ? 'is-positive' : 'is-danger' ?>">
                <?= $row['is_active'] ? 'Aktivan' : 'Deaktiviran' ?>
            </span>
        </div>
        <p class="item-meta">
            Rola: <?= htmlspecialchars($row['role_name'] ?? '— nema dodeljenu rolu —') ?>
            <?php if ($row['last_login_at'] !== null): ?>
                · poslednja prijava <?= htmlspecialchars((string) $row['last_login_at']) ?>
            <?php endif; ?>
        </p>
        <?php if ($canManage && (int) $row['id'] !== (int) $user['id']): ?>
        <div class="card-footer-right">
            <form method="post" action="?page=korisnici">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle_active">
                <input type="hidden" name="user_id" value="<?= (int) $row['id'] ?>">
                <button type="submit" class="btn-secondary">
                    <?= $row['is_active'] ? 'Deaktiviraj' : 'Aktiviraj' ?>
                </button>
            </form>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
