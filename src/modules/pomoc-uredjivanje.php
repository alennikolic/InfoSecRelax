<?php
/**
 * src/modules/pomoc-uredjivanje.php
 *
 * Centralno uređivanje sadržaja pomoći za sve stranice. Zamenjuje
 * uređivanje "na licu mesta" iz help-modal.php (taj modal je sad samo
 * prikaz) - jedno mesto za sav sadržaj, umesto da se ponavlja isti
 * mehanizam po 28 stranica.
 *
 * Isti list/edit obrazac preko GET parametra kao
 * izjava-primenljivosti.php: ?page=pomoc-uredjivanje je spisak svih
 * stranica iz menija, ?page=pomoc-uredjivanje&slug=kontekst je
 * uređivanje jedne. Čita config/menu.php direktno, isto kao
 * pregled-sistema.php, da spisak uvek prati stvarni meni bez ručnog
 * dupliranja.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

$menu = require __DIR__ . '/../config/menu.php';

$requestedSlug = isset($_GET['slug'])
    ? preg_replace('/[^a-z0-9\-]/', '', (string) $_GET['slug'])
    : null;

// --- Čuvanje sadržaja pomoći ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_help') {
    $slug      = preg_replace('/[^a-z0-9\-]/', '', (string) ($_POST['slug'] ?? ''));
    $helpTitle = trim($_POST['help_title'] ?? '');
    $helpBody  = trim($_POST['help_body'] ?? '');

    if ($slug === '') {
        $errors[] = 'Nepoznata stranica.';
    }
    if ($helpTitle === '') {
        $errors[] = 'Naslov je obavezan.';
    }

    if (empty($errors)) {
        saveHelpContent($pdo, $slug, $helpTitle, $helpBody);

        header('Location: ?page=pomoc-uredjivanje&slug=' . urlencode($slug));
        exit;
    }
}

// --- Da li se uređuje konkretna stranica (mora postojati u meniju) ---
$editingItem = null;
if ($requestedSlug !== null && $requestedSlug !== '') {
    foreach ($menu as $item) {
        if ($item['slug'] === $requestedSlug) {
            $editingItem = $item;
            break;
        }
    }
}

if ($editingItem !== null) {
    $helpContent = getHelpContent($pdo, $editingItem['slug']);
} else {
    // --- Spisak svih stranica sa statusom da li već imaju pomoć ---
    $helpStmt = $pdo->query('SELECT page_slug, updated_at FROM help_content');
    $existingHelp = [];
    foreach ($helpStmt->fetchAll() as $row) {
        $existingHelp[$row['page_slug']] = $row['updated_at'];
    }

    $manageableItems = array_filter($menu, fn(array $item): bool => $item['slug'] !== 'pomoc-uredjivanje');
}
?>

<?php if ($editingItem !== null): ?>

    <a class="back-link" href="?page=pomoc-uredjivanje">← Nazad na spisak</a>

    <div class="scope-current">
        <div class="card-header-row">
            <span class="card-title"><?= htmlspecialchars($editingItem['title']) ?></span>
        </div>
        <p class="item-meta">?page=<?= htmlspecialchars($editingItem['slug']) ?></p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" class="factor-form">
        <input type="hidden" name="action" value="save_help">
        <input type="hidden" name="slug" value="<?= htmlspecialchars($editingItem['slug']) ?>">

        <div class="form-row">
            <label for="help_title">Naslov</label>
            <input type="text" name="help_title" id="help_title" required
                value="<?= htmlspecialchars($helpContent['title'] ?? ('Pomoć — ' . $editingItem['title'])) ?>">
        </div>

        <div class="form-row">
            <label for="help_body">Sadržaj (HTML - &lt;p&gt;, &lt;h4&gt;, &lt;ul&gt;/&lt;li&gt;, &lt;a href&gt;...)</label>
            <textarea name="help_body" id="help_body" rows="18"><?= htmlspecialchars($helpContent['body'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="btn-primary">Sačuvaj pomoć</button>
    </form>

    <?php if (!empty($helpContent['body'])): ?>
        <h3 class="section-heading">Pregled</h3>
        <div class="factor-card">
            <div class="help-content"><?= $helpContent['body'] ?></div>
        </div>
    <?php endif; ?>

<?php else: ?>

    <p class="module-intro">
        Centralno mesto za uređivanje sadržaja pomoći koji se prikazuje na svakoj
        stranici. Klikni na stranicu da uneseš ili izmeniš njen tekst.
    </p>

    <table class="soa-table">
        <thead>
            <tr>
                <th>Stranica</th>
                <th>Pomoć uneta</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($manageableItems as $item): ?>
                <tr>
                    <td>
                        <a class="soa-edit-link" href="?page=pomoc-uredjivanje&slug=<?= htmlspecialchars($item['slug']) ?>">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                    </td>
                    <td>
                        <?php if (isset($existingHelp[$item['slug']])): ?>
                            <span class="status-badge is-positive">Uneto — <?= htmlspecialchars(substr((string) $existingHelp[$item['slug']], 0, 10)) ?></span>
                        <?php else: ?>
                            <span class="status-badge is-neutral">Nije uneto</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

<?php endif; ?>
