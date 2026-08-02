<?php
/**
 * src/includes/incident-card.php - prikaz jednog bezbednosnog događaja
 * (A.5.24-5.28).
 *
 * Očekuje da je $event (red iz security_events, spojen LEFT JOIN-om sa
 * personnel radi reporter_name) već postavljen pre uključivanja ovog
 * fajla - deli scope sa foreach petljom iz koje se poziva.
 *
 * security_events nema posebnu "naziv" kolonu, pa se kao naslov kartice
 * koristi datum prijave, a pun opis ide kao prvi pasus tela kartice -
 * izbegava se skraćivanje teksta (mb_strimwidth zahteva mbstring, koji
 * nije garantovano instaliran u ovoj Docker slici).
 */

$assessmentLabels = [
    'na_cekanju'         => 'Na čekanju',
    'lazna_uzbuna'       => 'Lažna uzbuna',
    'potvrdjen_incident' => 'Potvrđen incident',
];

$assessmentTone = [
    'na_cekanju'         => 'is-neutral',
    'lazna_uzbuna'       => 'is-positive',
    'potvrdjen_incident' => 'is-danger',
];

$severityLabels = [
    'nizak'   => 'Nizak',
    'srednji' => 'Srednji',
    'visok'   => 'Visok',
];

$severityTone = [
    'nizak'   => 'is-positive',
    'srednji' => 'is-warning',
    'visok'   => 'is-danger',
];

$isClosed = !empty($event['closed_at']);
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title">Prijavljeno <?= htmlspecialchars(substr((string) $event['reported_at'], 0, 16)) ?></span>
        <div class="button-group">
            <span class="status-badge <?= htmlspecialchars($assessmentTone[$event['assessment_outcome']] ?? 'is-neutral') ?>">
                <?= htmlspecialchars($assessmentLabels[$event['assessment_outcome']] ?? $event['assessment_outcome']) ?>
            </span>
            <button type="button" class="btn-secondary"
                onclick='openEditEventModal(<?= json_encode([
                    "id"          => (int) $event["id"],
                    "description" => $event["description"],
                    "reported_by" => $event["reported_by"] ?? "",
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <p><?= nl2br(htmlspecialchars($event['description'])) ?></p>

    <p class="item-meta">
        <?php if ($event['reporter_name'] !== null): ?>
            Prijavio: <?= htmlspecialchars($event['reporter_name']) ?>
        <?php else: ?>
            Prijavljeno anonimno
        <?php endif; ?>
    </p>

    <?php if ($event['severity'] !== null || $isClosed): ?>
    <p>
        <?php if ($event['severity'] !== null): ?>
            <span class="status-badge <?= htmlspecialchars($severityTone[$event['severity']] ?? 'is-neutral') ?>">
                Ozbiljnost: <?= htmlspecialchars($severityLabels[$event['severity']] ?? $event['severity']) ?>
            </span>
        <?php endif; ?>
        <?php if ($isClosed): ?>
            <span class="status-badge is-neutral">Zatvoreno</span>
        <?php endif; ?>
    </p>
    <?php endif; ?>

    <?php if (!empty($event['root_cause'])): ?>
        <p><strong>Koren uzroka:</strong> <?= nl2br(htmlspecialchars($event['root_cause'])) ?></p>
    <?php endif; ?>

    <?php if (!empty($event['evidence_reference'])): ?>
        <p class="item-meta">Dokazi: <?= htmlspecialchars($event['evidence_reference']) ?></p>
    <?php endif; ?>

    <?php if ($isClosed): ?>
        <p class="item-meta">Zatvoreno: <?= htmlspecialchars(substr((string) $event['closed_at'], 0, 16)) ?></p>
    <?php endif; ?>

    <button type="button" class="btn-secondary"
        onclick='openAssessmentModal(<?= json_encode([
            "id"                 => (int) $event["id"],
            "assessment_outcome" => $event["assessment_outcome"],
            "severity"           => $event["severity"] ?? "",
            "root_cause"         => $event["root_cause"] ?? "",
            "evidence_reference" => $event["evidence_reference"] ?? "",
        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Pokreni procenu</button>

    <div class="card-actions card-footer-right">
        <?php if ($isClosed): ?>
            <form method="post" class="factor-delete-form">
                <input type="hidden" name="action" value="reopen">
                <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                <button type="submit" class="btn-secondary">Ponovo otvori</button>
            </form>
        <?php else: ?>
            <form method="post" class="factor-delete-form" onsubmit="return confirm('Zatvoriti ovaj incident?');">
                <input type="hidden" name="action" value="close">
                <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                <button type="submit" class="btn-secondary">Zatvori</button>
            </form>
        <?php endif; ?>
        <form method="post" class="factor-delete-form" onsubmit="return confirm('Trajno obrisati ovaj incident?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
            <button type="submit" class="btn-delete">Obriši</button>
        </form>
    </div>
</div>
