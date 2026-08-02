<?php
/**
 * src/modules/prijava.php
 *
 * Prijava korisnika (i organizacionih naloga i super admina - login je
 * zajednički, index.php posle uspešne prijave rutira na odgovarajuću
 * granu na osnovu is_super_admin).
 *
 * Ovaj modul se učitava DIREKTNO iz index.php (grana "niko nije
 * ulogovan"), ne kroz standardni RBAC put - nema šta da se proveri,
 * stranica mora biti dostupna svakom.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/csrf.php';

$pdo         = getDbConnection();
$loginError  = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfRequireValid();

    $email    = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $user = ($email !== '' && $password !== '') ? loginUser($pdo, $email, $password) : null;

    if ($user === null) {
        $loginError = 'Pogrešan email ili lozinka.';
    } else {
        $destination = (bool) $user['is_super_admin'] ? 'organizacije' : 'pregled-sistema';
        header('Location: ?page=' . $destination);
        exit;
    }
}
?>

<div class="auth-form-wrap">
    <h2>Prijava na InfoSecRelax</h2>

    <?php if ($loginError !== null): ?>
    <div class="alert alert-error">
        <p><?= htmlspecialchars($loginError) ?></p>
    </div>
    <?php endif; ?>

    <form method="post" action="?page=prijava" class="auth-form">
        <?= csrfField() ?>
        <label class="form-field">
            <span>Email</span>
            <input type="email" name="email" required autofocus>
        </label>
        <label class="form-field">
            <span>Lozinka</span>
            <input type="password" name="password" required>
        </label>
        <button type="submit" class="btn-primary">Prijavi se</button>
    </form>
</div>
