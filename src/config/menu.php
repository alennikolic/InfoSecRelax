<?php
/**
 * Struktura menija InfoSecRelax aplikacije.
 *
 * Redosled stavki namerno prati redosled uspostavljanja ISO/IEC 27001
 * standarda - korisnik koji prvi put uvodi standard može ići kroz meni
 * od vrha ka dnu, tačno onim redom kojim bi i sam sistem gradio.
 *
 * Svaka stavka:
 *   slug     - jedinstven identifikator; koristi se u URL-u (?page=slug)
 *              i kao naziv fajla stranice (modules/{slug}.php)
 *   title    - naziv prikazan u meniju i u naslovu stranice
 *   iso_ref  - referenca na klauzulu ili kontrolu standarda
 *   group    - naziv sekcije menija za grupisanje srodnih stavki
 *
 * Dodavanje nove stavke u meni = dodavanje jednog reda ovde. Ruter u
 * index.php i layout u includes/header.php automatski je prikazuju,
 * bez potrebe za izmenom bilo čega drugog.
 */

return [

    // --- Kontekst i obim (Poglavlje 4) ---
    ['slug' => 'kontekst',                 'title' => 'Kontekst organizacije',      'iso_ref' => 'Klauzula 4.1',              'group' => 'Kontekst i obim'],
    ['slug' => 'zainteresovane-strane',    'title' => 'Zainteresovane strane',      'iso_ref' => 'Klauzula 4.2',              'group' => 'Kontekst i obim'],
    ['slug' => 'obim',                     'title' => 'Obim ISMS-a',                'iso_ref' => 'Klauzula 4.3',              'group' => 'Kontekst i obim'],

    // --- Liderstvo (Poglavlje 5) ---
    ['slug' => 'politike',                 'title' => 'Politike bezbednosti',       'iso_ref' => 'Klauzula 5.2 / A.5.1',      'group' => 'Liderstvo'],
    ['slug' => 'uloge',                    'title' => 'Uloge i odgovornosti',       'iso_ref' => 'Klauzula 5.3 / A.5.2',      'group' => 'Liderstvo'],

    // --- Rizik i planiranje (Poglavlje 6) ---
    ['slug' => 'sredstva',                 'title' => 'Popis sredstava',            'iso_ref' => 'A.5.9',                     'group' => 'Rizik i planiranje'],
    ['slug' => 'procena-rizika',           'title' => 'Procena rizika',             'iso_ref' => 'Klauzula 6.1.2',            'group' => 'Rizik i planiranje'],
    ['slug' => 'izjava-primenljivosti',    'title' => 'Izjava o primenljivosti',    'iso_ref' => 'Klauzula 6.1.3 / Aneks A',  'group' => 'Rizik i planiranje'],
    ['slug' => 'ciljevi',                  'title' => 'Ciljevi bezbednosti',        'iso_ref' => 'Klauzula 6.2',              'group' => 'Rizik i planiranje'],
    ['slug' => 'promene',                  'title' => 'Planiranje promena',         'iso_ref' => 'Klauzula 6.3',              'group' => 'Rizik i planiranje'],

    // --- Podrška (Poglavlje 7) ---
    ['slug' => 'kompetentnost',            'title' => 'Kompetentnost i obuka',      'iso_ref' => 'Klauzula 7.2-7.3',          'group' => 'Podrška'],
    ['slug' => 'komunikacija',             'title' => 'Komunikacija',               'iso_ref' => 'Klauzula 7.4',              'group' => 'Podrška'],
    ['slug' => 'dokumenti',                'title' => 'Dokumenti',                  'iso_ref' => 'Klauzula 7.5',              'group' => 'Podrška'],

    // --- Operacija (Poglavlje 8) ---
    ['slug' => 'sistemi-pristup',          'title' => 'Sistemi i pristup',          'iso_ref' => 'Klauzula 8.1 / A.8.1-8.5',  'group' => 'Operacija'],
    ['slug' => 'dobavljaci',               'title' => 'Dobavljači',                 'iso_ref' => 'A.5.19-5.23',               'group' => 'Operacija'],
    ['slug' => 'fizicka-bezbednost',       'title' => 'Fizička bezbednost',         'iso_ref' => 'A.7',                       'group' => 'Operacija'],
    ['slug' => 'incidenti',                'title' => 'Upravljanje incidentima',    'iso_ref' => 'A.5.24-5.28',               'group' => 'Operacija'],

    // --- Ocenjivanje učinka (Poglavlje 9) ---
    ['slug' => 'pokazatelji',              'title' => 'Pokazatelji i merenje',      'iso_ref' => 'Klauzula 9.1',              'group' => 'Ocenjivanje učinka'],
    ['slug' => 'interni-audit',            'title' => 'Interni audit',              'iso_ref' => 'Klauzula 9.2',              'group' => 'Ocenjivanje učinka'],
    ['slug' => 'pregled-menadzmenta',      'title' => 'Pregled menadžmenta',        'iso_ref' => 'Klauzula 9.3',              'group' => 'Ocenjivanje učinka'],

    // --- Unapređenje (Poglavlje 10) ---
    ['slug' => 'unapredjenje',             'title' => 'Stalno unapređenje',         'iso_ref' => 'Klauzula 10.1',             'group' => 'Unapređenje'],
    ['slug' => 'korektivne-mere',          'title' => 'Korektivne mere',            'iso_ref' => 'Klauzula 10.2',             'group' => 'Unapređenje'],

];
