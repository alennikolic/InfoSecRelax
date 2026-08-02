# InfoSecRelax

Web aplikacija koja malim i srednjim preduzećima pomaže da implementiraju i
kasnije održavaju sistem upravljanja bezbednošću informacija (ISMS) prema
ISO/IEC 27001. Meni aplikacije prati redosled uspostavljanja standarda —
korisnik ide kroz kontekst, rizik i planiranje, liderstvo, podršku,
operaciju, ocenjivanje učinka i unapređenje.

## Status

Svih **28 stavki menija koje prate standard** ima realnu funkcionalnost —
dodavanje, pregled, uređivanje i brisanje podataka, sa promenom statusa gde
je to prirodno (rizici, ciljevi, korektivne mere...). Skoro svaki modul sada
podržava i **pravo uređivanje** već unetih zapisa (dugme "Uredi" na svakoj
kartici), ne samo dodaj/obriši.

Svaka stranica ima i dugme **Pomoć** koje otvara kratko objašnjenje klauzule
ili kontrole koju ta stranica pokriva, sa primerima. Sadržaj pomoći se čuva u
bazi i uređuje se centralno, na jednom mestu za celu aplikaciju (grupa
"Alati" → "Uređivanje pomoći" u meniju) — ne po svakoj stranici posebno.

Poznata ograničenja, namerno ostavljena za kasnije:

- **Nema CSRF zaštite** na formama.
- **Nema autentifikacije ni registracije** — `organization_id` je uvek `1`
  (videti `ensureDefaultOrganization()` u `src/config/database.php`).
- Nekoliko tabela iz šeme nema svoju stavku menija (namerne odluke, ne
  propusti): `equipment`, `storage_media`, `personnel_screening`,
  `confidentiality_agreements`, `disciplinary_actions`. Takođe,
  `legal_requirements` je zamenjena širim `compliance_items` registrom
  (A.5.31-5.36) i ostaje u šemi neiskorišćena.

## Tehnologije

- **PHP 8.2** (Apache), bez frameworka — čist proceduralni PHP po modulu
- **MySQL 8.0**
- **Docker / docker-compose** za lokalno pokretanje
- Bez JavaScript build-a i bez ijedne spoljne biblioteke — čist vanilla JS
  (modali za dodavanje/uređivanje, pomoć, skrolovanje aktivne stavke menija)
  i čist CSS, ništa se ne kompajlira

## Pokretanje

```bash
docker compose up -d --build
```

Aplikacija je dostupna na `http://localhost:8090`, MySQL na portu `3307`
(mapiran sa internog `3306`).

Kredencijali (razvojno okruženje, videti `docker-compose.yml`):

| | |
|---|---|
| Baza | `infosecrelax` |
| Korisnik | `infosecrelax_user` |
| Lozinka | `infosecrelax_password` |

Demo podaci (`db/demo-data.sql`) učitavaju se preko dugmeta na
`?page=pregled-sistema`.

## Struktura projekta

```
InfoSecRelax/
├── Dockerfile
├── docker-compose.yml
├── db/
│   ├── init.sql                    (kompletna šema + seed podaci - pokreće se samo na SVEŽOJ instalaciji)
│   └── demo-data.sql               (demo podaci, učitavaju se preko dugmeta na pregled-sistema)
└── src/
    ├── index.php                     (jedina ulazna tačka / ruter)
    ├── config/
    │   ├── menu.php                   (struktura navigacije - jedini izvor istine za meni)
    │   └── database.php               (PDO konekcija, ensureDefaultOrganization())
    ├── includes/
    │   ├── header.php / footer.php / placeholder.php
    │   ├── document-helpers.php       (deljena logika za documents/document_versions)
    │   ├── help-content.php           (getHelpContent/saveHelpContent - čitanje i upis pomoći)
    │   ├── help-modal.php             (deljeni modal pomoći, view-only, uključuje se na svakoj stranici koja ima Pomoć dugme)
    │   └── *-card.php                 (po jedan reusable prikaz-fragment po entitetu)
    ├── modules/                       (po jedan fajl po stavci menija - {slug}.php)
    │   └── pomoc-uredjivanje.php      (centralno uređivanje sadržaja pomoći, van standarda)
    └── assets/
        └── style.css
```

## Pregled modula

