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
 * transakciji). Forma za novu verziju je modal otvoren dugmetom na
 * vrhu stranice, isti obrazac kao kontekst.php/zainteresovane-strane.php.
 *
 * Izuzeci i zavisnosti od trećih strana nemaju više svoju strukturu
 * ovde - pišu se direktno u tekstu obima, prirodnim jezikom. Tabele
 * scope_exclusions i third_party_dependencies ostaju u šemi (ne brišu
 * se, da se ne izgubi eventualno već uneti sadržaj), ali ih ova
 * stranica više ne čita niti upisuje.
 *
 * approved_by (FK ka personnel) namerno nije u formi - modul za
 * zaposlene (personnel) još ne postoji, pa nema iz čega da se bira.
 * Kad taj modul bude gotov, ovde treba dodati select za odobravanje.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';
require __DIR__ . '/../includes/help-content.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

$pageSlug = 'obim';

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

// --- Učitavanje trenutne (aktuelne) verzije obima ---
$currentStmt = $pdo->prepare(
    'SELECT * FROM scope_statements WHERE organization_id = :org_id AND is_current = TRUE LIMIT 1'
);
$currentStmt->execute(['org_id' => $organizationId]);
$currentScope = $currentStmt->fetch();

// --- Istorija ranijih verzija ---
$historyStmt = $pdo->prepare(
    'SELECT * FROM scope_statements
     WHERE organization_id = :org_id AND is_current = FALSE
     ORDER BY created_at DESC'
);
$historyStmt->execute(['org_id' => $organizationId]);
$scopeHistory = $historyStmt->fetchAll();

// --- Učitavanje sadržaja pomoći za ovu stranicu ---
$helpContent = getHelpContent($pdo, $pageSlug);
?>

<?php if (!empty($errors)): ?>
<div class="alert alert-error">
    <?php foreach ($errors as $error): ?>
        <p><?= htmlspecialchars($error) ?></p>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="toolbar">
    <button type="button" class="btn-primary" onclick="openVersionModal()">
        <?= $currentScope === false ? '+ Definiši obim' : '+ Nova verzija' ?>
    </button>
    <button type="button" class="btn-secondary" onclick="openHelpModal()">Pomoć</button>
</div>

<?php if ($currentScope === false): ?>

    <p class="empty-state">Obim ISMS-a još uvek nije definisan.</p>

<?php else: ?>

    <div class="scope-current">
        <div class="scope-current-header">
            <span class="scope-version-badge">Verzija <?= htmlspecialchars($currentScope['version']) ?></span>
            <?php if (!empty($currentScope['effective_from'])): ?>
                <span class="item-meta">na snazi od <?= htmlspecialchars($currentScope['effective_from']) ?></span>
            <?php endif; ?>
            <?php if (!empty($currentScope['approved_at'])): ?>
                <span class="item-meta">odobreno <?= htmlspecialchars($currentScope['approved_at']) ?></span>
            <?php endif; ?>
        </div>
        <p class="scope-text"><?= nl2br(htmlspecialchars($currentScope['scope_text'])) ?></p>
    </div>

<?php endif; ?>

<?php if (!empty($scopeHistory)): ?>
<div class="scope-history">
    <h3>Istorija ranijih verzija (<?= count($scopeHistory) ?>)</h3>
    <?php foreach ($scopeHistory as $pastScope): ?>
        <div class="scope-history-item">
            <div class="scope-current-header">
                <span class="scope-version-badge scope-version-badge-muted">Verzija <?= htmlspecialchars($pastScope['version']) ?></span>
                <?php if (!empty($pastScope['effective_from'])): ?>
                    <span class="item-meta">na snazi od <?= htmlspecialchars($pastScope['effective_from']) ?></span>
                <?php endif; ?>
            </div>
            <p class="scope-text"><?= nl2br(htmlspecialchars($pastScope['scope_text'])) ?></p>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="modal-overlay" id="version-modal-overlay" onclick="closeVersionModal()">
    <div class="modal-box" onclick="event.stopPropagation()">
        <div class="modal-header">
            <span class="modal-title"><?= $currentScope === false ? 'Definiši obim' : 'Nova verzija obima' ?></span>
            <button type="button" class="modal-close" onclick="closeVersionModal()" aria-label="Zatvori">&times;</button>
        </div>

        <?php if ($currentScope !== false): ?>
            <p class="item-meta">Čuvanje ne briše prethodnu verziju - ona ostaje u istoriji ispod.</p>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="action" value="add_version">

            <div class="form-row">
                <label for="version">Oznaka verzije</label>
                <input type="text" name="version" id="version" required
                    value="<?= $currentScope === false ? '1.0' : '' ?>"
                    placeholder="npr. 1.0, 1.1, 2.0">
            </div>

            <div class="form-row">
                <label for="scope_text">Tekst obima</label>
                <textarea name="scope_text" id="scope_text" rows="6" required
                    placeholder="npr. ISMS obuhvata sve informacione sisteme, osoblje i procese u kancelariji u Beogradu. Van obima je ogranak u Novom Sadu, jer ne obrađuje podatke klijenata. Hosting produkcionih servera je kod eksternog cloud dobavljača, uređeno ugovorom o nivou usluge."><?= $currentScope !== false ? htmlspecialchars($currentScope['scope_text']) : '' ?></textarea>
            </div>

            <div class="form-row">
                <label for="effective_from">Na snazi od (opciono)</label>
                <input type="date" name="effective_from" id="effective_from">
            </div>

            <div class="form-row">
                <label for="approved_at">Datum odobrenja (opciono)</label>
                <input type="date" name="approved_at" id="approved_at">
            </div>

            <div class="modal-actions modal-actions-split">
                <button type="button" class="btn-secondary" onclick="openHelpFromVersionModal()">Pomoć</button>
                <div class="button-group">
                    <button type="button" class="btn-secondary" onclick="closeVersionModal()">Otkaži</button>
                    <button type="submit" class="btn-primary">
                        <?= $currentScope === false ? 'Sačuvaj obim' : 'Sačuvaj kao novu verziju' ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/help-modal.php'; ?>

<script>
function openVersionModal() {
    document.getElementById('version-modal-overlay').classList.add('is-open');
}

function closeVersionModal() {
    document.getElementById('version-modal-overlay').classList.remove('is-open');
}

function openHelpFromVersionModal() {
    closeVersionModal();
    openHelpModal();
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeVersionModal();
    }
});
</script>
