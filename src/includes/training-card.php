<?php
/**
 * src/includes/training-card.php - prikaz jedne obuke i njenih
 * prisustava (Klauzula 7.3 / A.6.3).
 *
 * Očekuje da su $session (red iz training_sessions) i $attendees (niz
 * redova iz training_attendance za tu obuku, spojenih JOIN-om sa
 * personnel radi person_name; može biti prazan niz) već postavljeni
 * pre uključivanja ovog fajla - deli scope sa foreach petljom iz koje
 * se poziva. Takođe očekuje $activePersonnelOptions, postavljen jednom
 * u kompetentnost.php pre foreach petlje, za dropdown u formi za
 * dodavanje prisustva.
 *
 * Dodavanje osobe na listu prisustva ovde odmah znači "prisustvovao/la"
 * (completed_at se postavlja na trenutak dodavanja) - nema dvostepenog
 * "prijavljen pa naknadno označen kao završeno" toka, pošto obuka ima
 * jedan datum održavanja, pa je prisustvo praktično isto što i završetak.
 */
?>
<div class="factor-card">
    <div class="card-header-row">
        <span class="card-title"><?= htmlspecialchars($session['title']) ?></span>
        <div class="button-group">
            <span class="status-badge <?= $session['is_mandatory'] ? 'is-warning' : 'is-neutral' ?>">
                <?= $session['is_mandatory'] ? 'Obavezna' : 'Opciona' ?>
            </span>
            <button type="button" class="btn-secondary"
                onclick='openEditTrainingModal(<?= json_encode([
                    "id"           => (int) $session["id"],
                    "title"        => $session["title"],
                    "description"  => $session["description"] ?? "",
                    "held_at"      => $session["held_at"],
                    "is_mandatory" => (bool) $session["is_mandatory"],
                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) ?>)'>Uredi</button>
        </div>
    </div>

    <?php if (!empty($session['description'])): ?>
        <p><?= nl2br(htmlspecialchars($session['description'])) ?></p>
    <?php endif; ?>

    <p class="item-meta">Održano: <?= htmlspecialchars($session['held_at']) ?></p>

    <p class="item-title">Prisustvovali (<?= count($attendees) ?>)</p>

    <?php if (empty($attendees)): ?>
        <p class="empty-state">Još uvek nema evidentiranih prisustava.</p>
    <?php else: ?>
        <ul class="requirement-list">
            <?php foreach ($attendees as $attendee): ?>
                <li class="requirement-item">
                    <div class="requirement-text"><?= htmlspecialchars($attendee['person_name']) ?></div>
                    <div class="card-actions">
                        <form method="post" class="factor-delete-form" onsubmit="return confirm('Ukloniti ovo prisustvo?');">
                            <input type="hidden" name="action" value="delete_attendance">
                            <input type="hidden" name="id" value="<?= (int) $attendee['id'] ?>">
                            <button type="submit" class="btn-delete">Ukloni</button>
                        </form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <form method="post" class="subform">
        <input type="hidden" name="action" value="add_attendance">
        <input type="hidden" name="training_session_id" value="<?= (int) $session['id'] ?>">

        <div class="form-row form-row-inline">
            <label for="personnel_id_<?= (int) $session['id'] ?>">Dodaj prisustvo:</label>
            <select name="personnel_id" id="personnel_id_<?= (int) $session['id'] ?>">
                <option value="">Izaberite...</option>
                <?php foreach ($activePersonnelOptions as $option): ?>
                    <option value="<?= (int) $option['id'] ?>"><?= htmlspecialchars($option['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn-secondary">Dodaj</button>
        </div>
    </form>

    <form method="post" class="factor-delete-form card-footer-right" onsubmit="return confirm('Obrisati ovu obuku i sva evidentirana prisustva?');">
        <input type="hidden" name="action" value="delete_training">
        <input type="hidden" name="id" value="<?= (int) $session['id'] ?>">
        <button type="submit" class="btn-delete">Obriši obuku</button>
    </form>
</div>
