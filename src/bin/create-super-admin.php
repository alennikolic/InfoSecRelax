<?php
/**
 * src/bin/create-super-admin.php
 *
 * Jednokratna CLI skripta za kreiranje super admin naloga. Namerno NE
 * postoji web forma za ovo - jedini način da neko postane super admin
 * je da neko sa pristupom serveru/kontejneru pokrene ovu skriptu.
 *
 * Upotreba (iz web kontejnera):
 *
 *   docker exec -it InfoSecRelax_web php bin/create-super-admin.php \
 *       admin@firma.rs "LozinkaBar12Karaktera"
 *
 * Bezbedno se poziva ponovo za isti email - ažurira lozinku i vraća
 * postojeći nalog u super admin status, umesto da baci grešku zbog
 * duplikata.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ova skripta se pokreće isključivo iz komandne linije (CLI), ne preko web servera.');
}

require __DIR__ . '/../config/database.php';

$email    = $argv[1] ?? null;
$password = $argv[2] ?? null;

if ($email === null || $password === null) {
    fwrite(STDERR, "Upotreba: php bin/create-super-admin.php <email> <lozinka>\n");
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Email nije ispravan.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Lozinka super admina mora imati bar 12 karaktera.\n");
    exit(1);
}

$pdo  = getDbConnection();
$hash = password_hash($password, PASSWORD_DEFAULT);

$pdo->prepare(
    'INSERT INTO users (organization_id, email, password_hash, is_active, is_super_admin)
     VALUES (NULL, :email, :hash, TRUE, TRUE)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), is_super_admin = TRUE, is_active = TRUE'
)->execute(['email' => $email, 'hash' => $hash]);

echo "Super admin nalog spreman: {$email}\n";
