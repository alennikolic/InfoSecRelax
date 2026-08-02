<!-- README.md -->
# InfoSecRelax

Web aplikacija koja malim i srednjim preduzećima pomaže da implementiraju i
kasnije održavaju sistem upravljanja bezbednošću informacija (ISMS) prema
ISO/IEC 27001. Meni aplikacije prati redosled uspostavljanja standarda —
korisnik ide kroz kontekst, rizik i planiranje, liderstvo, podršku,
operaciju, ocenjivanje učinka i unapređenje.

Aplikacija je **multi-tenant** — jedna instalacija može istovremeno da
opslužuje više firmi (organizacija), svaku sa sopstvenim, potpuno
izolovanim podacima, korisnicima i rolama.

## Status

Svih **28 stavki menija koje prate standard** ima realnu funkcionalnost —
dodavanje, pregled, uređivanje i brisanje podataka, sa promenom statusa gde
je to prirodno (rizici, ciljevi, korektivne mere...). Skoro svaki modul
podržava i **pravo uređivanje** već unetih zapisa (dugme "Uredi" na svakoj
kartici), ne samo dodaj/obriši.

Svaka stranica ima i dugme **Pomoć** koje otvara kratko objašnjenje klauzule
ili kontrole koju ta stranica pokriva, sa primerima. Sadržaj pomoći se čuva u
bazi i uređuje se centralno, na jednom mestu za CELU instalaciju (ne po
organizaciji) — zato je uređivanje pomoći dostupno isključivo super adminu
(videti "Autentifikacija, multi-tenant i kontrola pristupa" ispod), ne
adminu pojedinačne firme.

Aplikacija ima punu **autentifikaciju, multi-tenant izolaciju podataka i
RBAC (kontrolu pristupa po roli i po stranici)** — videti odeljak ispod.

Poznata ograničenja, namerno ostavljena za kasnije:

- **Nema forme za promenu lozinke** — ni za super admina, ni za
  organizacione korisnike. Lozinka se trenutno može promeniti isključivo
  preko `bin/create-super-admin.php` (samo za super admina) ili ručnim
  `UPDATE users SET password_hash = ...` u bazi.
- **Dugmad za dodavanje/izmenu/brisanje u postojećim ISMS modulima
  (kontekst, rizici, sredstva...) se još ne sakrivaju** kad rola ima samo
  "čitanje" pravo na tu stranicu. Sam upis (POST) je već centralno
  blokiran u `index.php` bez obzira na ovo — ništa se ne može promeniti
  mimo dozvole — ali dugme ostaje vidljivo i klik vodi na "Nemate
  pristup" umesto da odmah nestane iz prikaza.
- `src/includes/demo-import.php` ostaje u projektu neiskorišćen (dugme
  "Uvezi demo podatke" je uklonjeno sa `?page=pregled-sistema` iz
  bezbednosnih razloga — brisalo je podatke SVIH organizacija bez
  filtera; demo skup se sada unosi automatski, jednom, isključivo za
  organizaciju id=1, kao deo `db/init.sql`).
- Nekoliko tabela iz šeme nema svoju stavku menija (namerne odluke, ne
  propusti): `equipment`, `storage_media`, `personnel_screening`,
  `confidentiality_agreements`, `disciplinary_actions`. Takođe,
  `legal_requirements` je zamenjena širim `compliance_items` registrom
  (A.5.31-5.36) i ostaje u šemi neiskorišćena.

## Autentifikacija, multi-tenant i kontrola pristupa (RBAC)

Dva nivoa naloga:

- **Super admin** (`users.is_super_admin = TRUE`) — ne pripada nijednoj
  organizaciji (`organization_id IS NULL`), nema rolu. Jedina nadležnost:
  kreiranje novih organizacija (`?page=organizacije`) i uređivanje
  centralnog sadržaja pomoći (`?page=pomoc-uredjivanje`) — obe stranice su
  van RBAC-a pojedinačnih firmi, jer diraju podatke/sadržaj koji nije
  vezan za samo jednu organizaciju.
