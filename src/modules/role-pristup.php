<?php
/**
 * src/modules/role-pristup.php
 *
 * Upravljanje custom rolama organizacije i njihovim pravima pristupa
 * po stranici (role_page_permissions). Isti list/edit obrazac preko
 * GET parametra kao izjava-primenljivosti.php:
 *
 *   ?page=role-pristup             -> spisak svih rola organizacije
 *   ?page=role-pristup&role=3      -> uređivanje prava jedne role
 *
 * Za svaku stranicu iz config/menu.php prikazuje se po jedna grupa
 * radio dugmadi (zabranjeno / citanje / puno) - default-deny: stranica
 * bez eksplicitno sačuvane vrednosti se tretira kao 'zabranjeno', pa
 * forma ispod uvek šalje SVE stranice eksplicitno (uključujući
 * "zabranjeno"), da baza tačno odražava ono što je administrator
 * video na ekranu.
 *
 * Sistemska "Administrator" rola (is_system = TRUE) se ne može ni
 * obrisati ni izmeniti - uvek mora postojati bar jedna rola sa punim
 * pristupom svemu, da organizacija nikad ne ostane "zaključana" bez
 * ijednog naloga koji može da upravlja rolama.
 *
 * $user i $currentPermission - videti napomenu u modules/korisnici.php.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$pdo            = getDbConnection();
$organizationId = (int) $user['organization_id'];
$canManage      = $currentPermission === PERMISSION_FULL;
$fullMenu       = require __DIR__ . '/../config/menu.php';

$errors = [];

$requestedRoleId = isset($_GET['role']) ? (int) $_GET['role'] : null;

/**
 * Učitava rolu iz OVE organizacije po id-ju, ili null ako ne postoji /
 * pripada drugoj organizaciji.
 */
function loadOrgRole(PDO $pdo, int $organizationId, int $roleId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM roles WHERE id = :id AND organization_id = :org_id');
    $stmt->execute(['id' => $roleId, 'org_id' => $organizationId]);
    $role = $stmt->fetch();

    return $role !== false ? $role : null;
}

// --- Kreiranje nove role ---
if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_role') {
    csrfRequireValid();

    $name        = trim((string) ($_POST['name'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($name === '') {
        $errors[] = 'Naziv role je obavezan.';
    }

    if (empty($errors)) {
        try {
            $pdo->prepare(
                'INSERT INTO roles (organization_id, name, description, is_system)
                 VALUES (:org_id, :name, :description, FALSE)'
            )->execute([
                'org_id'      => $organizationId,
                'name'        => $name,
                'description' => $description !== '' ? $description : null,
            ]);

            header('Location: ?page=role-pristup&role=' . (int) $pdo->lastInsertId());
            exit;
        } catch (PDOException $e) {
            $errors[] = $e->getCode() === '23000'
                ? 'Rola sa tim nazivom već postoji.'
                : 'Greška pri čuvanju: ' . $e->getMessage();
        }
    }
}

// --- Brisanje role (nesistemske) ---
if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_role') {
    csrfRequireValid();

    $roleId = (int) ($_POST['role_id'] ?? 0);
    $role   = loadOrgRole($pdo, $organizationId, $roleId);

    if ($role === null) {
        $errors[] = 'Rola nije pronađena.';
    } elseif ((bool) $role['is_system']) {
        $errors[] = 'Sistemska rola "Administrator" se ne može obrisati.';
    } else {
        // Korisnici koji su imali ovu rolu ostaju bez role (role_id
        // postaje NULL preko ON DELETE SET NULL) - ne mogu više da
        // pristupe nijednoj stranici dok im se ne dodeli nova rola.
        $pdo->prepare('DELETE FROM roles WHERE id = :id AND organization_id = :org_id')
            ->execute(['id' => $roleId, 'org_id' => $organizationId]);

        header('Location: ?page=role-pristup&deleted=1');
        exit;
    }
}

// --- Čuvanje prava pristupa za jednu rolu ---
if ($canManage && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_permissions') {
    csrfRequireValid();

    $roleId = (int) ($_POST['role_id'] ?? 0);
    $role   = loadOrgRole($pdo, $organizationId, $roleId);

    if ($role === null) {
        $errors[] = 'Rola nije pronađena.';
    } elseif ((bool) $role['is_system']) {
        $errors[] = 'Prava sistemske role "Administrator" su fiksna (puno na sve) i ne mogu se menjati.';
    } else {
        $validLevels = [PERMISSION_NONE, PERMISSION_READ, PERMISSION_FULL];
        $upsert = $pdo->prepare(
            'INSERT INTO role_page_permissions (role_id, page_slug, permission_level)
             VALUES (:role_id, :slug, :level)
             ON DUPLICATE KEY UPDATE permission_level = VALUES(permission_level)'
        );

        foreach ($fullMenu as $item) {
            $submitted = (string) ($_POST['permission'][$item['slug']] ?? PERMISSION_NONE);
            $level     = in_array($submitted, $validLevels, true) ? $submitted : PERMISSION_NONE;

            $upsert->execute(['role_id' => $roleId, 'slug' => $item['slug'], 'level' => $level]);
        }

        header('Location: ?page=role-pristup&role=' . $roleId . '&saved=1');
        exit;
    }
}

$permissionLabels = [
    PERMISSION_NONE => 'Zabranjeno',
    PERMISSION_READ => 'Čitanje',
    PERMISSION_FULL => 'Puno',
];

// ======================================================================
// PRIKAZ: uređivanje jedne role
// ======================================================================
if ($requestedRoleId !== null) {
    $editingRole = loadOrgRole($pdo, $organizationId, $requestedRoleId);

    if ($editingRole === null) {
        echo '<p class="empty-state">Rola nije pronađena.</p>';
    } else {
        $permStmt = $pdo->prepare(
            'SELECT page_slug, permission_level FROM role_page_permissions WHERE role_id = :role_id'
        );
        $permStmt->execute(['role_id' => (int) $editingRole['id']]);
        $currentLevels = [];
        foreach ($permStmt->fetchAll() as $row) {
            $currentLevels[$row['page_slug']] = $row['permission_level'];
        }

        $groupedMenu = [];
        foreach ($fullMenu as $item) {
            $groupedMenu[$item['group'] ?? 'Ostalo'][] = $item;
        }
        ?>

        <p><a href="?page=role-pristup">&larr; Nazad na spisak rola</a></p>

        <?php if (isset($_GET['saved'])): ?>
        <div class="alert alert-success"><p>Prava su sačuvana.</p></div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?><p><?= htmlspecialchars($error) ?></p><?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="factor-card">
            <div class="card-header-row">
                <span class="card-title"><?= htmlspecialchars($editingRole['name']) ?></span>
                <?php if ((bool) $editingRole['is_system']): ?>
                <span class="status-badge is-neutral">Sistemska - uvek puno pravo</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($editingRole['description'])): ?>
                <p class="item-meta"><?= htmlspecialchars($editingRole['description']) ?></p>
            <?php endif; ?>
        </div>

        <?php if ((bool) $editingRole['is_system']): ?>
            <p class="empty-state">
                Ova rola uvek ima "puno" pravo na svaku stranicu i ne može se menjati -
                svaka organizacija mora imati bar jednu rolu koja sigurno ima pristup svemu.
            </p>
        <?php else: ?>
        <form method="post" action="?page=role-pristup&role=<?= (int) $editingRole['id'] ?>">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="save_permissions">
            <input type="hidden" name="role_id" value="<?= (int) $editingRole['id'] ?>">

            <?php foreach ($groupedMenu as $groupName => $items): ?>
            <div class="factor-card">
                <div class="card-header-row">
                    <span class="card-title"><?= htmlspecialchars($groupName) ?></span>
                </div>
                <table class="permission-table">
                    <?php foreach ($items as $item): ?>
                    <?php $level = $currentLevels[$item['slug']] ?? PERMISSION_NONE; ?>
                    <tr>
                        <td class="permission-table-page">
                            <?= htmlspecialchars($item['title']) ?>
                            <?php if (!empty($item['iso_ref'])): ?>
                                <span class="nav-ref"><?= htmlspecialchars($item['iso_ref']) ?></span>
                            <?php endif; ?>
                        </td>
                        <?php foreach ([PERMISSION_NONE, PERMISSION_READ, PERMISSION_FULL] as $option): ?>
                        <td class="permission-table-option">
                            <label>
                                <input type="radio"
                                       name="permission[<?= htmlspecialchars($item['slug']) ?>]"
                                       value="<?= $option ?>"
                                       <?= $level === $option ? 'checked' : '' ?>>
                                <?= htmlspecialchars($permissionLabels[$option]) ?>
                            </label>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endforeach; ?>

            <button type="submit" class="btn-primary">Sačuvaj prava</button>
        </form>
        <?php endif; ?>

        <?php
    }
    return;
}

