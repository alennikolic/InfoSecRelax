<?php
/**
 * src/modules/pregled-sistema.php
 *
 * Klauzula 4.4: Sistem upravljanja bezbednošću informacija - ISMS kao
 * celina, ne kao skup nepovezanih dokumenata. Ovo je početna stranica
 * aplikacije (prva u meniju, van grupa - videti config/menu.php).
 *
 * Za razliku od unapredjenje.php (koji sažima samo dokaze unapređenja),
 * ovo je pravi landing dashboard: čita config/menu.php direktno i
 * pravi pregled svih stavki, grupisanih istim redosledom kao bočna
 * navigacija, sa brojem unosa po modulu. Namerno čita menu.php umesto
 * da duplira spisak stavki ovde - ako se meni izmeni (nova stavka,
 * novi redosled), ova stranica automatski prati promenu.
 *
 * Read-only - bez formi, bez upisa u bazu.
 *
 * IZMENA (RBAC/multi-tenant): dugme "Uvezi demo podatke" (i poziv na
 * includes/demo-import.php) je UKLONJENO odavde. db/demo-data.sql radi
 * TRUNCATE na desetine tabela BEZ filtera po organizaciji - u
 * multi-tenant okruženju bi klik iz bilo koje organizacije obrisao
 * podatke SVIH organizacija na instalaciji. Isti demo skup se sada
 * unosi AUTOMATSKI i JEDNOM, isključivo za organization_id = 1, kao
 * deo db/init.sql (pri svakoj svežoj instalaciji) - videti komentar
 * na vrhu db/demo-data.sql i dogovor iz razgovora o ovoj izmeni.
 * includes/demo-import.php ostaje u projektu neiskorišćen (može se
 * obrisati kasnije) - namerno nije brisan ovom izmenom, da se ne diraju
 * fajlovi van onoga što je zatraženo.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

// --- Broj unosa po modulu - jedan upit po tabeli, mapiran na slug iz menija ---
$countQueries = [
    'kontekst'              => 'SELECT COUNT(*) FROM context_factors WHERE organization_id = :org_id',
    'zainteresovane-strane' => 'SELECT COUNT(*) FROM interested_parties WHERE organization_id = :org_id',
    'politike'               => 'SELECT COUNT(*) FROM policies WHERE organization_id = :org_id',
    'zaposleni'               => 'SELECT COUNT(*) FROM personnel WHERE organization_id = :org_id AND is_active = TRUE',
    'uloge'                   => 'SELECT COUNT(*) FROM roles_responsibilities WHERE organization_id = :org_id',
    'sredstva'                 => 'SELECT COUNT(*) FROM assets WHERE organization_id = :org_id',
    'procena-rizika'           => 'SELECT COUNT(*) FROM risks WHERE organization_id = :org_id',
    'ciljevi'                  => 'SELECT COUNT(*) FROM objectives WHERE organization_id = :org_id',
    'promene'                  => 'SELECT COUNT(*) FROM planned_changes WHERE organization_id = :org_id',
    'kompetentnost'             => 'SELECT COUNT(*) FROM competence_records WHERE organization_id = :org_id',
    'komunikacija'               => 'SELECT COUNT(*) FROM communications_plan WHERE organization_id = :org_id',
    'dokumenti'                  => 'SELECT COUNT(*) FROM documents WHERE organization_id = :org_id',
    'sistemi-pristup'             => 'SELECT COUNT(*) FROM systems WHERE organization_id = :org_id',
    'dobavljaci'                  => 'SELECT COUNT(*) FROM suppliers WHERE organization_id = :org_id',
    'fizicka-bezbednost'           => 'SELECT COUNT(*) FROM physical_locations WHERE organization_id = :org_id',
    'incidenti'                    => 'SELECT COUNT(*) FROM security_events WHERE organization_id = :org_id',
    'pokazatelji'                   => 'SELECT COUNT(*) FROM metrics WHERE organization_id = :org_id',
    'interni-audit'                  => 'SELECT COUNT(*) FROM internal_audits WHERE organization_id = :org_id',
    'pregled-menadzmenta'             => 'SELECT COUNT(*) FROM management_reviews WHERE organization_id = :org_id',
    'korektivne-mere'                  => 'SELECT COUNT(*) FROM corrective_actions WHERE organization_id = :org_id',
];

$moduleCounts = [];
foreach ($countQueries as $slug => $query) {
    $stmt = $pdo->prepare($query);
    $stmt->execute(['org_id' => $organizationId]);
    $moduleCounts[$slug] = (int) $stmt->fetchColumn();
}

// --- obim: nema prost "broj unosa", nego da li trenutna verzija postoji ---
$scopeStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM scope_statements WHERE organization_id = :org_id AND is_current = TRUE'
);
$scopeStmt->execute(['org_id' => $organizationId]);
$hasCurrentScope = ((int) $scopeStmt->fetchColumn()) > 0;

// --- izjava-primenljivosti: koliko od (do sada pokrenutih) kontrola ima obrazloženje ---
$soaStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total, SUM(justification != '') AS filled
     FROM statement_of_applicability WHERE organization_id = :org_id"
);
$soaStmt->execute(['org_id' => $organizationId]);
$soaStats = $soaStmt->fetch();
$soaTotal = (int) ($soaStats['total'] ?? 0);
$soaFilled = (int) ($soaStats['filled'] ?? 0);

// --- Rizici po nivou (za istaknute brojke na vrhu) ---
$riskStatsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total, SUM(risk_level = 'visok') AS high
     FROM risks WHERE organization_id = :org_id"
);
$riskStatsStmt->execute(['org_id' => $organizationId]);
$riskStats = $riskStatsStmt->fetch();

// --- Otvoreni incidenti (za istaknute brojke na vrhu) ---
$openIncidentsStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM security_events WHERE organization_id = :org_id AND closed_at IS NULL'
);
$openIncidentsStmt->execute(['org_id' => $organizationId]);
$openIncidentsCount = (int) $openIncidentsStmt->fetchColumn();

// --- Napomene za module koji nemaju prost "broj unosa" ---
$moduleNotes = [
    'obim' => $hasCurrentScope ? 'Obim definisan' : 'Obim još nije definisan',
    'izjava-primenljivosti' => $soaTotal > 0
        ? $soaFilled . ' / ' . $soaTotal . ' kontrola obrazloženo'
        : 'Još nije pokrenuto',
    'unapredjenje' => 'Sažetak dokaza unapređenja (bez sopstvenih unosa)',
];

// --- Grupisanje stavki menija istim redosledom kao bočna navigacija ---
$menu = require __DIR__ . '/../config/menu.php';

$groupedMenu = [];
foreach ($menu as $item) {
    if ($item['slug'] === 'pregled-sistema') {
        continue;
    }
    $groupedMenu[$item['group']][] = $item;
}
?>

<div class="soa-summary">
    <div class="soa-summary-stat">
        <span class="stat-value"><?= (int) ($moduleCounts['zaposleni'] ?? 0) ?></span>
        <span class="stat-label">Aktivnih zaposlenih</span>
    </div>
    <div class="soa-summary-stat">
        <span class="stat-value"><?= (int) ($riskStats['total'] ?? 0) ?></span>
        <span class="stat-label">Identifikovanih rizika</span>
    </div>
    <div class="soa-summary-stat">
        <span class="stat-value"><?= (int) ($riskStats['high'] ?? 0) ?></span>
        <span class="stat-label">Visokih rizika</span>
    </div>
    <div class="soa-summary-stat">
        <span class="stat-value"><?= $openIncidentsCount ?></span>
        <span class="stat-label">Otvorenih incidenata</span>
    </div>
    <div class="soa-summary-stat">
        <span class="stat-value"><?= $soaTotal > 0 ? $soaFilled . '/' . $soaTotal : '—' ?></span>
        <span class="stat-label">SoA kontrola obrazloženo</span>
    </div>
</div>

<?php foreach ($groupedMenu as $groupName => $items): ?>
    <h3 class="section-heading"><?= htmlspecialchars($groupName ?? 'Ostalo') ?></h3>
    <table class="soa-table dashboard-table">
        <thead>
            <tr>
                <th>Modul</th>
                <th>Broj unosa</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td>
                        <a class="soa-edit-link" href="?page=<?= htmlspecialchars($item['slug']) ?>">
                            <?= htmlspecialchars($item['title']) ?>
                        </a>
                    </td>
                    <td>
                        <?php if (isset($moduleNotes[$item['slug']])): ?>
                            <?= htmlspecialchars($moduleNotes[$item['slug']]) ?>
                        <?php else: ?>
                            <?= (int) ($moduleCounts[$item['slug']] ?? 0) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>
