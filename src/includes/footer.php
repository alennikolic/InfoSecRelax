<?php
/**
 * src/includes/footer.php - zatvaranje layout-a otvorenog u header.php.
 *
 * assets/help-modal.js se učitava ovde, globalno na svakoj stranici -
 * funkcije unutra proveravaju da li modal pomoći uopšte postoji na
 * trenutnoj stranici pre nego što ga diraju, pa je bezbedno učitati ga
 * svuda i pre nego što svaka stranica dobije svoj modal pomoći.
 *
 * assets/sidebar-scroll.js dovodi aktivnu stavku menija u vidokrug pri
 * svakom učitavanju stranice - videti komentar u samom fajlu.
 */
?>
            </div>
        </main>

    </div>

    <script src="assets/help-modal.js"></script>
    <script src="assets/sidebar-scroll.js"></script>
</body>
</html>
