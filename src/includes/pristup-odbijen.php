<?php
/**
 * src/includes/pristup-odbijen.php
 *
 * Deljeni fragment prikazan kad RBAC provera (requirePagePermission /
 * requireSuperAdmin u config/auth.php) zaustavi zahtev. Namerno je
 * čist fragment (bez <html>/<head>) - poziva se i iz konteksta gde je
 * header.php već ispisao layout (obična RBAC odbijenica usred
 * stranice), i iz konteksta gde header.php uopšte nije pozvan
 * (requireSuperAdmin pre nego što se učita bilo kakav meni).
 */

declare(strict_types=1);
?>
<div class="alert alert-error">
    <p><strong>Nemate pristup ovoj stranici.</strong></p>
    <p>
        Vaša rola nema dovoljna prava za ovu radnju. Obratite se
        administratoru vaše organizacije ako smatrate da je ovo greška.
    </p>
</div>
