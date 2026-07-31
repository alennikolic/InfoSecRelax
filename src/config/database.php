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
 */

declare(strict_types=1);

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

/**
 * Privremeno rešenje dok ne postoji prijava/registracija firmi:
 * osigurava da organizacija sa id=1 postoji, kako bi svi moduli imali
 * na šta da se oslone preko organization_id. Bezbedno se poziva
 * ponovljeno - INSERT IGNORE ne radi ništa ako red već postoji.
 *
 * TODO: ukloniti kad se doda prava registracija/prijava firmi i
 * organization_id počne da dolazi iz sesije ulogovanog korisnika.
 */
function ensureDefaultOrganization(PDO $pdo): int
{
    $pdo->exec("INSERT IGNORE INTO organizations (id, name) VALUES (1, 'Moja firma')");
    return 1;
}