- **Organizacioni korisnik** — pripada tačno jednoj organizaciji i ima
  tačno jednu custom rolu unutar nje. Rola određuje, po stranici iz
  `config/menu.php`, jedan od tri nivoa prava:
  - `zabranjeno` (podrazumevano — odsustvo eksplicitnog podešavanja se
    tumači kao zabrana; stranica se ni ne prikazuje u meniju)
  - `citanje` (vidi i pregleda, ne može ništa da upiše — svaki POST na
    toj stranici se centralno blokira u `index.php`, pre nego što ijedan
    modul i takne bazu)
  - `puno` (potpun pristup — dodavanje, izmena, brisanje)

Svaka nova organizacija automatski dobija sistemsku rolu **"Administrator"**
(`roles.is_system = TRUE`) sa `puno` pravom na sve trenutne stranice — ne
može se obrisati niti joj se prava mogu menjati, da nijedna firma ne ostane
"zaključana" bez ijednog naloga sa punim pristupom. Dodatne, custom role
(npr. "Samo čitanje", "Koordinator rizika") se prave i uređuju na
`?page=role-pristup`.

Korisnici unutar organizacije se dodaju/aktiviraju/deaktiviraju na
`?page=korisnici`.

Nove organizacije kreira isključivo super admin, na `?page=organizacije` —
nema javne "Registruj firmu" forme.

### Podrazumevani nalozi

| Nalog | Email | Lozinka |
|---|---|---|
| Super admin | `superadmin@infosecrelax.local` | `AiSSPhTjXRFZox6eXZfH` |
| Demo (organizacija "Moja firma", rola Administrator) | `demo@demo.local` | `AiSSPhTjXRFZox6eXZfH` |

**Obavezno promeniti/ukloniti oba naloga** ako instalacija ikad postane
dostupna van lokalnog razvojnog okruženja — lozinke su namerno trivijalne
ili javno zapisane u ovom README-u.

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

Kredencijali baze (razvojno okruženje, videti `docker-compose.yml`):

| | |
|---|---|
| Baza | `infosecrelax` |
| Korisnik | `infosecrelax_user` |
| Lozinka | `infosecrelax_password` |

Demo podaci za organizaciju "Moja firma" (`db/demo-data.sql`) se učitavaju
automatski, jednom, kao deo `db/init.sql` — samo pri SVEŽOJ instalaciji
(nov, prazan Docker volume). Više ne postoji dugme za ručni uvoz u
aplikaciji (videti "Poznata ograničenja" iznad).

## Struktura projekta

```
InfoSecRelax/
├── Dockerfile
├── docker-compose.yml
├── db/
│   ├── init.sql                    (kompletna šema + seed podaci + RBAC + demo nalog + demo podaci - pokreće se samo na SVEŽOJ instalaciji)
│   └── demo-data.sql               (demo podaci za organizaciju id=1, sadržaj se nalepi na kraj init.sql)
└── src/
    ├── index.php                     (jedina ulazna tačka / ruter - i autentifikacija i RBAC)
    ├── config/
    │   ├── menu.php                   (struktura navigacije - jedini izvor istine za meni)
    │   ├── database.php               (PDO konekcija, ensureDefaultOrganization() - vraća organization_id ulogovanog korisnika)
    │   └── auth.php                   (sesije, login/logout, RBAC provere - requirePagePermission(), requireSuperAdmin())
    ├── includes/
    │   ├── header.php / footer.php / placeholder.php
    │   ├── csrf.php                   (CSRF token po sesiji - csrfField()/csrfRequireValid())
    │   ├── pristup-odbijen.php        (deljeni fragment za odbijen RBAC pristup)
    │   ├── document-helpers.php       (deljena logika za documents/document_versions)
    │   ├── help-content.php           (getHelpContent/saveHelpContent - čitanje i upis pomoći)
    │   ├── help-modal.php             (deljeni modal pomoći, view-only, uključuje se na svakoj stranici koja ima Pomoć dugme)
    │   ├── demo-import.php            (neiskorišćeno - videti "Poznata ograničenja")
    │   └── *-card.php                 (po jedan reusable prikaz-fragment po entitetu)
    ├── modules/                       (po jedan fajl po stavci menija - {slug}.php)
    │   ├── prijava.php                (login - dostupno bez prijave)
    │   ├── organizacije.php           (super admin - kreiranje novih firmi)
    │   ├── korisnici.php              (org admin - korisnici unutar organizacije)
    │   ├── role-pristup.php           (org admin - custom role i prava po stranici)
    │   └── pomoc-uredjivanje.php      (super admin - centralno uređivanje sadržaja pomoći, van standarda)
    ├── bin/
    │   └── create-super-admin.php     (CLI - dodatni super admin nalozi / reset lozinke postojećeg)
    └── assets/
        └── style.css
```

