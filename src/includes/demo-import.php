<?php
/**
 * src/includes/demo-import.php
 *
 * Logika za uvoz demo podataka - jedini deo aplikacije kome je potrebna
 * posebna PDO konekcija sa uključenim PDO::MYSQL_ATTR_MULTI_STATEMENTS,
 * pošto db/demo-data.sql sadrži desetine naredbi u jednom fajlu, a
 * getDbConnection() iz config/database.php namerno nema tu opciju
 * uključenu - multi-statement izvršavanje ostaje ograničeno samo na ovu
 * jednu funkciju, ne utiče na ostatak aplikacije.
 */

declare(strict_types=1);

/**
 * Učitava i izvršava db/demo-data.sql u celosti - briše sve postojeće
 * tenant podatke i zamenjuje ih demo skupom (videti komentar na vrhu
 * samog demo-data.sql fajla za tačan spisak tabela).
 *
 * Fajl mora biti dostupan na /var/www/db/demo-data.sql - videti
 * ./db:/var/www/db:ro mount u docker-compose.yml za web servis.
 *
 * Baca RuntimeException ako fajl ne postoji, PDOException ako neka
 * naredba iz fajla ne uspe.
 */
function importDemoData(): void
{
    $path = '/var/www/db/demo-data.sql';

    if (!is_file($path)) {
        throw new RuntimeException(
            'db/demo-data.sql nije pronađen na ' . $path . ' - proveri da li je ' .
            '"./db:/var/www/db:ro" mount dodat u docker-compose.yml za web servis ' .
            'i da li je kontejner ponovo pokrenut (docker-compose up -d) posle te izmene.'
        );
    }

    $sql = file_get_contents($path);

    if ($sql === false) {
        throw new RuntimeException('Neuspešno čitanje fajla ' . $path . '.');
    }

    $host = getenv('DB_HOST') ?: 'db';
    $port = getenv('DB_PORT') ?: '3306';
    $name = getenv('MYSQL_DATABASE') ?: 'infosecrelax';
    $user = getenv('MYSQL_USER') ?: 'infosecrelax_user';
    $pass = getenv('MYSQL_PASSWORD') ?: 'infosecrelax_password';

    $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    // Namerno posebna konekcija, ne getDbConnection() - MYSQL_ATTR_MULTI_STATEMENTS
    // ostaje ograničen na ovu funkciju umesto da postane podrazumevano
    // ponašanje za ceo PDO sloj aplikacije.
    $importPdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE                => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
    ]);

    $importPdo->exec($sql);
}
