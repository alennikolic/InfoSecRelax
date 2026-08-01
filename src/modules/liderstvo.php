<?php
/**
 * src/modules/liderstvo.php
 *
 * Klauzula 5.1: Liderstvo i posvećenost.
 *
 * Isti princip kao unapredjenje.php (10.1) - nema svoju tabelu u šemi,
 * narativna je klauzula. Top menadžment demonstrira posvećenost kroz
 * ono što se već radi na drugim mestima: da li opšta politika postoji
 * i da li je odobrena, da li se pregledi menadžmenta zaista održavaju,
 * da li ciljevi bivaju ostvareni, da li uloge nose stvarno ovlašćenje,
 * i da li se radnje iz pregleda zaista privode kraju. Read-only - bez
 * ijedne forme, bez upisa u bazu.
 */

declare(strict_types=1);

require __DIR__ . '/../config/database.php';

$pdo = getDbConnection();
$organizationId = ensureDefaultOrganization($pdo);

// --- Opšta politika bezbednosti (Klauzula 5.2(a) - direktan dokaz 5.1(a)) ---
$generalPolicyStmt = $pdo->prepare(
    "SELECT d.title, d.current_version, d.approved_at, per.full_name AS approved_by_name
     FROM policies p
     INNER JOIN documents d ON d.id = p.document_id
     LEFT JOIN personnel per ON per.id = d.approved_by
     WHERE p.organization_id = :org_id AND p.policy_type = 'opsta'
     ORDER BY (d.approved_at IS NULL), d.approved_at DESC
     LIMIT 1"
);
$generalPolicyStmt->execute(['org_id' => $organizationId]);
$generalPolicy = $generalPolicyStmt->fetch();

// --- Pregledi menadžmenta - broj i poslednji ---
$reviewCountStmt = $pdo->prepare('SELECT COUNT(*) FROM management_reviews WHERE organization_id = :org_id');
$reviewCountStmt->execute(['org_id' => $organizationId]);
$reviewCount = (int) $reviewCountStmt->fetchColumn();

$latestReviewStmt = $pdo->prepare(
    'SELECT review_date, attendees
     FROM management_reviews
     WHERE organization_id = :org_id
     ORDER BY review_date DESC
     LIMIT 1'
);
$latestReviewStmt->execute(['org_id' => $organizationId]);
$latestReview = $latestReviewStmt->fetch();

// --- Ciljevi: ostvareno / ukupno (Klauzula 5.1(e) - da li ISMS ostvaruje nameravane rezultate) ---
$objectivesStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total, SUM(status = 'ostvaren') AS achieved
     FROM objectives WHERE organization_id = :org_id"
);
$objectivesStmt->execute(['org_id' => $organizationId]);
$objectivesStats = $objectivesStmt->fetch();

// --- Uloge sa stvarno dodeljenim ovlašćenjem (Klauzula 5.1(f)/(h)) ---
$rolesStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total, SUM(authority_level IS NOT NULL AND authority_level != '') AS with_authority
     FROM roles_responsibilities WHERE organization_id = :org_id"
);
$rolesStmt->execute(['org_id' => $organizationId]);
$rolesStats = $rolesStmt->fetch();

// --- Politike koje je rukovodstvo stvarno odobrilo (bilo koja vrsta) ---
$approvedPoliciesStmt = $pdo->prepare(
    'SELECT d.title, d.approved_at, per.full_name AS approved_by_name
     FROM policies p
     INNER JOIN documents d ON d.id = p.document_id
     INNER JOIN personnel per ON per.id = d.approved_by
     WHERE p.organization_id = :org_id
     ORDER BY d.approved_at DESC'
);
$approvedPoliciesStmt->execute(['org_id' => $organizationId]);
$approvedPolicies = $approvedPoliciesStmt->fetchAll();

// --- Otvorene radnje iz pregleda menadžmenta (Klauzula 5.1(g) - dokaz da se prati kraj) ---
$openActionsStmt = $pdo->prepare(
    "SELECT a.action_description, a.due_date, a.status, per.full_name AS owner_name, r.review_date
     FROM management_review_actions a
     INNER JOIN management_reviews r ON r.id = a.management_review_id
     LEFT JOIN personnel per ON per.id = a.owner_id
     WHERE r.organization_id = :org_id AND a.status != 'zavrseno'
     ORDER BY a.due_date IS NULL, a.due_date"
);
$openActionsStmt->execute(['org_id' => $organizationId]);
$openActions = $openActionsStmt->fetchAll();
?>

<p class="module-intro">
    Klauzula 5.1 traži da top menadžment demonstrira liderstvo i posvećenost
    ISMS-u - kroz politiku, ciljeve, resurse i aktivno učešće u pregledima i
    unapređenju. Ova stranica nema svoju tabelu, nego sažima dokaze te
    posvećenosti koji se već beleže na drugim mestima u aplikaciji.