| Grupa | Modul | Klauzula / kontrola |
|---|---|---|
| *(van grupa)* | Pregled sistema | 4.4 |
| Kontekst i obim | Kontekst organizacije | 4.1 |
| Kontekst i obim | Zainteresovane strane | 4.2 |
| Kontekst i obim | Obim ISMS-a | 4.3 |
| Rizik i planiranje | Popis sredstava | A.5.9 |
| Rizik i planiranje | Procena rizika | 6.1.2 |
| Rizik i planiranje | Izjava o primenljivosti | 6.1.3 / Aneks A |
| Rizik i planiranje | Ciljevi bezbednosti | 6.2 |
| Rizik i planiranje | Planiranje promena | 6.3 |
| Liderstvo | Liderstvo i posvećenost | 5.1 |
| Liderstvo | Politike bezbednosti | 5.2 / A.5.1 |
| Liderstvo | Zaposleni i saradnici | A.6 |
| Liderstvo | Uloge i odgovornosti | 5.3 / A.5.2 |
| Podrška | Resursi | 7.1 |
| Podrška | Kompetentnost i obuka | 7.2-7.3 |
| Podrška | Komunikacija | 7.4 |
| Podrška | Dokumenti | 7.5 |
| Operacija | Sistemi i pristup | 8.1 / A.8.1-8.5 |
| Operacija | Dobavljači | A.5.19-5.23 |
| Operacija | Fizička bezbednost | A.7 |
| Operacija | Upravljanje incidentima | A.5.24-5.28 |
| Operacija | Kontinuitet poslovanja | A.5.29-5.30 |
| Operacija | Usklađenost | A.5.31-5.36 |
| Ocenjivanje učinka | Pokazatelji i merenje | 9.1 |
| Ocenjivanje učinka | Interni audit | 9.2 |
| Ocenjivanje učinka | Pregled menadžmenta | 9.3 |
| Unapređenje | Stalno unapređenje | 10.1 |
| Unapređenje | Korektivne mere | 10.2 |
| Alati | Uređivanje pomoći | *(van standarda)* |

Redosled grupa "Rizik i planiranje" pre "Liderstvo" je namerna odluka
(obrnuto od brojeva klauzula 5 i 6) — videti napomenu u `config/menu.php`.

## Arhitektonski obrasci

- **Ruter je data-driven.** `config/menu.php` vraća niz stavki menija;
  `index.php` čita `?page=slug`, i ako postoji `modules/{slug}.php` učitava
  ga, inače prikazuje `includes/placeholder.php`.
- **Post-Redirect-Get** na svakom upisu — obradi POST, uradi redirect, tek
  onda učitaj podatke za prikaz.
- **PDO isključivo sa pripremljenim upitima**, `PDO::ATTR_EMULATE_PREPARES
  => false`.
- **Sav tekst ka korisniku ide kroz `htmlspecialchars()`.**
- **Status-bedž sistem** (`.status-badge` + `.is-positive/.is-warning/
  .is-danger/.is-neutral`) deli se kroz skoro sve module za nivoe rizika,
  ozbiljnost, status usklađenosti i slično.
- **Dodavanje i uređivanje idu kroz modal.** Skoro svaka stranica ima
  toolbar na vrhu ("+ Dodaj X" levo, "Pomoć" desno) i jedan deljeni modal za
  dodavanje/uređivanje te glavne stavke. Polja koja bi uređivanje moglo da
  pokvari (npr. oznaka verzije dokumenta, status koji ima svoju posebnu
  radnju) namerno su izuzeta iz "Uredi" forme.
- **Ugnježdeno dodavanje (roditelj-dete) takođe ide kroz modal**, otvoren
  dugmetom u kartici roditelja (npr. "Dodaj meru" na kartici rizika), umesto
  ugrađene forme koja je uvek vidljiva — čisti kartice, smanjuje rizik od
  slučajnog klika na obližnje dugme za brisanje.
- **Destruktivne radnje idu u desni ugao kartice** (`.card-footer-right`),
  odvojene razmakom od dugmadi za dodavanje iznad njih, iz istog razloga.
- **Centralizovan sistem pomoći.** `help_content` tabela (jedan red po
  `page_slug`), čita se preko `getHelpContent()`, prikazuje kroz deljeni
  `includes/help-modal.php` (samo prikaz), uređuje isključivo preko
  `?page=pomoc-uredjivanje` — nema uređivanja "na licu mesta" po svakoj
  stranici.
- **Bočna traka (`.sidebar`) skroluje nezavisno od glavnog sadržaja**
  (`position: sticky` + ograničena visina), a skripta koja dovodi aktivnu
  stavku menija u vidokrug je namerno ugrađena u `header.php` odmah posle
  menija (ne u spoljnom fajlu na dnu stranice) da bi se izvršila pre prvog
  iscrtavanja i izbegla vidljiv "skok" sadržaja.
- **Dva tipa modula:**
  - Većina su **CRUD registri** (dodaj/uredi/prikaži/obriši, ponegde i
    status).
  - Nekoliko klauzula (4.4, 5.1, 10.1) nema svoju tabelu u šemi — narativne
    su, pa su implementirane kao **read-only dashboard-i** koji sažimaju
    dokaze iz podataka koji se već beleže na drugim mestima.

## Sledeći koraci

Predlog redosleda, po prioritetu:

1. **`?page=izjava-primenljivosti`** — jedina stranica koja nije prošla kroz
   noviji obrazac (toolbar, modal, Pomoć dugme).
2. **CSRF zaštita** na formama.
3. **Autentifikacija i registracija firmi** — kad se doda, `organization_id`
   prestaje da bude uvek `1` (videti napomenu u `database.php`).
4. Opciono: prebaciti preostala ugrađena ugnježdena dodavanja (prisustvo na
   obuci i sl.) na isti modal obrazac, radi potpune doslednosti.
