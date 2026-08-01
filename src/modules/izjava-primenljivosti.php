<?php
/**
 * src/modules/izjava-primenljivosti.php
 *
 * Klauzula 6.1.3(d) / Aneks A: Izjava o primenljivosti (Statement of
 * Applicability). Za svaku od 93 kontrole Aneksa A treba odlučiti da
 * li je primenljiva i obrazložiti zašto - u oba slučaja, uključena ili
 * isključena.
 *
 * Arhitektonski druga vrsta modula od svih dosadašnjih: statement_of_applicability
 * nije lista koja raste dodavanjem - to je tačno jedan red po (organizacija,
 * kontrola) par, uvek svih 93. Bootstrap ispod (ensureStatementOfApplicability)
 * to osigurava, analogno ensureDefaultOrganization() iz database.php.
 *
 * 93 kartice na jednoj strani bi bilo neupotrebljivo, pa je ovo prvi
 * modul sa dve "vrste" prikaza na istoj ruti, razdvojene GET parametrom:
 *
 *   ?page=izjava-primenljivosti                 -> tabelarni pregled svih kontrola
 *   ?page=izjava-primenljivosti&control=5.1     -> uređivanje jedne kontrole
 *
 * index.php i dalje samo bira ovaj fajl na osnovu ?page= - dodatni
 * &control= parametar čita se ovde, ruter se ne dira.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

// Bootstrap: osiguraj da za organizaciju postoji tačno jedan red po
// svakoj od 93 kontrole. justification je NOT NULL bez podrazumevane
// vrednosti u šemi, pa se ovde svesno startuje sa praznim stringom -
// prikaz jasno pokazuje koje kontrole još nemaju obrazloženje.
$pdo->prepare(
    "INSERT IGNORE INTO statement_of_applicability (organization_id, control_id, justification)
     SELECT :org_id, id, '' FROM annex_a_controls"
)->execute(['org_id' => $organizationId]);

$errors = [];

$themeLabels = [
    'organizacione' => 'A.5 — Organizacione kontrole',
    'ljudske'       => 'A.6 — Ljudske kontrole',
    'fizicke'       => 'A.7 — Fizičke kontrole',
    'tehnoloske'    => 'A.8 — Tehnološke kontrole',
];

$implementationLabels = [
    'nije_zapoceto'  => 'Nije započeto',
    'u_toku'         => 'U toku',
    'implementirano' => 'Implementirano',
];

$implementationTone = [
    'nije_zapoceto'  => 'is-neutral',
    'u_toku'         => 'is-warning',
    'implementirano' => 'is-positive',
];

$requestedControlRef = isset($_GET['control'])
    ? preg_replace('/[^0-9.]/', '', (string) $_GET['control'])
    : null;

// --- Čuvanje izmena za jednu kontrolu ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_soa') {
    $controlId             = (int) ($_POST['control_id'] ?? 0);
    $isApplicable          = isset($_POST['is_applicable']) ? 1 : 0;
    $justification         = trim($_POST['justification'] ?? '');
    $implementationStatus  = $_POST['implementation_status'] ?? 'nije_zapoceto';
    $linkedRiskId          = trim($_POST['linked_risk_id'] ?? '');
    $ownerId               = trim($_POST['owner_id'] ?? '');

    $validImplementationStatuses = ['nije_zapoceto', 'u_toku', 'implementirano'];

    if ($justification === '') {
        $errors[] = 'Obrazloženje je obavezno, i za uključene i za isključene kontrole.';
    }
    if (!in_array($implementationStatus, $validImplementationStatuses, true)) {
        $errors[] = 'Izaberite status implementacije.';
    }

    // Povezani rizik, ako je izabran, mora stvarno postojati u ovoj organizaciji.
    $linkedRiskIdValue = null;
    if ($linkedRiskId !== '') {
        $linkedRiskIdValue = (int) $linkedRiskId;
        $riskCheck = $pdo->prepare('SELECT id FROM risks WHERE id = :id AND organization_id = :org_id');
        $riskCheck->execute(['id' => $linkedRiskIdValue, 'org_id' => $organizationId]);

        if ($riskCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani rizik nije pronađen.';
            $linkedRiskIdValue = null;
        }
    }

    // Isto i vlasnik kontrole.
    $ownerIdValue = null;
    if ($ownerId !== '') {
        $ownerIdValue = (int) $ownerId;
        $personCheck = $pdo->prepare('SELECT id FROM personnel WHERE id = :id AND organization_id = :org_id');
        $personCheck->execute(['id' => $ownerIdValue, 'org_id' => $organizationId]);

        if ($personCheck->fetchColumn() === false) {
            $errors[] = 'Izabrani vlasnik nije pronađen.';
            $ownerIdValue = null;
        }
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'UPDATE statement_of_applicability
             SET is_applicable = :is_applicable,
                 justification = :justification,
                 implementation_status = :implementation_status,
                 linked_risk_id = :linked_risk_id,
                 owner_id = :owner_id,
                 last_reviewed_at = CURDATE()
             WHERE control_id = :control_id AND organization_id = :org_id'
        );
        $stmt->execute([
            'is_applicable'         => $isApplicable,
            'justification'         => $justification,
            'implementation_status' => $implementationStatus,
            'linked_risk_id'        => $linkedRiskIdValue,
            'owner_id'              => $ownerIdValue,
            'control_id'            => $controlId,
            'org_id'                => $organizationId,
        ]);

        header('Location: ?page=izjava-primenljivosti');
        exit;
    }
}

$control = null;
if ($requestedControlRef !== null && $requestedControlRef !== '') {
    $controlStmt = $pdo->prepare(
        'SELECT c.id, c.control_ref, c.theme, c.title,
                s.is_applicable, s.justification, s.implementation_status,
                s.linked_risk_id, s.owner_id, s.last_reviewed_at
         FROM annex_a_controls c
         INNER JOIN statement_of_applicability s
             ON s.control_id = c.id AND s.organization_id = :org_id
         WHERE c.control_ref = :control_ref'
    );
    $controlStmt->execute(['org_id' => $organizationId, 'control_ref' => $requestedControlRef]);
    $control = $controlStmt->fetch();
}

if (!empty($control)) {
    // === Podaci za formu uređivanja jedne kontrole ===
    $risksStmt = $pdo->prepare('SELECT id, title FROM risks WHERE organization_id = :org_id ORDER BY title');
    $risksStmt->execute(['org_id' => $organizationId]);
    $riskOptions = $risksStmt->fetchAll();

    $personnelStmt = $pdo->prepare(
        'SELECT id, full_name FROM personnel WHERE organization_id = :org_id AND is_active = TRUE ORDER BY full_name'
    );
    $personnelStmt->execute(['org_id' => $organizationId]);
    $activePersonnelOptions = $personnelStmt->fetchAll();
} else {
    // === Podaci za tabelarni pregled svih 93 kontrole ===
    $summaryStmt = $pdo->prepare(
        "SELECT
            SUM(implementation_status = 'implementirano') AS implemented_count,
            SUM(implementation_status = 'u_toku') AS in_progress_count,
            SUM(implementation_status = 'nije_zapoceto') AS not_started_count,
            SUM(is_applicable = 0) AS excluded_count,
            COUNT(*) AS total_count
         FROM statement_of_applicability
         WHERE organization_id = :org_id"
    );
    $summaryStmt->execute(['org_id' => $organizationId]);
    $summary = $summaryStmt->fetch();

    $controlsStmt = $pdo->prepare(
        'SELECT c.id, c.control_ref, c.theme, c.title,
                s.is_applicable, s.implementation_status
         FROM annex_a_controls c
         INNER JOIN statement_of_applicability s
             ON s.control_id = c.id AND s.organization_id = :org_id
         ORDER BY c.id'
    );
    $controlsStmt->execute(['org_id' => $organizationId]);

    $controlsByTheme = [];
    foreach ($controlsStmt->fetchAll() as $row) {
        $controlsByTheme[$row['theme']][] = $row;
    }
}
?>

<?php if (!empty($control)): ?>

    <a class="back-link" href="?page=izjava-primenljivosti">← Nazad na spisak</a>

    <div class="scope-current">
        <div class="card-header-row">
            <span class="card-title"><?= htmlspecialchars($control['control_ref']) ?> — <?= htmlspecialchars($control['title']) ?></span>
        </div>
        <p class="item-meta"><?= htmlspecialchars($themeLabels[$control['theme']] ?? $control['theme']) ?></p>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="alert alert-error">
        <?php foreach ($errors as $error): ?>
            <p><?= htmlspecialchars($error) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" class="factor-form">
        <input type="hidden" name="action" value="update_soa">
        <input type="hidden" name="control_id" value="<?= (int) $control['id'] ?>">

        <div class="form-row form-row-inline">
            <label class="checkbox-label">
                <input type="checkbox" name="is_applicable" value="1" <?= $control['is_applicable'] ? 'checked' : '' ?>>
                Kontrola je primenljiva
            </label>
        </div>

        <div class="form-row">
            <label for="justification">Obrazloženje (obavezno - i za uključene i za isključene kontrole)</label>
            <textarea name="justification" id="justification" rows="3" required
                placeholder="npr. Primenljivo je jer obrađujemo lične podatke klijenata u cloud sistemu. / Nije primenljivo jer firma nema sopstveni razvoj softvera."><?= htmlspecialchars($control['justification']) ?></textarea>
        </div>

        <div class="form-row">
            <label for="implementation_status">Status implementacije</label>
            <select name="implementation_status" id="implementation_status">
                <option value="nije_zapoceto" <?= $control['implementation_status'] === 'nije_zapoceto' ? 'selected' : '' ?>>Nije započeto</option>
                <option value="u_toku" <?= $control['implementation_status'] === 'u_toku' ? 'selected' : '' ?>>U toku</option>
                <option value="implementirano" <?= $control['implementation_status'] === 'implementirano' ? 'selected' : '' ?>>Implementirano</option>
            </select>
        </div>

        <div class="form-row">
            <label for="linked_risk_id">Povezan rizik (opciono)</label>
            <select name="linked_risk_id" id="linked_risk_id">
                <option value="">Nije povezano</option>
                <?php foreach ($riskOptions as $option): ?>
                    <option value="<?= (int) $option['id'] ?>" <?= (int) $control['linked_risk_id'] === (int) $option['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($option['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (empty($riskOptions)): ?>
                <p class="item-meta">Nema unetih rizika - opciono ih dodaj na stranici "Procena rizika".</p>
            <?php endif; ?>
        </div>

        <div class="form-row">
            <label for="owner_id">Vlasnik kontrole (opciono)</label>
            <select name="owner_id" id="owner_id">
                <option value="">Nije dodeljen</option>
                <?php foreach ($activePersonnelOptions as $option): ?>
                    <option value="<?= (int) $option['id'] ?>" <?= (int) $control['owner_id'] === (int) $option['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($option['full_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if (!empty($control['last_reviewed_at'])): ?>
            <p class="item-meta">Poslednji pregled: <?= htmlspecialchars($control['last_reviewed_at']) ?> (ažurira se automatski pri svakom čuvanju)</p>
        <?php endif; ?>

        <button type="submit" class="btn-primary">Sačuvaj</button>
    </form>

<?php else: ?>

    <p class="module-intro">
        Klauzula 6.1.3(d) traži da se za svaku kontrolu iz Aneksa A odluči da li
        je primenljiva i obrazloži zašto - u oba slučaja. Klikni "Uredi" na
        kontroli da uneseš odluku.
    </p>

    <div class="soa-summary">
        <div class="soa-summary-stat">
            <span class="stat-value"><?= (int) $summary['implemented_count'] ?> / <?= (int) $summary['total_count'] ?></span>
            <span class="stat-label">Implementirano</span>
        </div>
        <div class="soa-summary-stat">
            <span class="stat-value"><?= (int) $summary['in_progress_count'] ?></span>
            <span class="stat-label">U toku</span>
        </div>
        <div class="soa-summary-stat">
            <span class="stat-value"><?= (int) $summary['not_started_count'] ?></span>
            <span class="stat-label">Nije započeto</span>
        </div>
        <div class="soa-summary-stat">
            <span class="stat-value"><?= (int) $summary['excluded_count'] ?></span>
            <span class="stat-label">Isključeno</span>
        </div>
    </div>

    <?php foreach ($themeLabels as $themeKey => $themeLabel): ?>
        <?php if (!empty($controlsByTheme[$themeKey])): ?>
            <h3 class="section-heading"><?= htmlspecialchars($themeLabel) ?></h3>
            <table class="soa-table">
                <thead>
                    <tr>
                        <th>Kontrola</th>
                        <th>Naziv</th>
                        <th>Primenljivo</th>
                        <th>Status implementacije</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($controlsByTheme[$themeKey] as $control): ?>
                        <tr>
                            <td class="soa-control-ref"><?= htmlspecialchars($control['control_ref']) ?></td>
                            <td><?= htmlspecialchars($control['title']) ?></td>
                            <td>
                                <span class="status-badge <?= $control['is_applicable'] ? 'is-positive' : 'is-neutral' ?>">
                                    <?= $control['is_applicable'] ? 'Da' : 'Ne' ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge <?= htmlspecialchars($implementationTone[$control['implementation_status']] ?? 'is-neutral') ?>">
                                    <?= htmlspecialchars($implementationLabels[$control['implementation_status']] ?? $control['implementation_status']) ?>
                                </span>
                            </td>
                            <td>
                                <a class="soa-edit-link" href="?page=izjava-primenljivosti&control=<?= urlencode($control['control_ref']) ?>">Uredi</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endforeach; ?>

<?php endif; ?>