## Pregled modula

| Grupa | Modul | Klauzula / kontrola | Dostupno |
|---|---|---|---|
| *(van grupa)* | Pregled sistema | 4.4 | organizacija |
| Kontekst i obim | Kontekst organizacije | 4.1 | organizacija |
| Kontekst i obim | Zainteresovane strane | 4.2 | organizacija |
| Kontekst i obim | Obim ISMS-a | 4.3 | organizacija |
| Rizik i planiranje | Popis sredstava | A.5.9 | organizacija |
| Rizik i planiranje | Procena rizika | 6.1.2 | organizacija |
| Rizik i planiranje | Izjava o primenljivosti | 6.1.3 / Aneks A | organizacija |
| Rizik i planiranje | Ciljevi bezbednosti | 6.2 | organizacija |
| Rizik i planiranje | Planiranje promena | 6.3 | organizacija |
| Liderstvo | Liderstvo i posvećenost | 5.1 | organizacija |
| Liderstvo | Politike bezbednosti | 5.2 / A.5.1 | organizacija |
| Liderstvo | Zaposleni i saradnici | A.6 | organizacija |
| Liderstvo | Uloge i odgovornosti | 5.3 / A.5.2 | organizacija |
| Podrška | Resursi | 7.1 | organizacija |
| Podrška | Kompetentnost i obuka | 7.2-7.3 | organizacija |
| Podrška | Komunikacija | 7.4 | organizacija |
| Podrška | Dokumenti | 7.5 | organizacija |
| Operacija | Sistemi i pristup | 8.1 / A.8.1-8.5 | organizacija |
| Operacija | Dobavljači | A.5.19-5.23 | organizacija |
| Operacija | Fizička bezbednost | A.7 | organizacija |
| Operacija | Upravljanje incidentima | A.5.24-5.28 | organizacija |
| Operacija | Kontinuitet poslovanja | A.5.29-5.30 | organizacija |
| Operacija | Usklađenost | A.5.31-5.36 | organizacija |
| Ocenjivanje učinka | Pokazatelji i merenje | 9.1 | organizacija |
| Ocenjivanje učinka | Interni audit | 9.2 | organizacija |
| Ocenjivanje učinka | Pregled menadžmenta | 9.3 | organizacija |
| Unapređenje | Stalno unapređenje | 10.1 | organizacija |
| Unapređenje | Korektivne mere | 10.2 | organizacija |
| Alati | Korisnici | *(van standarda)* | organizacija |
| Alati | Role i prava pristupa | *(van standarda)* | organizacija |
| *(van grupa/menija)* | Organizacije | *(van standarda)* | super admin |
| *(van grupa/menija)* | Uređivanje pomoći | *(van standarda)* | super admin |

Kolona "Dostupno" pokazuje ko uopšte MOŽE da vidi stranicu (rutiranje u
`index.php`) — unutar "organizacija" nivoa, koju od ovih stranica konkretna
rola sme da vidi/menja dodatno određuje `role_page_permissions` (videti
"Autentifikacija, multi-tenant i kontrola pristupa" iznad).

Redosled grupa "Rizik i planiranje" pre "Liderstvo" je namerna odluka
(obrnuto od brojeva klauzula 5 i 6) — videti napomenu u `config/menu.php`.

