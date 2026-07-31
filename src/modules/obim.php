<?php
/**
 * src/modules/obim.php
 *
 * Klauzula 4.3: Utvrđivanje obima sistema upravljanja bezbednošću
 * informacija (ISMS).
 *
 * Za razliku od kontekst.php i zainteresovane-strane.php, ovde se
 * postojeći red ne briše niti menja - scope_statements čuva istoriju
 * verzija. Dodavanje nove verzije samo obeleži prethodnu kao
 * is_current = FALSE i upiše novi red kao is_current = TRUE (u jednoj
 * transakciji). Izuzeci (scope_exclusions) i zavisnosti od trećih
 * strana (third_party_dependencies) vezani su za konkretnu verziju
 * (scope_statement_id), pa svaka verzija čuva svoj tadašnji snimak -
 * to je namerno, to je sam trag audita kroz vreme, ne greška.
 *
 * approved_by (FK ka personnel) namerno nije u formi - modul za
 * zaposlene (personnel) još ne postoji, pa nema iz čega da se bira.
 * Kad taj modul bude gotov, ovde treba dodati select za odobravanje.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$errors = [];

// --- Dodavanje nove verzije obima (prethodna verzija postaje istorija) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_version') {
    $scopeText     = trim($_POST['scope_text'] ?? '');
    $version       = trim($_POST['version'] ?? '');
    $effectiveFrom = trim($_POST['effective_from'] ?? '');
    $approvedAt    = trim($_POST['approved_at'] ?? '');

    if ($scopeText === '') {
        $errors[] = 'Tekst obima je obavezan.';
    }
    if ($version === '') {
        $errors[] = 'Oznaka verzije je obavezna.';
    }

    if (empty($errors)) {
        $pdo->beginTransaction();

        $pdo->prepare(
            'UPDATE scope_statements SET is_current = FALSE
             WHERE organization_id = :org_id AND is_current = TRUE'
        )->execute(['org_id' => $organizationId]);

        $stmt = $pdo->prepare(
            'INSERT INTO scope_statements
                (organization_id, scope_text, version, effective_from, approved_at, is_current)
             VALUES (:org_id, :scope_text, :version, :effective_from, :approved_at, TRUE)'
        );
        $stmt->execute([
            'org_id'         => $organizationId,
            'scope_text'     => $scopeText,
            'version'        => $version,
            'effective_from' => $effectiveFrom !== '' ? $effectiveFrom : null,
            'approved_at'    => $approvedAt !== '' ? $approvedAt : null,
        ]);

        $pdo->commit();

        header('Location: ?page=obim');
        exit;
    }
}

// --- Dodavanje izuzetka trenutnoj (aktuelnoj) verziji ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_exclusion') {
    $scopeStatementId = (int) ($_POST['scope_statement_id'] ?? 0);
    $excludedItem     = trim($_POST['excluded_item'] ?? '');
    $justification    = trim($_POST['justification'] ?? '');

    $scopeCheck = $pdo->prepare(
        'SELECT id FROM scope_statements WHERE id = :id AND organization_id = :org_id AND is_current = TRUE'
    );
    $scopeCheck->execute(['id' => $scopeStatementId, 'org_id' => $organizationId]);

    if ($scopeCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznata ili zastarela verzija obima.';
    }
    if ($excludedItem === '') {
        $errors[] = 'Naziv izuzete stavke je obavezan.';
    }
    if ($justification === '') {
        $errors[] = 'Obrazloženje izuzeća je obavezno.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO scope_exclusions (scope_statement_id, excluded_item, justification)
             VALUES (:scope_id, :excluded_item, :justification)'
        );
        $stmt->execute([
            'scope_id'      => $scopeStatementId,
            'excluded_item' => $excludedItem,
            'justification' => $justification,
        ]);

        header('Location: ?page=obim');
        exit;
    }
}

// --- Brisanje izuzetka ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_exclusion') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE e FROM scope_exclusions e
         INNER JOIN scope_statements s ON s.id = e.scope_statement_id
         WHERE e.id = :id AND s.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=obim');
    exit;
}

// --- Dodavanje zavisnosti od treće strane trenutnoj verziji ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_dependency') {
    $scopeStatementId = (int) ($_POST['scope_statement_id'] ?? 0);
    $description      = trim($_POST['description'] ?? '');
    $managedVia       = trim($_POST['managed_via'] ?? '');

    $scopeCheck = $pdo->prepare(
        'SELECT id FROM scope_statements WHERE id = :id AND organization_id = :org_id AND is_current = TRUE'
    );
    $scopeCheck->execute(['id' => $scopeStatementId, 'org_id' => $organizationId]);

    if ($scopeCheck->fetchColumn() === false) {
        $errors[] = 'Nepoznata ili zastarela verzija obima.';
    }
    if ($description === '') {
        $errors[] = 'Opis zavisnosti je obavezan.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO third_party_dependencies (scope_statement_id, description, managed_via)
             VALUES (:scope_id, :description, :managed_via)'
        );
        $stmt->execute([
            'scope_id'    => $scopeStatementId,
            'description' => $description,
            'managed_via' => $managedVia !== '' ? $managedVia : null,
        ]);

        header('Location: ?page=obim');
        exit;
    }
}

// --- Brisanje zavisnosti ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_dependency') {
    $id = (int) ($_POST['id'] ?? 0);

    $stmt = $pdo->prepare(
        'DELETE d FROM third_party_dependencies d
         INNER JOIN scope_statements s ON s.id = d.scope_statement_id
         WHERE d.id = :id AND s.organization_id = :org_id'
    );
    $stmt->execute(['id' => $id, 'org_id' => $organizationId]);

    header('Location: ?page=obim');
    exit;
}

// --- Učitavanje trenutne (aktuelne) verzije obima ---
$currentStmt = $pdo->prepare(
    'SELECT * FROM scope_statements WHERE organization_id = :org_id AND is_current = TRUE LIMIT 1'
);
$currentStmt->execute(['org_id' => $organizationId]);
$currentScope = $currentStmt->fetch();

$exclusions = [];
$dependencies = [];

if ($currentScope !== false) {
    $exclusionsStmt = $pdo->prepare(
        'SELECT * FROM scope_exclusions WHERE scope_statement_id = :scope_id ORDER BY id'
    );
    $exclusionsStmt->execute(['scope_id' => $currentScope['id']]);
    $exclusions = $exclusionsStmt->fetchAll();

    $dependenciesStmt = $pdo->prepare(
        'SELECT * FROM third_party_dependencies WHERE scope_statement_id = :scope_id ORDER BY id'
    );
    $dependenciesStmt->execute(['scope_id' => $currentScope['id']]);
    $dependencies = $dependenciesStmt->fetchAll();
}

// --- Istorija ranijih verzija ---
$historyStmt = $pdo->prepare(
    'SELECT * FROM scope_statements
     WHERE organization_id = :org_id AND is_current = FALSE
     ORDER BY created_at DESC'
);
$historyStmt->execute(['org_id' => $organizationId]);
$scopeHistory = $historyStmt->fetchAll();
?>

<p class="module-intro">
    Klauzula 4.3 traži da na osnovu konteksta (4.1) i zahteva zainteresovanih
    strana (4.2) odredite obim ISMS-a - koji delovi organizacije, lokacije i
    sistemi su unutar njega, a koji su izričito isključeni i zašto.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($currentScope === false): ?>

    <p class="empty-state">Obim ISMS-a još uvek nije definisan.</p>

<?php else: ?>

    <div class="scope-current">
        <div class="scope-current-header">
            <span class="scope-version-badge">Verzija <?= htmlspecialchars($currentScope['version']) ?></span>
            <?php if (!empty($currentScope['effective_from'])): ?>
                <span class="scope-meta">na snazi od <?= htmlspecialchars($currentScope['effective_from']) ?></span>
            <?php endif; ?>
            <?php if (!empty($currentScope['approved_at'])): ?>
                <span class="scope-meta">odobreno <?= htmlspecialchars($currentScope['approved_at']) ?></span>
            <?php endif; ?>
        </div>
        <p class="scope-text"><?= nl2br(htmlspecialchars($currentScope['scope_text'])) ?></p>
    </div>

    <div class="factor-columns">
        <div class="factor-column">
            <h3>Izuzeci iz obima (<?= count($exclusions) ?>)</h3>
            <?php if (empty($exclusions)): ?>
                <p class="empty-state">Nema unetih izuzeća.</p>
            <?php else: ?>
                <?php foreach ($exclusions as $exclusion): ?>
                    <div class="factor-card">
                        <p class="item-title"><?= htmlspecialchars($exclusion['excluded_item']) ?></p>
                        <p><?= nl2br(htmlspecialchars($exclusion['justification'])) ?></p>
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj izuzetak?');">
                            <input type="hidden" name="action" value="delete_exclusion">
                            <input type="hidden" name="id" value="<?= (int) $exclusion['id'] ?>">
                            <button type="submit" class="btn-delete">Obriši</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="post" class="subform">
                <input type="hidden" name="action" value="add_exclusion">
                <input type="hidden" name="scope_statement_id" value="<?= (int) $currentScope['id'] ?>">

                <div class="form-row">
                    <label for="excluded_item">Izuzeta stavka</label>
                    <input type="text" name="excluded_item" id="excluded_item" required
                        placeholder="npr. Ogranak u Novom Sadu">
                </div>
                <div class="form-row">
                    <label for="justification">Obrazloženje</label>
                    <textarea name="justification" id="justification" rows="2" required
                        placeholder="npr. Ogranak ne obrađuje podatke klijenata i nema pristup produkcionim sistemima."></textarea>
                </div>
                <button type="submit" class="btn-secondary">Dodaj izuzetak</button>
            </form>
        </div>

        <div class="factor-column">
            <h3>Zavisnosti od trećih strana (<?= count($dependencies) ?>)</h3>
            <?php if (empty($dependencies)): ?>
                <p class="empty-state">Nema unetih zavisnosti.</p>
            <?php else: ?>
                <?php foreach ($dependencies as $dependency): ?>
                    <div class="factor-card">
                        <p class="item-title"><?= htmlspecialchars($dependency['description']) ?></p>
                        <?php if (!empty($dependency['managed_via'])): ?>
                            <p>Uređeno preko: <?= htmlspecialchars($dependency['managed_via']) ?></p>
                        <?php endif; ?>
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovu zavisnost?');">
                            <input type="hidden" name="action" value="delete_dependency">
                            <input type="hidden" name="id" value="<?= (int) $dependency['id'] ?>">
                            <button type="submit" class="btn-delete">Obriši</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <form method="post" class="subform">
                <input type="hidden" name="action" value="add_dependency">
                <input type="hidden" name="scope_statement_id" value="<?= (int) $currentScope['id'] ?>">

                <div class="form-row">
                    <label for="description">Opis zavisnosti</label>
                    <textarea name="description" id="description" rows="2" required
                        placeholder="npr. Hosting produkcionih servera kod eksternog dobavljača cloud usluga."></textarea>
                </div>
                <div class="form-row">
                    <label for="managed_via">Uređeno preko (opciono)</label>
                    <input type="text" name="managed_via" id="managed_via" placeholder="npr. Ugovor o nivou usluge (SLA)">
                </div>
                <button type="submit" class="btn-secondary">Dodaj zavisnost</button>
            </form>
        </div>
    </div>

<?php endif; ?>

<div class="scope-new-version">
    <h3><?= $currentScope === false ? 'Definiši obim' : 'Nova verzija obima' ?></h3>
    <?php if ($currentScope !== false): ?>
        <p class="module-intro">
            Čuvanje nove verzije ne briše prethodnu - ona ostaje u istoriji ispod,
            zajedno sa izuzecima i zavisnostima kakvi su bili važeći u tom trenutku.
        </p>
    <?php endif; ?>

    <form method="post" class="factor-form">
        <input type="hidden" name="action" value="add_version">

        <div class="form-row">
            <label for="version">Oznaka verzije</label>
            <input type="text" name="version" id="version" required
                value="<?= $currentScope === false ? '1.0' : '' ?>"
                placeholder="npr. 1.0, 1.1, 2.0">
        </div>

        <div class="form-row">
            <label for="scope_text">Tekst obima</label>
            <textarea name="scope_text" id="scope_text" rows="4" required
                placeholder="npr. ISMS obuhvata sve informacione sisteme, osoblje i procese koji podržavaju pružanje usluga klijentima firme, u kancelariji u Beogradu."><?= $currentScope !== false ? htmlspecialchars($currentScope['scope_text']) : '' ?></textarea>
        </div>

        <div class="form-row">
            <label for="effective_from">Na snazi od (opciono)</label>
            <input type="date" name="effective_from" id="effective_from">
        </div>

        <div class="form-row">
            <label for="approved_at">Datum odobrenja (opciono)</label>
            <input type="date" name="approved_at" id="approved_at">
        </div>

        <button type="submit" class="btn-primary">
            <?= $currentScope === false ? 'Sačuvaj obim' : 'Sačuvaj kao novu verziju' ?>
        </button>
    </form>
</div>

<?php if (!empty($scopeHistory)): ?>
<div class="scope-history">
    <h3>Istorija ranijih verzija (<?= count($scopeHistory) ?>)</h3>
    <?php foreach ($scopeHistory as $pastScope): ?>
        <div class="scope-history-item">
            <div class="scope-current-header">
                <span class="scope-version-badge scope-version-badge-muted">Verzija <?= htmlspecialchars($pastScope['version']) ?></span>
                <?php if (!empty($pastScope['effective_from'])): ?>
                    <span class="scope-meta">na snazi od <?= htmlspecialchars($pastScope['effective_from']) ?></span>
                <?php endif; ?>
            </div>
            <p class="scope-text"><?= nl2br(htmlspecialchars($pastScope['scope_text'])) ?></p>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
