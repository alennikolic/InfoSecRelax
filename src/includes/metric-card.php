<?php
/**
 * src/includes/metric-card.php - prikaz jednog pokazatelja i njegovih
 * merenja (Klauzula 9.1).
 *
 * Očekuje da su $metric (red iz metrics) i $measurements (niz redova
 * iz metric_measurements za taj pokazatelj, spojenih LEFT JOIN-om sa
 * personnel radi measured_by_name, sortiran najnovije prvo; može biti
 * prazan niz) već postavljeni pre uključivanja ovog fajla - deli scope
 * sa foreach petljom iz koje se poziva.
 *
 * "+ Dodaj merenje" ne otvara više ugrađenu formu u kartici - otvara
 * zajednički modal na nivou stranice (modules/pokazatelji.php).
 *
 * target_value i value dolaze iz DECIMAL(10,2) kolona - PDO ih vraća
 * kao stringove sa uvek tačno dve decimale (npr. "5.00"), pa se
 * suvišne nule uklanjaju preko rtrim() radi čitljivijeg prikaza
 * ("5" umesto "5.00", "12.5" umesto "12.50") - isti $formatValue se
 * koristi i za predpopunjavanje polja "Ciljna vrednost" u modalu za
 * uređivanje.
 */

$latestMeasurement = $measurements[0] ?? null;
$unit = $metric['unit'] !== null ? ' ' . $metric['unit'] : '';

$formatValue = static function (string $decimalString): string {
    return rtrim(rtrim($decimalString, '0'), '.');
};
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($metric['name']) ?></span>
        <div class="button-group">
            <?php if (!empty($metric['measurement_frequency'])): ?>
                <span class="factor-category"><?= htmlspecialchars($metric['measurement_frequency']) ?></span>
            <?php endif; ?>
            <button type="button" class="btn-secondary"
                onclick='openEditMetricModal(<?= json_encode([
                    "id"                     => (int) $metric["id"],
                    "name"                   => $metric["name"],
                    "description"            => $metric["description"] ?? "",
                    "unit"                   => $metric["unit"] ?? "",
                    "target_value"           => $metric["target_value"] !== null ? $formatValue($metric["target_value"]) : "",
                    "measurement_frequency"  => $metric["measurement_frequency"] ?? "",
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <?php if (!empty($metric['description'])): ?>
        <p><?= nl2br(htmlspecialchars($metric['description'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">
        <?php if ($metric['target_value'] !== null): ?>
            Cilj: <?= htmlspecialchars($formatValue($metric['target_value'])) ?><?= htmlspecialchars($unit) ?> ·
        <?php endif; ?>
        <?php if ($latestMeasurement !== null): ?>
            Poslednje merenje: <?= htmlspecialchars($formatValue($latestMeasurement['value'])) ?><?= htmlspecialchars($unit) ?>
            (<?= htmlspecialchars(substr((string) $latestMeasurement['measured_at'], 0, 10)) ?>)
        <?php else: ?>
            Još nema merenja
        <?php endif; ?>
    </p>

    <p class="item-title">Merenja (<?= count($measurements) ?>)</p>

    <?php if (empty($measurements)): ?>
        <p class="empty-state">Još uvek nema unetih merenja.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($measurements as $measurement): ?>
                <li class="requirement-item">
                    <div class="requirement-text">
                        <strong><?= htmlspecialchars($formatValue($measurement['value'])) ?><?= htmlspecialchars($unit) ?></strong>
                        — <?= htmlspecialchars(substr((string) $measurement['measured_at'], 0, 10)) ?>
                        <?php if ($measurement['measured_by_name'] !== null): ?>
                            (<?= htmlspecialchars($measurement['measured_by_name']) ?>)
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($measurement['notes'])): ?>
                        <p class="item-meta"><?= nl2br(htmlspecialchars($measurement['notes'])) ?></p>
                    <?php endif; ?>
                    <div class="card-actions">
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovo merenje?');">
                            <input type="hidden" name="action" value="delete_measurement">
                            <input type="hidden" name="id" value="<?= (int) $measurement['id'] ?>">
                            <button type="submit" class="btn-delete">Obriši</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <button type="button" class="btn-secondary"
        onclick='openMeasurementModal(<?= (int) $metric['id'] ?>, <?= json_encode($metric['name'], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>+ Dodaj merenje</button>

    <form method="post" class="factor-delete-form card-footer-right" onsubmit="return confirm('Obrisati ovaj pokazatelj i sva njegova merenja?');">
        <input type="hidden" name="action" value="delete_metric">
        <input type="hidden" name="id" value="<?= (int) $metric['id'] ?>">
        <button type="submit" class="btn-delete">Obriši pokazatelj</button>
    </form>
</div>
