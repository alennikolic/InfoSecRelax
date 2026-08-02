<?php
/**
 * src/config/database.php
 *
 * PDO konekcija ka MySQL bazi.
 *
 * Kredencijali se čitaju iz environment varijabli koje docker-compose.yml
 * prosleđuje web servisu (vidi environment: sekciju za "web" u
 * docker-compose.yml). Fallback vrednosti ispod odgovaraju istim
 * kredencijalima kao i sam docker-compose.yml, tako da kod radi i ako
 * neko pokrene PHP ugrađeni server lokalno, van kontejnera.
 *
 * Upotreba u bilo kom modulu:
 *
 *     require __DIR__ . '/../config/database.php';
 *     $pdo = getDbConnection();
 *     $stmt = $pdo->query('SELECT * FROM context_factors WHERE organization_id = 1');
 *
 * IZMENA (RBAC): funkcije su omotane u function_exists() provere. Razlog:
 * config/auth.php sada učitava ovaj fajl preko require_once JEDNOM, na
 * samom početku, u index.php - pre nego što se učita header.php ili bilo
 * koji modul. Svaki modul i dalje, kao i do sada, samostalno radi
 * "require __DIR__ . '/../config/database.php';" (plain require, ne
 * require_once) na svom vrhu - bez ove provere, to bi izazvalo fatalnu
 * grešku "Cannot redeclare getDbConnection()" jer plain require ne
 * proverava da li je fajl već učitan preko require_once. Ponašanje
 * funkcija ispod je nepromenjeno.
 */

declare(strict_types=1);

if (!function_exists('getDbConnection')) {
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = getenv('DB_HOST') ?: 'db';
        $port = getenv('DB_PORT') ?: '3306';
        $name = getenv('MYSQL_DATABASE') ?: 'infosecrelax';
        $user = getenv('MYSQL_USER') ?: 'infosecrelax_user';
        $pass = getenv('MYSQL_PASSWORD') ?: 'infosecrelax_password';

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    return $pdo;
}
}

if (!function_exists('ensureDefaultOrganization')) {
/**
 * Istorijsko ime iz perioda pre multi-tenant prijave - i dalje se
 * poziva iz mnogo modula. Otkako postoji RBAC (config/auth.php), ova
 * funkcija vraća STVARNI organization_id ulogovanog organizacionog
 * korisnika (preko currentUser()) - NE više uvek 1.
 *
 * Ovo je namerno urađeno ovde, u jednoj funkciji, umesto da se svaki
 * od ~25 postojećih modula pojedinačno menja da poziva
 * requireAuth()['organization_id'] - isti poziv, isto ime funkcije,
 * isti povratni tip, samo ispravan sadržaj. Svaki modul koji već radi
 * "$organizationId = ensureDefaultOrganization($pdo);" sada automatski
 * upisuje/čita podatke SVOJE firme, bez ijedne izmene u tom modulu.
 *
 * function_exists('currentUser') je odbrana za slučaj da neko pozove
 * ovu funkciju bez da je config/auth.php uopšte učitan (npr. buduća
 * CLI skripta van web konteksta) - tada se vraća na staro ponašanje
 * (organizacija id=1, kreirana ako ne postoji), umesto fatalne greške.
 */
function ensureDefaultOrganization(PDO $pdo): int
{
    if (function_exists('currentUser')) {
        $user = currentUser();
        if ($user !== null && $user['organization_id'] !== null) {
            return (int) $user['organization_id'];
        }
    }

    $pdo->exec("INSERT IGNORE INTO organizations (id, name) VALUES (1, 'Moja firma')");
    return 1;
}
}
