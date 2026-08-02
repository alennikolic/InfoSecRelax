<?php
/**
 * src/config/auth.php
 *
 * Jezgro autentifikacije i RBAC-a (kontrola pristupa po stranici).
 *
 * Model:
 *   - Super admin (users.is_super_admin = TRUE) NEMA organization_id
 *     i NEMA role_id - ne prolazi kroz role_page_permissions. Jedina
 *     njegova nadležnost je kreiranje novih organizacija
 *     (modules/organizacije.php). Prvi super admin se ugrađuje direktno
 *     u bazu preko INSERT-a u db/init.sql (bcrypt heš lozinke) - nema
 *     web formu za kreiranje super admina, namerno. bin/create-super-admin.php
 *     ostaje dostupna kao CLI alatka za dodatne super admin naloge ili
 *     reset lozinke postojećeg (ON DUPLICATE KEY UPDATE po email-u).
 *   - Običan korisnik pripada TAČNO JEDNOJ organizaciji i ima TAČNO
 *     JEDNU custom rolu (users.role_id) unutar te organizacije. Rola
 *     nosi permission_level po stranici: 'zabranjeno' (default,
 *     odsustvo reda se tumači isto), 'citanje' ili 'puno'.
 *
 * Ovaj fajl se učitava preko require_once - i sam interno učitava
 * config/database.php preko require_once. Nijedan drugi fajl ne sme
 * učitati auth.php preko plain require (videti napomenu u
 * config/database.php za objašnjenje zašto).
 */

declare(strict_types=1);

require_once __DIR__ . '/database.php';

const PERMISSION_NONE = 'zabranjeno';
const PERMISSION_READ = 'citanje';
const PERMISSION_FULL = 'puno';

/**
 * Pokreće PHP sesiju sa bezbednim podešavanjima kolačića, ako sesija
 * već nije pokrenuta. Bezbedno se poziva ponovo iz više fajlova.
 */
function startAppSession(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/**
 * Vraća red trenutno ulogovanog korisnika iz baze (svež upit svaki put
 * u okviru jednog zahteva, keširan statički da se ne ponavlja upit) ili
 * null ako niko nije ulogovan / nalog je u međuvremenu deaktiviran.
 */
function currentUser(): ?array
{
    startAppSession();

    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cached = null;
    static $resolved = false;

    if ($resolved) {
        return $cached;
    }
    $resolved = true;

    $pdo  = getDbConnection();
    $stmt = $pdo->prepare(
        'SELECT id, organization_id, role_id, email, is_active, is_super_admin
         FROM users WHERE id = :id'
    );
    $stmt->execute(['id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user === false || !(bool) $user['is_active']) {
        logoutUser();
        return null;
    }

    $cached = $user;
    return $user;
}

/**
 * Zaustavlja izvršavanje i preusmerava na prijavu ako niko nije
 * ulogovan. Vraća red korisnika kad jeste (za pozivaoca).
 */
function requireAuth(): array
{
    $user = currentUser();
    if ($user === null) {
        header('Location: ?page=prijava');
        exit;
    }
    return $user;
}

/**
 * Isto kao requireAuth(), ali dodatno zahteva is_super_admin = TRUE.
 *
 * NAPOMENA: poziva se isključivo iz konteksta gde je header.php već
 * učitan (modules/organizacije.php, posle index.php GRANA 2) - zato
 * pri odbijanju pristupa zatvara layout preko footer.php pre exit-a,
 * umesto da ostavi nezatvoren HTML.
 */
function requireSuperAdmin(): array
{
    $user = requireAuth();
    if (!(bool) $user['is_super_admin']) {
        http_response_code(403);
        require __DIR__ . '/../includes/pristup-odbijen.php';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }
    return $user;
}

/**
 * Proverava email/lozinku, i ako su tačni i nalog je aktivan, otvara
 * sesiju (uz session_regenerate_id radi zaštite od session fixation).
 * Vraća red korisnika ili null.
 */
function loginUser(PDO $pdo, string $email, string $password): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email AND is_active = TRUE');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user === false || !password_verify($password, $user['password_hash'])) {
        return null;
    }

    startAppSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];

    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = :id')
        ->execute(['id' => $user['id']]);

    return $user;
}

