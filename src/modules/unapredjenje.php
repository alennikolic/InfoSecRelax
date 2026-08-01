<?php
/**
 * src/modules/unapredjenje.php
 *
 * Klauzula 10.1: Stalno unapređenje.
 *
 * Za razliku od svih dosadašnjih modula, ovaj nema svoju tabelu u šemi -
 * Klauzula 10.1 je narativna, dokazuje se kroz ono što se već radi na
 * drugim mestima (ciljevi, tretman rizika, nalazi audita, učenje iz
 * incidenata, radnje iz pregleda menadžmenta). Zato je ovo read-only
 * pregled/dashboard koji sažima podatke iz postojećih tabela - bez
 * ijedne forme, bez upisa u bazu. Nova tabela namerno nije dodata bez
 * dogovora (pravilo iz instrukcija projekta o izmenama šeme).
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

/**
 * Pretvara rezultat GROUP BY ... status upita u fiksni skup oznaka iz
 * $labels, popunjavajući nule za oznake koje se ne pojave u podacima.
 * Vraća niz sa istim ključevima kao $labels, da se u template-u mogu
 * uparivati direktno.
 */
function tallyByStatus(PDO $pdo, string $query, array $params, array $labels): array
{
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $counts = array_fill_keys(array_keys($labels), 0);
    foreach ($stmt->fetchAll() as $row) {
        if (isset($counts[$row['status']])) {
            $counts[$row['status']] = (int) $row['cnt'];
        }
    }

    return $counts;
}

// --- Ciljevi bezbednosti (Klauzula 6.2) ---
$objectiveLabels = [
    'planiran'   => 'Planiran',
    'u_toku'     => 'U toku',
    'ostvaren'   => 'Ostvaren',
    'neostvaren' => 'Neostvaren',
];
$objectiveCounts = tallyByStatus(
    $pdo,
    'SELECT status, COUNT(*) AS cnt FROM objectives WHERE organization_id = :org_id GROUP BY status',
    ['org_id' => $organizationId],
    $objectiveLabels
);

// --- Mere tretmana rizika (Klauzula 6.1.3) ---
$treatmentLabels = [
    'planirano'       => 'Planirano',
    'u_toku'          => 'U toku',
    'sprovedeno'      => 'Sprovedeno',
    'ponovo_otvoreno' => 'Ponovo otvoreno',
];
$treatmentCounts = tallyByStatus(
    $pdo,
    'SELECT rt.status, COUNT(*) AS cnt
     FROM risk_treatments rt
     INNER JOIN risks r ON r.id = rt.risk_id
     WHERE r.organization_id = :org_id
     GROUP BY rt.status',
    ['org_id' => $organizationId],
    $treatmentLabels
);

// --- Nalazi internog audita (Klauzula 9.2), po ozbiljnosti ---
$severityLabels = ['nizak' => 'Nizak', 'srednji' => 'Srednji', 'visok' => 'Visok'];
$findingCounts = tallyByStatus(
    $pdo,
    'SELECT f.severity AS status, COUNT(*) AS cnt
     FROM audit_findings f
     INNER JOIN internal_audits a ON a.id = f.internal_audit_id
     WHERE a.organization_id = :org_id
     GROUP BY f.severity',
    ['org_id' => $organizationId],
    $severityLabels
);
$totalFindings = array_sum($findingCounts);

// --- Učenje iz incidenata (A.5.27) ---
$incidentStmt = $pdo->prepare(
    'SELECT
        COUNT(*) AS total,
        SUM(closed_at IS NOT NULL) AS closed,
        SUM(closed_at IS NOT NULL AND root_cause IS NOT NULL) AS closed_with_cause
     FROM security_events
     WHERE organization_id = :org_id'
);
$incidentStmt->execute(['org_id' => $organizationId]);
$incidentStats = $incidentStmt->fetch();

