<?php
/**
 * src/includes/footer.php - zatvaranje layout-a otvorenog u header.php.
 *
 * assets/help-modal.js se učitava ovde, globalno na svakoj stranici -
 * funkcije unutra proveravaju da li modal pomoći uopšte postoji na
 * trenutnoj stranici pre nego što ga diraju, pa je bezbedno učitati ga
 * svuda i pre nego što svaka stranica dobije svoj modal pomoći.
 *
 * Skrolovanje aktivne stavke menija u vidokrug NIJE ovde - to je
 * ugrađeno u header.php, odmah posle menija, da bi se izvršilo pre
 * prvog iscrtavanja stranice (videti napomenu tamo).
 */
?>
            </div>
        </main>

    </div>

    <script src="assets/help-modal.js"></script>
</body>
</html>
