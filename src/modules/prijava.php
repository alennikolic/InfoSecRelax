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

    <div class="demo-hint">
        <p><strong>Brzo testiranje?</strong> Prijavi se demo nalogom:</p>
        <p class="demo-hint-row">
            <span>Email: <code id="demo-hint-email">demo@demo.local</code></span>
            <button type="button" class="demo-hint-copy" onclick="copyDemoHintValue('demo-hint-email', this)">Kopiraj</button>
        </p>
        <p class="demo-hint-row">
            <span>Lozinka: <code id="demo-hint-password">AiSSPhTjXRFZox6eXZfH</code></span>
            <button type="button" class="demo-hint-copy" onclick="copyDemoHintValue('demo-hint-password', this)">Kopiraj</button>
        </p>
    </div>

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

<script>
    /**
     * Kopira sadržaj <code> elementa (demo email/lozinka) u klipbord i
     * privremeno menja tekst dugmeta u "Kopirano!" radi potvrde. Prvo
     * pokušava Clipboard API (radi na localhost i preko HTTPS-a bez
     * dodatnih dozvola); ako nije dostupan (stariji brauzer, ne-bezbedan
     * kontekst), pada nazad na document.execCommand('copy') preko
     * privremenog, nevidljivog textarea elementa.
     */
    function copyDemoHintValue(elementId, buttonEl) {
        var text = document.getElementById(elementId).textContent;
        var resetLabel = buttonEl.textContent;

        function showCopied() {
            buttonEl.textContent = 'Kopirano!';
            setTimeout(function () {
                buttonEl.textContent = resetLabel;
            }, 1500);
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(showCopied);
            return;
        }

        var tempInput = document.createElement('textarea');
        tempInput.value = text;
        tempInput.style.position = 'fixed';
        tempInput.style.opacity = '0';
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        showCopied();
    }
</script>
