<?php
/**
 * src/includes/metric-card.php - prikaz jednog pokazatelja i njegovih
 * merenja (Klauzula 9.1).
 *
 * Očekuje da su $metric (red iz metrics) i $measurements (niz redova
 * iz metric_measurements za taj pokazatelj, spojenih LEFT JOIN-om sa
 * personnel radi measured_by_name, sortiran najnovije prvo; može biti
 * prazan niz) već postavljeni pre uključivanja ovog fajla - deli scope
 * sa foreach petljom iz koje se poziva. Takođe očekuje
 * $activePersonnelOptions, postavljen jednom u pokazatelji.php pre
 * foreach petlje.
 *
 * target_value i value dolaze iz DECIMAL(10,2) kolona - PDO ih vraća
 * kao stringove sa uvek tačno dve decimale (npr. "5.00"), pa se
 * suvišne nule uklanjaju preko rtrim() radi čitljivijeg prikaza
 * ("5" umesto "5.00", "12.5" umesto "12.50").
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
        <?php if (!empty($metric['measurement_frequency'])): ?>
            <span class="factor-category"><?= htmlspecialchars($metric['measurement_frequency']) ?></span>
        <?php endif; ?>
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

    <form method="post" class="subform">
        <input type="hidden" name="action" value="add_measurement">
        <input type="hidden" name="metric_id" value="<?= (int) $metric['id'] ?>">

        <div class="form-row">
            <label for="value_<?= (int) $metric['id'] ?>">Vrednost</label>
            <input type="number" name="value" id="value_<?= (int) $metric['id'] ?>" step="0.01" required>
        </div>

        <div class="form-row">
            <label for="measured_at_<?= (int) $metric['id'] ?>">Datum merenja</label>
            <input type="date" name="measured_at" id="measured_at_<?= (int) $metric['id'] ?>" value="<?= date('Y-m-d') ?>">
        </div>

        <div class="form-row">
            <label for="measured_by_<?= (int) $metric['id'] ?>">Izmerio (opciono)</label>
            <select name="measured_by" id="measured_by_<?= (int) $metric['id'] ?>">
                <option value="">Nije dodeljen</option>
                <?php foreach ($activePersonnelOptions as $option): ?>
                    <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <label for="notes_<?= (int) $metric['id'] ?>">Napomena (opciono)</label>
            <textarea name="notes" id="notes_<?= (int) $metric['id'] ?>" rows="2"></textarea>
        </div>

        <button type="submit" class="btn-secondary">Dodaj merenje</button>
    </form>

    <form method="post" class="factor-delete-form" onsubmit="return confirm('Obrisati ovaj pokazatelj i sva njegova merenja?');">
        <input type="hidden" name="action" value="delete_metric">
        <input type="hidden" name="id" value="<?= (int) $metric['id'] ?>">
        <button type="submit" class="btn-delete">Obriši pokazatelj</button>
    </form>
</div>
