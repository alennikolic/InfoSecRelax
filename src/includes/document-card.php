<?php
/**
 * src/includes/document-card.php - prikaz jednog dokumenta i njegove
 * istorije verzija (Klauzula 7.5 / 7.5.2).
 *
 * Očekuje da su $document (red iz documents, spojen LEFT JOIN-om sa
 * personnel radi owner_name i approved_by_name) i $versions (niz
 * redova iz document_versions za taj dokument, spojenih LEFT JOIN-om
 * sa personnel radi changed_by_name; može biti prazan niz) već
 * postavljeni pre uključivanja ovog fajla - deli scope sa foreach
 * petljom iz koje se poziva.
 *
 * "Nova verzija" ne otvara više ugrađenu formu u kartici - otvara
 * zajednički modal na nivou stranice (modules/dokumenti.php), isti
 * obrazac kao "Nova verzija" u policy-card.php.
 */

$docTypeLabels = [
    'politika'  => 'Politika',
    'procedura' => 'Procedura',
    'registar'  => 'Registar',
    'zapisnik'  => 'Zapisnik',
    'ostalo'    => 'Ostalo',
];

$classificationLabels = [
    'javno'             => 'Javno',
    'interno'           => 'Interno',
    'poverljivo'        => 'Poverljivo',
    'strogo_poverljivo' => 'Strogo poverljivo',
];

$classificationTone = [
    'javno'             => 'is-neutral',
    'interno'           => 'is-neutral',
    'poverljivo'        => 'is-warning',
    'strogo_poverljivo' => 'is-danger',
];

$isReviewOverdue = !empty($document['next_review_due']) && $document['next_review_due'] < date('Y-m-d');
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($document['title']) ?></span>
        <div class="button-group">
            <span class="factor-category"><?= htmlspecialchars($docTypeLabels[$document['doc_type']] ?? $document['doc_type']) ?></span>
            <button type="button" class="btn-secondary"
                onclick='openEditDocumentModal(<?= json_encode([
                    "id"              => (int) $document["id"],
                    "title"           => $document["title"],
                    "doc_type"        => $document["doc_type"],
                    "classification"  => $document["classification"],
                    "file_reference"  => $document["file_reference"] ?? "",
                    "owner_id"        => $document["owner_id"] ?? "",
                    "approved_by"     => $document["approved_by"] ?? "",
                    "approved_at"     => $document["approved_at"] ?? "",
                    "next_review_due" => $document["next_review_due"] ?? "",
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <p>
        <span class="status-badge <?= htmlspecialchars($classificationTone[$document['classification']] ?? 'is-neutral') ?>">
            <?= htmlspecialchars($classificationLabels[$document['classification']] ?? $document['classification']) ?>
        </span>
        <?php if ($isReviewOverdue): ?>
            <span class="status-badge is-danger">Pregled dospeo</span>
        <?php endif; ?>
    </p>

    <p class="item-meta">
        Verzija: <?= htmlspecialchars($document['current_version']) ?>
        <?php if ($document['owner_name'] !== null): ?>
            · Vlasnik: <?= htmlspecialchars($document['owner_name']) ?>
        <?php endif; ?>
        <?php if ($document['approved_by_name'] !== null): ?>
            · Odobrio: <?= htmlspecialchars($document['approved_by_name']) ?>
            <?php if (!empty($document['approved_at'])): ?>
                (<?= htmlspecialchars($document['approved_at']) ?>)
            <?php endif; ?>
        <?php endif; ?>
    </p>

    <?php if (!empty($document['next_review_due'])): ?>
        <p class="item-meta">Sledeći pregled: <?= htmlspecialchars($document['next_review_due']) ?></p>
    <?php endif; ?>

    <?php if (!empty($document['file_reference'])): ?>
        <p class="item-meta">Fajl: <?= htmlspecialchars($document['file_reference']) ?></p>
    <?php endif; ?>

    <p class="item-title">Istorija verzija (<?= count($versions) ?>)</p>

    <?php if (empty($versions)): ?>
        <p class="empty-state">Nema zabeleženih verzija.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($versions as $version): ?>
                <li class="requirement-item">
                    <div class="requirement-text">
                        <strong>Verzija <?= htmlspecialchars($version['version_number']) ?></strong>
                        <?php if (!empty($version['change_summary'])): ?>
                            — <?= nl2br(htmlspecialchars($version['change_summary'])) ?>
                        <?php endif; ?>
                    </div>
                    <p class="item-meta">
                        <?php if ($version['changed_by_name'] !== null): ?>
                            Izmenio: <?= htmlspecialchars($version['changed_by_name']) ?> ·
                        <?php endif; ?>
                        <?= htmlspecialchars(substr((string) $version['created_at'], 0, 10)) ?>
                    </p>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <button type="button" class="btn-secondary"
        onclick='openDocumentVersionModal(<?= (int) $document['id'] ?>, <?= json_encode($document['title'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Nova verzija</button>

    <form method="post" class="factor-delete-form card-footer-right" onsubmit="return confirm('Obrisati ovaj dokument i celu istoriju njegovih verzija?');">
        <input type="hidden" name="action" value="delete_document">
        <input type="hidden" name="id" value="<?= (int) $document['id'] ?>">
        <button type="submit" class="btn-delete">Obriši dokument</button>
    </form>
</div>