// --- Radnje iz pregleda menadžmenta (Klauzula 9.3) ---
$reviewActionLabels = ['otvoreno' => 'Otvoreno', 'u_toku' => 'U toku', 'zavrseno' => 'Završeno'];
$reviewActionCounts = tallyByStatus(
    $pdo,
    'SELECT a.status, COUNT(*) AS cnt
     FROM management_review_actions a
     INNER JOIN management_reviews r ON r.id = a.management_review_id
     WHERE r.organization_id = :org_id
     GROUP BY a.status',
    ['org_id' => $organizationId],
    $reviewActionLabels
);

// --- Poslednje identifikovane prilike za unapređenje (9.3g) ---
$latestOpportunityStmt = $pdo->prepare(
    "SELECT review_date, improvement_opportunities
     FROM management_reviews
     WHERE organization_id = :org_id
       AND improvement_opportunities IS NOT NULL AND improvement_opportunities != ''
     ORDER BY review_date DESC
     LIMIT 1"
);
$latestOpportunityStmt->execute(['org_id' => $organizationId]);
$latestOpportunity = $latestOpportunityStmt->fetch();
?>

<p class="module-intro">
    Klauzula 10.1 traži stalno unapređenje ISMS-a - ova stranica nema svoju
    tabelu, nego sažima dokaze unapređenja koji se već beleže na drugim
    mestima u aplikaciji.
</p>

<h3 class="section-heading">Ciljevi bezbednosti</h3>
<div class="soa-summary">
    <?php foreach ($objectiveLabels as $key => $label): ?>
        <div class="soa-summary-stat">
            <span class="stat-value"><?= (int) $objectiveCounts[$key] ?></span>
            <span class="stat-label"><?= htmlspecialchars($label) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<h3 class="section-heading">Mere tretmana rizika</h3>
<div class="soa-summary">
    <?php foreach ($treatmentLabels as $key => $label): ?>
        <div class="soa-summary-stat">
            <span class="stat-value"><?= (int) $treatmentCounts[$key] ?></span>
            <span class="stat-label"><?= htmlspecialchars($label) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<h3 class="section-heading">Nalazi internog audita (<?= (int) $totalFindings ?> ukupno)</h3>
<div class="soa-summary">
    <?php foreach ($severityLabels as $key => $label): ?>
        <div class="soa-summary-stat">
            <span class="stat-value"><?= (int) $findingCounts[$key] ?></span>
            <span class="stat-label"><?= htmlspecialchars($label) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<h3 class="section-heading">Učenje iz incidenata</h3>
<div class="soa-summary">
    <div class="soa-summary-stat">
        <span class="stat-value"><?= (int) $incidentStats['total'] ?></span>
        <span class="stat-label">Prijavljeno ukupno</span>
    </div>
    <div class="soa-summary-stat">
        <span class="stat-value"><?= (int) $incidentStats['closed'] ?></span>
        <span class="stat-label">Zatvoreno</span>
    </div>
    <div class="soa-summary-stat">
        <span class="stat-value"><?= (int) $incidentStats['closed_with_cause'] ?></span>
        <span class="stat-label">Zatvoreno sa korenom uzroka</span>
    </div>
</div>

<h3 class="section-heading">Radnje iz pregleda menadžmenta</h3>
<div class="soa-summary">
    <?php foreach ($reviewActionLabels as $key => $label): ?>
        <div class="soa-summary-stat">
            <span class="stat-value"><?= (int) $reviewActionCounts[$key] ?></span>
            <span class="stat-label"><?= htmlspecialchars($label) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<h3 class="section-heading">Poslednje identifikovane prilike za unapređenje</h3>
<?php if ($latestOpportunity === false): ?>
    <p class="empty-state">Još uvek nijedan pregled menadžmenta nije popunio polje 9.3(g).</p>
<?php else: ?>
    <div class="factor-card">
        <p class="item-meta">Iz pregleda od <?= htmlspecialchars($latestOpportunity['review_date']) ?></p>
        <p><?= nl2br(htmlspecialchars($latestOpportunity['improvement_opportunities'])) ?></p>
    </div>
<?php endif; ?>