</p>

<div class="soa-summary">
    <div class="soa-summary-stat">
        <span class="stat-value"><?= $reviewCount ?></span>
        <span class="stat-label">Pregleda menadžmenta održano</span>
    </div>
    <div class="soa-summary-stat">
        <span class="stat-value"><?= (int) ($objectivesStats['achieved'] ?? 0) ?> / <?= (int) ($objectivesStats['total'] ?? 0) ?></span>
        <span class="stat-label">Ciljeva ostvareno</span>
    </div>
    <div class="soa-summary-stat">
        <span class="stat-value"><?= (int) ($rolesStats['with_authority'] ?? 0) ?> / <?= (int) ($rolesStats['total'] ?? 0) ?></span>
        <span class="stat-label">Uloga sa ovlašćenjem</span>
    </div>
    <div class="soa-summary-stat">
        <span class="stat-value"><?= count($openActions) ?></span>
        <span class="stat-label">Otvorenih radnji iz pregleda</span>
    </div>
</div>

<h3 class="section-heading">Opšta politika bezbednosti informacija</h3>
<?php if ($generalPolicy === false): ?>
    <p class="empty-state">Opšta politika (Klauzula 5.2) još uvek nije uneta na stranici "Politike bezbednosti".</p>
<?php else: ?>
    <div class="scope-current">
        <div class="card-header-row">
            <span class="card-title"><?= htmlspecialchars($generalPolicy['title']) ?></span>
            <span class="scope-version-badge">Verzija <?= htmlspecialchars($generalPolicy['current_version']) ?></span>
        </div>
        <?php if ($generalPolicy['approved_by_name'] !== null): ?>
            <p class="item-meta">
                Odobrio: <?= htmlspecialchars($generalPolicy['approved_by_name']) ?>
                <?php if (!empty($generalPolicy['approved_at'])): ?>
                    (<?= htmlspecialchars($generalPolicy['approved_at']) ?>)
                <?php endif; ?>
            </p>
        <?php else: ?>
            <p class="item-meta">Još uvek nema evidentiranog odobrenja rukovodstva.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<h3 class="section-heading">Poslednji pregled menadžmenta</h3>
<?php if ($latestReview === false): ?>
    <p class="empty-state">Još uvek nijedan pregled menadžmenta nije održan.</p>
<?php else: ?>
    <div class="factor-card">
        <p class="item-meta">Datum: <?= htmlspecialchars($latestReview['review_date']) ?></p>
        <?php if (!empty($latestReview['attendees'])): ?>
            <p><?= htmlspecialchars($latestReview['attendees']) ?></p>
        <?php else: ?>
            <p class="empty-state">Prisutni nisu evidentirani za ovaj pregled.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<h3 class="section-heading">Politike koje je rukovodstvo odobrilo (<?= count($approvedPolicies) ?>)</h3>
<?php if (empty($approvedPolicies)): ?>
    <p class="empty-state">Još uvek nijedna politika nema evidentirano odobrenje.</p>
<?php else: ?>
    <ul class="requirement-list">
        <?php foreach ($approvedPolicies as $approvedPolicy): ?>
            <li class="requirement-item">
                <div class="requirement-text"><?= htmlspecialchars($approvedPolicy['title']) ?></div>
                <p class="item-meta">
                    Odobrio: <?= htmlspecialchars($approvedPolicy['approved_by_name']) ?>
                    <?php if (!empty($approvedPolicy['approved_at'])): ?>
                        · <?= htmlspecialchars($approvedPolicy['approved_at']) ?>
                    <?php endif; ?>
                </p>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<h3 class="section-heading">Otvorene radnje iz pregleda menadžmenta (<?= count($openActions) ?>)</h3>
<?php if (empty($openActions)): ?>
    <p class="empty-state">Nema otvorenih radnji - sve su završene ili ih još uvek nema.</p>
<?php else: ?>
    <ul class="requirement-list">
        <?php foreach ($openActions as $openAction): ?>
            <li class="requirement-item">
                <div class="requirement-text"><?= htmlspecialchars($openAction['action_description']) ?></div>
                <p class="item-meta">
                    Iz pregleda od <?= htmlspecialchars($openAction['review_date']) ?>
                    <?php if ($openAction['owner_name'] !== null): ?>
                        · Nosilac: <?= htmlspecialchars($openAction['owner_name']) ?>
                    <?php endif; ?>
                    <?php if (!empty($openAction['due_date'])): ?>
                        · Rok: <?= htmlspecialchars($openAction['due_date']) ?>
                    <?php endif; ?>
                </p>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
