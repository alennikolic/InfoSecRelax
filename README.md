# InfoSecRelax

Web aplikacija koja malim i srednjim preduzećima pomaže da implementiraju i
kasnije održavaju sistem upravljanja bezbednošću informacija (ISMS) prema
ISO/IEC 27001. Meni aplikacije prati tačan redosled uspostavljanja standarda
— korisnik ide kroz kontekst, liderstvo, planiranje/rizik, podršku,
operaciju, ocenjivanje učinka i unapređenje, istim redosledom kojim bi i sam
sistem gradio u firmi.

## Status

Svih **28 stavki menija** ima realnu funkcionalnost — dodavanje, pregled i
brisanje podataka, sa promenom statusa gde je to prirodno (rizici, ciljevi,
korektivne mere...). Nema više "u pripremi" ekrana.

Poznata ograničenja, namerno ostavljena za kasnije:

- **Nema CSRF zaštite** na formama.
- **Nema autentifikacije ni registracije** — `organization_id` je uvek `1`
  (videti `ensureDefaultOrganization()` u `src/config/database.php`).
- Većina modula podržava samo dodavanje, brisanje i (gde ima smisla) promenu
  statusa — nema pravog uređivanja već unetih polja. Ispravka se trenutno
  radi brisanjem i ponovnim unosom.
- Nekoliko tabela iz šeme nema svoju stavku menija (namerne odluke, ne
  propusti): `equipment`, `storage_media`, `personnel_screening`,
  `confidentiality_agreements`, `disciplinary_actions`. Takođe,
  `legal_requirements` je zamenjena širim `compliance_items` registrom
  (A.5.31-5.36) i ostaje u šemi neiskorišćena.

## Tehnologije

- **PHP 8.2** (Apache), bez frameworka — čist proceduralni PHP po modulu
- **MySQL 8.0**
- **Docker / docker-compose** za lokalno pokretanje
- Bez JavaScript build-a — samo čist HTML/CSS uz par `confirm()` dijaloga

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

## Struktura projekta

```
InfoSecRelax/
├── Dockerfile
├── docker-compose.yml
├── db/
│   ├── init.sql                (kompletna šema + seed podaci - pokreće se samo na SVEŽOJ instalaciji)
│   └── demo-data.sql              (demo podaci)
└── src/
    ├── index.php                 (jedina ulazna tačka / ruter)
    ├── config/
    │   ├── menu.php               (struktura navigacije - jedini izvor istine za meni)
    │   └── database.php           (PDO konekcija, ensureDefaultOrganization())
    ├── includes/
    │   ├── header.php / footer.php / placeholder.php
    │   ├── document-helpers.php   (deljena logika za documents/document_versions)
    │   └── *-card.php             (po jedan reusable prikaz-fragment po entitetu)
    ├── modules/                   (po jedan fajl po stavci menija - {slug}.php)
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
| Liderstvo | Liderstvo i posvećenost | 5.1 |
| Liderstvo | Politike bezbednosti | 5.2 / A.5.1 |
| Liderstvo | Zaposleni i saradnici | A.6 |
| Liderstvo | Uloge i odgovornosti | 5.3 / A.5.2 |
| Rizik i planiranje | Popis sredstava | A.5.9 |
| Rizik i planiranje | Procena rizika | 6.1.2 |
| Rizik i planiranje | Izjava o primenljivosti | 6.1.3 / Aneks A |
| Rizik i planiranje | Ciljevi bezbednosti | 6.2 |
| Rizik i planiranje | Planiranje promena | 6.3 |
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
- **Dva tipa modula:**
  - Većina su **CRUD registri** (dodaj/prikaži/obriši, ponegde i status).
  - Nekoliko klauzula (4.4, 5.1, 10.1) nema svoju tabelu u šemi — narativne
    su, pa su implementirane kao **read-only dashboard-i** koji sažimaju
    dokaze iz podataka koji se već beleže na drugim mestima, umesto da se
    izmišlja nova tabela bez potrebe.

## Sledeći koraci

- CSRF zaštita na formama (najavljena, još nije urađena).
- Autentifikacija/registracija firmi.
- Uređivanje postojećih zapisa (trenutno samo dodaj/obriši/status).