/**
 * Briše sesiju u potpunosti (podaci + kolačić).
 */
function logoutUser(): void
{
    startAppSession();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

/**
 * Nivo prava jedne role na jednoj stranici. $roleId = null (npr.
 * korisnik bez dodeljene role) uvek vraća 'zabranjeno'.
 */
function permissionLevelFor(PDO $pdo, ?int $roleId, string $slug): string
{
    if ($roleId === null) {
        return PERMISSION_NONE;
    }

    $stmt = $pdo->prepare(
        'SELECT permission_level FROM role_page_permissions
         WHERE role_id = :role_id AND page_slug = :slug'
    );
    $stmt->execute(['role_id' => $roleId, 'slug' => $slug]);
    $level = $stmt->fetchColumn();

    return $level !== false ? (string) $level : PERMISSION_NONE;
}

/**
 * Poredak: zabranjeno (0) < citanje (1) < puno (2).
 */
function permissionAtLeast(string $level, string $required): bool
{
    $rank = [PERMISSION_NONE => 0, PERMISSION_READ => 1, PERMISSION_FULL => 2];
    return ($rank[$level] ?? 0) >= ($rank[$required] ?? 0);
}

/**
 * Zaustavlja izvršavanje ("Nemate pristup") ako ulogovani ORGANIZACIONI
 * korisnik (ne super admin) nema bar $required nivo prava na $slug.
 * Vraća stvarni nivo prava - moduli/šabloni ga koriste da sakriju
 * dugmad za dodavanje/izmenu/brisanje kad je nivo 'citanje'.
 *
 * NAPOMENA: poziva se isključivo iz konteksta gde je header.php već
 * učitan (index.php GRANA 3, posle require header.php) - zato pri
 * odbijanju pristupa zatvara layout preko footer.php pre exit-a,
 * umesto da ostavi nezatvoren HTML.
 */
function requirePagePermission(PDO $pdo, array $user, string $slug, string $required = PERMISSION_READ): string
{
    $roleId = $user['role_id'] !== null ? (int) $user['role_id'] : null;
    $level  = permissionLevelFor($pdo, $roleId, $slug);

    if (!permissionAtLeast($level, $required)) {
        http_response_code(403);
        require __DIR__ . '/../includes/pristup-odbijen.php';
        require __DIR__ . '/../includes/footer.php';
        exit;
    }

    return $level;
}

/**
 * Kreira (ako ne postoji) sistemsku "Administrator" rolu za datu
 * organizaciju, sa 'puno' pravom na SVAKU stranicu trenutno definisanu
 * u config/menu.php. Idempotentna - bezbedno se poziva ponovo (npr.
 * kad se doda nova stavka u meni, ponovni poziv dopunjuje nedostajuća
 * prava za tu rolu, za sve organizacije koje je pozovu). Poziva se pri
 * osnivanju svake nove organizacije (modules/organizacije.php).
 */
function ensureAdministratorRole(PDO $pdo, int $organizationId): int
{
    $pdo->prepare(
        "INSERT IGNORE INTO roles (organization_id, name, description, is_system)
         VALUES (:org_id, 'Administrator', 'Podrazumevana rola - puno pravo na sve stranice. Ne može se obrisati.', TRUE)"
    )->execute(['org_id' => $organizationId]);

    $stmt = $pdo->prepare(
        "SELECT id FROM roles WHERE organization_id = :org_id AND name = 'Administrator'"
    );
    $stmt->execute(['org_id' => $organizationId]);
    $roleId = (int) $stmt->fetchColumn();

    $menu = require __DIR__ . '/menu.php';

    $insertPerm = $pdo->prepare(
        "INSERT IGNORE INTO role_page_permissions (role_id, page_slug, permission_level)
         VALUES (:role_id, :slug, 'puno')"
    );
    foreach ($menu as $item) {
        $insertPerm->execute(['role_id' => $roleId, 'slug' => $item['slug']]);
    }

    return $roleId;
}
