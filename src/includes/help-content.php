<?php
/**
 * src/includes/help-content.php
 *
 * Deljena logika za čitanje i čuvanje sadržaja pomoći (help_content
 * tabela) - jedan red po stranici (page_slug), nezavisno od
 * organization_id jer je isti tekst za sve organizacije (objašnjava
 * standard, ne podatke konkretne firme - zato ova tabela nije deo
 * demo-data.sql niti se briše pri uvozu demo podataka).
 *
 * Sadržaj se čuva i prikazuje kao sirov HTML (ne kao čist tekst) -
 * svesna odluka da bi pomoć mogla da sadrži liste, naslove i linkove
 * ka spoljnim izvorima. Isti nivo poverenja kao i za sav ostali
 * sadržaj u aplikaciji - nema autentifikacije, pa ko god uređuje
 * pomoć već ima pristup svemu drugom.
 */

declare(strict_types=1);

function getHelpContent(PDO $pdo, string $pageSlug): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM help_content WHERE page_slug = :slug');
    $stmt->execute(['slug' => $pageSlug]);
    $row = $stmt->fetch();

    return $row === false ? null : $row;
}

function saveHelpContent(PDO $pdo, string $pageSlug, string $title, string $body): void
{
    $pdo->prepare(
        'INSERT INTO help_content (page_slug, title, body)
         VALUES (:slug, :title, :body)
         ON DUPLICATE KEY UPDATE title = VALUES(title), body = VALUES(body)'
    )->execute([
        'slug'  => $pageSlug,
        'title' => $title,
        'body'  => $body,
    ]);
}