// ======================================================================
// PRIKAZ: spisak rola
// ======================================================================
$rolesStmt = $pdo->prepare(
    'SELECT r.*, COUNT(u.id) AS user_count
     FROM roles r
     LEFT JOIN users u ON u.role_id = r.id
     WHERE r.organization_id = :org_id
     GROUP BY r.id
     ORDER BY r.is_system DESC, r.name'
);
$rolesStmt->execute(['org_id' => $organizationId]);
$roles = $rolesStmt->fetchAll();
?>

<?php if (isset($_GET['deleted'])): ?>
<div class="alert alert-success"><p>Rola je obrisana.</p></div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?><p><?= htmlspecialchars($error) ?></p><?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($canManage): ?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title">Nova rola</span>
    </div>
    <form method="post" action="?page=role-pristup" class="stacked-form">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="add_role">
        <label class="form-field">
            <span>Naziv role</span>
            <input type="text" name="name" required placeholder="npr. Koordinator rizika">
        </label>
        <label class="form-field">
            <span>Opis (opciono)</span>
            <input type="text" name="description">
        </label>
        <button type="submit" class="btn-primary">Napravi rolu</button>
    </form>
    <p class="item-meta">
        Nova rola počinje sa "zabranjeno" na svim stranicama - podesi prava
        klikom na rolu ispod.
    </p>
</div>
<?php endif; ?>

<?php foreach ($roles as $role): ?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title">
            <a href="?page=role-pristup&role=<?= (int) $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></a>
        </span>
        <?php if ((bool) $role['is_system']): ?>
        <span class="status-badge is-neutral">Sistemska</span>
        <?php endif; ?>
    </div>
    <?php if (!empty($role['description'])): ?>
        <p class="item-meta"><?= htmlspecialchars($role['description']) ?></p>
    <?php endif; ?>
    <p class="item-meta"><?= (int) $role['user_count'] ?> korisnik(a) sa ovom rolom</p>
    <?php if ($canManage && !(bool) $role['is_system']): ?>
    <div class="card-footer-right">
        <form method="post" action="?page=role-pristup"
              onsubmit="return confirm('Obrisati rolu &quot;<?= htmlspecialchars($role['name'], ENT_QUOTES) ?>&quot;? Korisnici sa ovom rolom ostaju bez pristupa dok im se ne dodeli nova.');">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="delete_role">
            <input type="hidden" name="role_id" value="<?= (int) $role['id'] ?>">
            <button type="submit" class="btn-secondary">Obriši</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