## Arhitektonski obrasci

- **Ruter je data-driven.** `config/menu.php` vraća niz stavki menija;
  `index.php` čita `?page=slug`, i ako postoji `modules/{slug}.php` učitava
  ga, inače prikazuje `includes/placeholder.php`. Ruter takođe grana po
  stanju autentifikacije (nije ulogovan / super admin / organizacioni
  korisnik) i po RBAC pravu za traženu stranicu — videti komentar na vrhu
  `index.php`.
- **Post-Redirect-Get** na svakom upisu — obradi POST, uradi redirect, tek
  onda učitaj podatke za prikaz.
- **PDO isključivo sa pripremljenim upitima**, `PDO::ATTR_EMULATE_PREPARES
  => false`.
- **Sav tekst ka korisniku ide kroz `htmlspecialchars()`.**
- **CSRF token po sesiji** (`includes/csrf.php`) — svaka forma koja upisuje
  uključuje `csrfField()`, svaka POST obrada počinje sa
  `csrfRequireValid()`.
- **`config/database.php` funkcije su omotane u `function_exists()`
  provere** — `config/auth.php` učitava taj fajl preko `require_once`
  jednom, na početku, dok svaki modul i dalje samostalno radi plain
  `require` istog fajla na svom vrhu; bez ove provere bi drugi `require`
  pukao na "Cannot redeclare".
- **`ensureDefaultOrganization($pdo)` vraća organization_id STVARNOG
  ulogovanog korisnika** (preko `currentUser()`), ne više uvek `1` — isto
  ime funkcije, isti poziv u svakom modulu kao i pre RBAC-a, samo ispravan
  sadržaj. Ovim su svi postojeći moduli automatski postali multi-tenant,
  bez pojedinačne izmene.
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
  `page_slug`, deljena kroz CELU instalaciju, nije multi-tenant), čita se
  preko `getHelpContent()`, prikazuje kroz deljeni `includes/help-modal.php`
  (samo prikaz), uređuje isključivo preko `?page=pomoc-uredjivanje` — sad
  dostupno samo super adminu, baš zato što sadržaj nije vezan za jednu
  organizaciju.
- **Bočna traka (`.sidebar`) skroluje nezavisno od glavnog sadržaja**
  (`position: sticky` + ograničena visina), a skripta koja dovodi aktivnu
  stavku menija u vidokrug je namerno ugrađena u `header.php` odmah posle
  menija (ne u spoljnom fajlu na dnu stranice) da bi se izvršila pre prvog
  iscrtavanja i izbegla vidljiv "skok" sadržaja. Isti fajl prikazuje i
  email ulogovanog korisnika sa linkom "Odjava", ispod loga.
- **Dva tipa modula:**
  - Većina su **CRUD registri** (dodaj/uredi/prikaži/obriši, ponegde i
    status).
  - Nekoliko klauzula (4.4, 5.1, 10.1) nema svoju tabelu u šemi — narativne
    su, pa su implementirane kao **read-only dashboard-i** koji sažimaju
    dokaze iz podataka koji se već beleže na drugim mestima.

## Sledeći koraci

Predlog redosleda, po prioritetu:

1. **Forma za promenu lozinke**, dostupna svakom ulogovanom korisniku za
   sopstveni nalog.
2. **Sakrivanje dugmadi za dodavanje/izmenu/brisanje** u postojećim ISMS
   modulima kad je `$currentPermission !== 'puno'` — upis je već blokiran
   centralno, ovo je čisto UX poboljšanje, modul po modul.
3. Opciono: obrisati neiskorišćeni `includes/demo-import.php` i redove sa
   `page_slug = 'pomoc-uredjivanje'` iz `role_page_permissions` (ostaci od
   pre nego što je ta stranica postala isključivo super-admin).
4. Opciono: prebaciti preostala ugrađena ugnježdena dodavanja (prisustvo na
   obuci i sl.) na isti modal obrazac, radi potpune doslednosti.
