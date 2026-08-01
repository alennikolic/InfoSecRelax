-- db/demo-data.sql
--
-- Demo podaci za "Bilans Plus" - knjigovodstvenu agenciju - koji
-- popunjavaju svih 28 modula realnim, međusobno povezanim primerima za
-- potrebe prezentacije i obuke.
--
-- OVAJ FAJL BRIŠE SVE POSTOJEĆE TENANT PODATKE (sve tabele osim
-- organizations, annex_a_controls, users i tabela koje nijedan modul
-- trenutno ne koristi - equipment, storage_media, backup_records,
-- personnel_screening, confidentiality_agreements, disciplinary_actions,
-- legal_requirements) i zamenjuje ih ovim demo skupom. Namerno je tako
-- napravljen - i ručna primena i dugme na pregled-sistema.php treba da
-- daju isti, predvidiv, čist rezultat svaki put, ne da se demo podaci
-- gomilaju preko postojećih test unosa.
--
-- RUČNA PRIMENA (isto što radi i dugme "Uvezi demo podatke"):
--
--   docker exec -i InfoSecRelax_db mysql \
--       -u infosecrelax_user -pinfosecrelax_password infosecrelax \
--       < db/demo-data.sql
--
-- Pretpostavlja da su migracije 001-003 već primenjene (isms_resources,
-- continuity_plans, compliance_items moraju postojati).

-- =====================================================================
-- KORAK 1: Čišćenje postojećih tenant podataka
-- =====================================================================

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE policy_acknowledgments;
TRUNCATE TABLE document_versions;
TRUNCATE TABLE policies;
TRUNCATE TABLE documents;
TRUNCATE TABLE interested_party_requirements;
TRUNCATE TABLE interested_parties;
TRUNCATE TABLE scope_exclusions;
TRUNCATE TABLE third_party_dependencies;
TRUNCATE TABLE scope_statements;
TRUNCATE TABLE context_factors;
TRUNCATE TABLE roles_responsibilities;
TRUNCATE TABLE risk_treatments;
TRUNCATE TABLE risks;
TRUNCATE TABLE risk_criteria;
TRUNCATE TABLE objectives;
TRUNCATE TABLE planned_changes;
TRUNCATE TABLE isms_resources;
TRUNCATE TABLE competence_records;
TRUNCATE TABLE training_attendance;
TRUNCATE TABLE training_sessions;
TRUNCATE TABLE communications_plan;
TRUNCATE TABLE access_grants;
TRUNCATE TABLE systems;
TRUNCATE TABLE supplier_reviews;
TRUNCATE TABLE suppliers;
TRUNCATE TABLE physical_locations;
TRUNCATE TABLE corrective_actions;
TRUNCATE TABLE security_events;
TRUNCATE TABLE metric_measurements;
TRUNCATE TABLE metrics;
TRUNCATE TABLE audit_findings;
TRUNCATE TABLE internal_audits;
TRUNCATE TABLE management_review_actions;
TRUNCATE TABLE management_reviews;
TRUNCATE TABLE continuity_plans;
TRUNCATE TABLE compliance_items;
TRUNCATE TABLE statement_of_applicability;
TRUNCATE TABLE assets;
TRUNCATE TABLE personnel;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- KORAK 2: Organizacija
-- =====================================================================

UPDATE organizations SET
    name = 'Bilans Plus knjigovodstvena agencija d.o.o.',
    industry = 'Knjigovodstvene i računovodstvene usluge',
    employee_count = 11,
    certification_status = 'priprema',
    certification_date = NULL,
    recertification_due = NULL
WHERE id = 1;

-- =====================================================================
-- KORAK 3: Osoblje (personnel) - videti zaposleni.php
-- =====================================================================

INSERT INTO personnel (id, organization_id, full_name, email, employment_type, job_title, start_date, end_date, is_active) VALUES
(1,  1, 'Ana Jovanović',        'ana.jovanovic@bilansplus.rs',        'zaposleni',           'Direktorka',                              '2018-03-01', NULL,         TRUE),
(2,  1, 'Marko Petrović',       'marko.petrovic@bilansplus.rs',       'zaposleni',           'Rukovodilac IT-a i koordinator ISMS-a',   '2019-06-15', NULL,         TRUE),
(3,  1, 'Milica Stanković',     'milica.stankovic@bilansplus.rs',     'zaposleni',           'Šef knjigovodstva',                       '2018-09-01', NULL,         TRUE),
(4,  1, 'Nemanja Ilić',         'nemanja.ilic@bilansplus.rs',         'zaposleni',           'Knjigovođa',                               '2021-02-01', NULL,         TRUE),
(5,  1, 'Jovana Đorđević',      'jovana.djordjevic@bilansplus.rs',    'zaposleni',           'Knjigovođa',                               '2022-04-15', NULL,         TRUE),
(6,  1, 'Stefan Nikolić',       'stefan.nikolic@bilansplus.rs',       'zaposleni',           'Knjigovođa',                               '2023-01-10', NULL,         TRUE),
(7,  1, 'Tijana Radovanović',   'tijana.radovanovic@bilansplus.rs',   'zaposleni',           'Obračun zarada',                          '2020-11-01', NULL,         TRUE),
(8,  1, 'Vladimir Kovačević',   'vladimir.kovacevic@bilansplus.rs',   'honorarni_saradnik',  'Administrator sistema',                   '2022-01-01', NULL,         TRUE),
(9,  1, 'Ivana Marković',       'ivana.markovic@bilansplus.rs',       'honorarni_saradnik',  'Pravni savetnik',                         '2021-05-01', NULL,         TRUE),
(10, 1, 'Dušan Živković',       'dusan.zivkovic@bilansplus.rs',       'zaposleni',           'Knjigovođa',                               '2019-03-01', '2025-11-30', FALSE),
(11, 1, 'Milan Todorović',      'milan.todorovic@bilansplus.rs',      'zaposleni',           'Praktikant',                               '2026-06-01', NULL,         TRUE);

-- =====================================================================
-- KORAK 4: Kontekst organizacije (4.1) - videti kontekst.php
-- =====================================================================

INSERT INTO context_factors (id, organization_id, factor_type, description, category) VALUES
(1, 1, 'spoljni',    'Zakon o zaštiti podataka o ličnosti zahteva posebnu pažnju pri obradi finansijskih i ličnih podataka klijenata.', 'zakonski'),
(2, 1, 'spoljni',    'Sve veći broj klijenata traži da agencija poseduje ISO 27001 sertifikat kao uslov za poveravanje knjigovodstva.', 'trziste'),
(3, 1, 'spoljni',    'Porast phishing napada usmerenih na knjigovodstvene agencije radi krađe pristupa bankarskim nalozima klijenata.', 'tehnologija'),
(4, 1, 'spoljni',    'Poreska uprava zahteva elektronsko dostavljanje poreskih prijava preko bezbednih kanala.', 'zakonski'),
(5, 1, 'unutrasnji', 'Veći deo tima radi hibridno, uključujući rad od kuće nekoliko dana nedeljno.', 'organizacija'),
(6, 1, 'unutrasnji', 'Firma koristi cloud računovodstveni softver za obradu podataka klijenata.', 'tehnologija'),
(7, 1, 'unutrasnji', 'Ograničeni IT resursi - spoljni saradnik pokriva sistemsku administraciju sa nepunim radnim vremenom.', 'organizacija');

-- =====================================================================
-- KORAK 5: Zainteresovane strane (4.2) - videti zainteresovane-strane.php
-- =====================================================================

INSERT INTO interested_parties (id, organization_id, name, party_type) VALUES
(1, 1, 'Zaposleni',                                      'interna'),
(2, 1, 'Rukovodstvo',                                     'interna'),
(3, 1, 'Klijenti',                                        'eksterna'),
(4, 1, 'Poreska uprava',                                  'eksterna'),
(5, 1, 'Banka - dobavljač servisa e-bankarstva',          'eksterna'),
(6, 1, 'Dobavljač cloud računovodstvenog softvera',       'eksterna');

INSERT INTO interested_party_requirements (interested_party_id, requirement, addressed_by_isms) VALUES
(1, 'Očekuju jasne procedure i obuku kako da bezbedno rukuju podacima klijenata.', TRUE),
(2, 'Zahteva da bezbednosne mere ne usporavaju svakodnevni rad sa klijentima.', TRUE),
(3, 'Očekuju da njihovi finansijski podaci budu zaštićeni od neovlašćenog pristupa i gubitka.', TRUE),
(3, 'Očekuju brz odgovor u slučaju bezbednosnog incidenta koji ih se tiče.', TRUE),
(4, 'Zahteva tačnost i pravovremenost dostavljenih poreskih prijava.', FALSE),
(5, 'Zahteva dvofaktorsku autentifikaciju za pristup nalozima klijenata.', TRUE),
(6, 'Ugovor zahteva potpisan DPA i definisane bezbednosne standarde.', TRUE);

-- =====================================================================
-- KORAK 6: Obim ISMS-a (4.3) - videti obim.php
-- =====================================================================

INSERT INTO scope_statements (id, organization_id, scope_text, version, approved_by, approved_at, effective_from, is_current) VALUES
(1, 1, 'ISMS obuhvata sve informacione sisteme, osoblje i procese koji podržavaju pružanje knjigovodstvenih i računovodstvenih usluga klijentima agencije Bilans Plus, uključujući obradu finansijskih podataka, obračun zarada, poreske prijave i komunikaciju sa klijentima, u kancelariji u Beogradu i pri radu od kuće zaposlenih.', '1.0', 1, '2026-02-01', '2026-02-01', TRUE);

INSERT INTO scope_exclusions (scope_statement_id, excluded_item, justification) VALUES
(1, 'Lični uređaji zaposlenih korišćeni isključivo u privatne svrhe', 'Van kontrole agencije i ne koriste se za pristup podacima klijenata.');

INSERT INTO third_party_dependencies (scope_statement_id, description, managed_via) VALUES
(1, 'Hosting cloud računovodstvenog softvera kod eksternog dobavljača.', 'Ugovor o nivou usluge (SLA) i ugovor o obradi podataka (DPA)'),
(1, 'Bankarske usluge za elektronsko plaćanje u ime klijenata.', 'Ugovor sa bankom, dvofaktorska autentifikacija');

-- =====================================================================
-- KORAK 7: Dokumenti + verzije (7.5) - videti dokumenti.php
-- Prvih pet je politika (poveznih u koraku 8), ostali su samostalni.
-- =====================================================================

INSERT INTO documents (id, organization_id, title, doc_type, classification, current_version, owner_id, approved_by, approved_at, next_review_due) VALUES
(1, 1, 'Politika bezbednosti informacija',                    'politika',  'interno',    '1.0', 2, 1, '2026-02-10', '2027-02-10'),
(2, 1, 'Politika kontrole pristupa',                          'politika',  'interno',    '1.0', 2, 1, '2026-02-10', '2027-02-10'),
(3, 1, 'Politika bezbedne upotrebe interneta i e-pošte',      'politika',  'interno',    '1.0', 2, 1, '2026-02-10', '2027-02-10'),
(4, 1, 'Politika rada na daljinu',                             'politika',  'interno',    '1.0', 2, 1, '2026-02-10', '2027-02-10'),
(5, 1, 'Politika izrade rezervnih kopija',                     'politika',  'interno',    '1.1', 8, 1, '2026-05-01', '2027-05-01'),
(6, 1, 'Procedura za rezervne kopije',                         'procedura', 'interno',    '1.0', 8, 2, '2026-03-01', '2027-03-01'),
(7, 1, 'Registar rizika',                                      'registar',  'poverljivo', '1.0', 2, NULL, NULL, NULL),
(8, 1, 'Zapisnik sa osnivačkog sastanka ISMS tima',            'zapisnik',  'interno',    '1.0', 2, NULL, NULL, NULL),
(9, 1, 'Procedura za offboarding zaposlenih',                  'procedura', 'interno',    '1.0', 8, 2, '2026-04-01', '2027-04-01');

INSERT INTO document_versions (document_id, version_number, changed_by, change_summary, created_at) VALUES
(1, '1.0', 1, 'Prva verzija dokumenta.', '2026-02-10 09:00:00'),
(2, '1.0', 1, 'Prva verzija dokumenta.', '2026-02-10 09:05:00'),
(3, '1.0', 1, 'Prva verzija dokumenta.', '2026-02-10 09:10:00'),
(4, '1.0', 1, 'Prva verzija dokumenta.', '2026-02-10 09:15:00'),
(5, '1.0', 1, 'Prva verzija dokumenta.', '2026-02-10 09:20:00'),
(5, '1.1', 8, 'Dodat postupak za mesečno testiranje obnavljanja rezervnih kopija.', '2026-05-01 14:00:00'),
(6, '1.0', 8, 'Prva verzija dokumenta.', '2026-03-01 10:00:00'),
(7, '1.0', 2, 'Prva verzija dokumenta.', '2026-02-05 11:00:00'),
(8, '1.0', 2, 'Prva verzija dokumenta.', '2026-01-20 16:00:00'),
(9, '1.0', 8, 'Prva verzija dokumenta.', '2026-04-01 09:00:00');

-- =====================================================================
-- KORAK 8: Politike (5.2 / A.5.1) - videti politike.php
-- =====================================================================

INSERT INTO policies (id, organization_id, document_id, policy_type, topic, acknowledgment_required) VALUES
(1, 1, 1, 'opsta',    NULL,                  TRUE),
(2, 1, 2, 'tematska', 'kontrola_pristupa',   TRUE),
(3, 1, 3, 'tematska', 'prihvatljiva_upotreba', TRUE),
(4, 1, 4, 'tematska', 'rad_na_daljinu',      TRUE),
(5, 1, 5, 'tematska', 'backup',              FALSE);

-- Opšta politika: potvrdilo je 9 od 10 aktivnih (Milan, praktikant od juna, još nije).
INSERT INTO policy_acknowledgments (policy_id, personnel_id, acknowledged_at) VALUES
(1, 1, '2026-02-12 09:00:00'), (1, 2, '2026-02-12 09:05:00'), (1, 3, '2026-02-12 10:00:00'),
(1, 4, '2026-02-13 08:30:00'), (1, 5, '2026-02-13 08:45:00'), (1, 6, '2026-02-13 09:00:00'),
(1, 7, '2026-02-13 09:15:00'), (1, 8, '2026-02-14 11:00:00'), (1, 9, '2026-02-14 11:30:00');

-- Politika kontrole pristupa: potvrdio uži krug (oni koji stvarno dodeljuju pristup).
INSERT INTO policy_acknowledgments (policy_id, personnel_id, acknowledged_at) VALUES
(2, 1, '2026-02-15 09:00:00'), (2, 2, '2026-02-15 09:05:00'), (2, 3, '2026-02-15 10:00:00'), (2, 8, '2026-02-15 11:00:00');

-- =====================================================================
-- KORAK 9: Uloge i odgovornosti (5.3 / A.5.2) - videti uloge.php
-- =====================================================================

INSERT INTO roles_responsibilities (id, organization_id, role_name, description, assigned_to, authority_level, related_control_ref) VALUES
(1, 1, 'Koordinator ISMS-a',              'Koordinira sve aktivnosti uspostavljanja i održavanja ISMS-a, priprema materijale za preglede menadžmenta.', 2, 'Ovlašćen da donosi operativne odluke o bezbednosnim merama i koordinira aktivnosti ISMS-a.', 'A.5.2'),
(2, 1, 'Vlasnik podataka klijenata',      'Odgovoran za klasifikaciju i kontrolu pristupa podacima klijenata u računovodstvenom sistemu.', 3, 'Odlučuje ko dobija pristup finansijskim podacima pojedinačnih klijenata.', 'A.5.9'),
(3, 1, 'Administrator sistema',           'Upravlja tehničkom infrastrukturom, korisničkim nalozima i rezervnim kopijama.', 8, 'Ovlašćen za dodelu i ukidanje pristupa sistemima.', 'A.8.2'),
(4, 1, 'Zamenik koordinatora ISMS-a',     'Preuzima odgovornosti koordinatora u slučaju njegovog odsustva.', 3, 'Iste operativne ovlasti kao koordinator, važe samo u odsustvu.', NULL);

-- =====================================================================
-- KORAK 10: Popis sredstava (A.5.9) - videti sredstva.php
-- =====================================================================

INSERT INTO assets (id, organization_id, name, asset_type, description, owner_id, classification) VALUES
(1, 1, 'Baza podataka klijenata u cloud računovodstvenom softveru', 'informacija', 'Sadrži finansijske podatke, kontakt informacije i poslovnu dokumentaciju svih klijenata agencije.', 3, 'strogo_poverljivo'),
(2, 1, 'Radne stanice zaposlenih (laptopovi)',                       'hardver',     'Laptopovi dodeljeni zaposlenima za svakodnevni rad, uključujući rad od kuće.', 8, 'interno'),
(3, 1, 'Server za lokalni backup',                                    'hardver',     'Lokalni server u kancelariji na koji se preslikavaju rezervne kopije podataka.', 8, 'poverljivo'),
(4, 1, 'Nalog za elektronsko bankarstvo klijenata',                   'usluga',      'Pristup e-bankarstvu klijenata radi izvršavanja plaćanja u njihovo ime.', 1, 'strogo_poverljivo'),
(5, 1, 'Softver za obračun zarada',                                    'softver',     'Aplikacija za obračun zarada zaposlenih kod klijenata.', 7, 'poverljivo'),
(6, 1, 'Arhiva potpisanih ugovora sa klijentima',                     'informacija', 'Papirna i skenirana arhiva ugovora o pružanju knjigovodstvenih usluga.', 1, 'poverljivo'),
(7, 1, 'Tim knjigovođa',                                               'ljudi',       'Zaposleni koji imaju svakodnevni pristup finansijskim podacima klijenata.', 3, 'interno');

-- =====================================================================
-- KORAK 11: Kriterijumi i procena rizika (6.1.2) - videti procena-rizika.php
-- =====================================================================

INSERT INTO risk_criteria (id, organization_id, likelihood_scale_max, impact_scale_max, low_threshold_max, medium_threshold_max, notes) VALUES
(1, 1, 5, 5, 6, 14, 'Skala usvojena na osnivačkom sastanku ISMS tima - videti Zapisnik sa osnivačkog sastanka.');

-- risk_score je generisana kolona (likelihood * impact), namerno izostavljena iz INSERT-a.
INSERT INTO risks (id, organization_id, asset_id, title, threat_description, vulnerability_description, likelihood, impact, risk_level, identified_at, review_trigger, status, last_reviewed_at) VALUES
(1, 1, 4, 'Neovlašćen pristup nalogu za e-bankarstvo klijenta',
   'Napadač dobija pristup nalogu za e-bankarstvo putem phishing napada usmerenog na zaposlenog.',
   'Stariji nalozi za e-bankarstvo nemaju uključenu dvofaktorsku autentifikaciju.',
   4, 5, 'visok', '2026-01-15', 'incident', 'u_tretmanu', '2026-04-15'),
(2, 1, 3, 'Gubitak podataka klijenata usled otkaza servera',
   'Hardverski kvar lokalnog servera za backup.',
   'Rezervne kopije se ne testiraju redovno, pa se ne zna da li obnavljanje zaista radi.',
   2, 4, 'srednji', '2026-01-20', 'godisnji_ciklus', 'otvoren', '2026-01-20'),
(3, 1, 1, 'Slanje finansijskih podataka klijenta pogrešnom primaocu e-poštom',
   'Ljudska greška pri slanju osetljivih fajlova e-poštom.',
   'Ne postoji obavezna provera primaoca pre slanja mejlova sa finansijskim prilozima.',
   3, 3, 'srednji', '2026-02-05', 'godisnji_ciklus', 'tretiran', '2026-05-10'),
(4, 1, 1, 'Neovlašćeno korišćenje ličnog USB-a za prenos podataka klijenata',
   'Curenje podataka preko nekontrolisanog prenosivog medija.',
   'Ne postoji formalna politika o korišćenju prenosivih medija.',
   2, 3, 'nizak', '2026-02-10', 'godisnji_ciklus', 'prihvacen', '2026-02-10'),
(5, 1, 1, 'Nedostupnost cloud računovodstvenog softvera',
   'Prekid usluge kod dobavljača cloud softvera.',
   'Ne postoji definisan alternativni način rada tokom prekida usluge.',
   2, 4, 'srednji', '2026-03-01', 'godisnji_ciklus', 'otvoren', '2026-03-01');

INSERT INTO risk_treatments (risk_id, treatment_option, description, owner_id, due_date, status, completed_at) VALUES
(1, 'smanjiti',   'Uvesti obaveznu dvofaktorsku autentifikaciju za sve naloge e-bankarstva klijenata.', 2, '2026-03-01', 'sprovedeno', '2026-03-05'),
(2, 'smanjiti',   'Uspostaviti automatsko mesečno testiranje obnavljanja rezervnih kopija.', 8, '2026-08-01', 'planirano', NULL),
(3, 'smanjiti',   'Uvesti obaveznu proveru primaoca za sve mejlove sa prilozima koji sadrže finansijske podatke.', 3, '2026-04-01', 'sprovedeno', '2026-03-28'),
(4, 'prihvatiti', 'Rizik prihvaćen uz redovno podsećanje na politiku prihvatljive upotrebe pri obukama.', 1, NULL, 'sprovedeno', '2026-02-10'),
(5, 'smanjiti',   'Definisati alternativni ručni postupak rada za slučaj nedostupnosti softvera.', 3, '2026-09-01', 'u_toku', NULL);

-- =====================================================================
-- KORAK 12: Izjava o primenljivosti (6.1.3 / Aneks A) - videti izjava-primenljivosti.php
-- Bootstrap za svih 93 kontrole, pa ciljano ažuriranje reprezentativnog
-- podskupa da brojke na dashboard-ima izgledaju organski, ne uniformno.
-- =====================================================================

INSERT INTO statement_of_applicability (organization_id, control_id, is_applicable, justification, implementation_status)
SELECT 1, id, TRUE,
       'Primenljivo - firma obrađuje finansijske i lične podatke klijenata i zaposlenih u okviru pružanja knjigovodstvenih usluga.',
       'u_toku'
FROM annex_a_controls;

-- Kontrole vezane za razvoj sopstvenog softvera - agencija koristi gotova
-- komercijalna rešenja, ne razvija sopstveni softver.
UPDATE statement_of_applicability soa
INNER JOIN annex_a_controls c ON c.id = soa.control_id
SET soa.is_applicable = FALSE,
    soa.justification = 'Nije primenljivo - firma ne razvija sopstveni softver, koristi gotova komercijalna i cloud rešenja.',
    soa.implementation_status = 'nije_zapoceto'
WHERE soa.organization_id = 1
  AND c.control_ref IN ('8.4','8.25','8.26','8.27','8.28','8.29','8.30','8.31');

-- Kontrole već vidljivo implementirane kroz ostatak demo podataka.
UPDATE statement_of_applicability soa
INNER JOIN annex_a_controls c ON c.id = soa.control_id
SET soa.implementation_status = 'implementirano',
    soa.owner_id = 2,
    soa.last_reviewed_at = '2026-06-01'
WHERE soa.organization_id = 1
  AND c.control_ref IN (
      '5.1','5.2','5.3','5.9','5.10','5.12','5.15','5.16','5.17','5.18',
      '5.19','5.20','5.24','5.25','5.26','5.27','5.28','5.29','5.30',
      '7.4','8.1','8.2','8.3','8.5','8.7','8.13','8.24'
  );

-- Kontrole koje realno još nisu ni započete kod male agencije u pripremi.
UPDATE statement_of_applicability soa
INNER JOIN annex_a_controls c ON c.id = soa.control_id
SET soa.implementation_status = 'nije_zapoceto'
WHERE soa.organization_id = 1
  AND c.control_ref IN ('5.7','5.23','8.9','8.16','8.20','8.34');

-- =====================================================================
-- KORAK 13: Ciljevi bezbednosti (6.2) - videti ciljevi.php
-- =====================================================================

INSERT INTO objectives (id, organization_id, title, linked_risk_id, what_will_be_done, resources_required, owner_id, due_date, evaluation_method, status) VALUES
(1, 1, 'Smanjiti vreme ukidanja pristupa nakon odlaska zaposlenog', NULL,
   'Uvesti checklistu za offboarding koja uključuje ukidanje pristupa u roku od 24h.',
   'Nekoliko sati administratora sistema po slučaju.', 8, '2026-09-01',
   'Pokazatelj "vreme ukidanja pristupa nakon odlaska", cilj ispod 24h u proseku.', 'u_toku'),
(2, 1, 'Uvesti dvofaktorsku autentifikaciju na svim kritičnim nalozima', 1,
   'Uključiti 2FA na e-bankarstvu, cloud računovodstvenom softveru i e-pošti.',
   'Podešavanje kod dobavljača, kratka obuka zaposlenih.', 2, '2026-03-15',
   'Provera podešavanja na svakom kritičnom nalogu.', 'ostvaren'),
(3, 1, 'Sprovesti obuku o prepoznavanju phishing napada za sve zaposlene', NULL,
   'Organizovati internu radionicu sa primerima stvarnih pokušaja prevare.',
   'Pola radnog dana za pripremu i održavanje.', 2, '2026-02-28',
   'Prisustvo evidentirano za sve aktivne zaposlene.', 'ostvaren'),
(4, 1, 'Testirati plan oporavka od otkaza glavnog servera', 2,
   'Simulirati otkaz i izmeriti vreme potrebno za potpuni oporavak podataka.',
   'Jedan radni dan tima za IT i knjigovodstvo.', 8, '2026-10-01',
   'Uspešno obnavljanje podataka u planiranom roku.', 'planiran');

-- =====================================================================
-- KORAK 14: Planiranje promena (6.3) - videti promene.php
-- =====================================================================

INSERT INTO planned_changes (id, organization_id, title, description, impact_assessment, test_plan, rollback_plan, approved_by, planned_date, status, is_unintended) VALUES
(1, 1, 'Migracija na novu verziju cloud računovodstvenog softvera',
   'Dobavljač najavio prelazak svih korisnika na novu verziju platforme.',
   'Utiče na sve knjigovođe, potreban kratak period prilagođavanja.',
   'Testirati na probnom nalogu jednog manjeg klijenta pre punog prelaska.',
   'Zadržati pristup staroj verziji aktivan 14 dana kao rezervu.',
   1, '2026-09-15', 'odobreno', FALSE),
(2, 1, 'Uvođenje VPN-a za rad od kuće',
   'Uspostavljanje VPN pristupa za sve zaposlene koji rade hibridno.',
   'Smanjuje rizik od nezaštićenog pristupa preko javnih mreža.',
   'Testirano sa tri zaposlena tokom dve nedelje pre punog uvođenja.',
   'Povratak na prethodni način pristupa ako VPN pravi probleme sa performansama.',
   2, '2026-04-01', 'sprovedeno', FALSE),
(3, 1, 'Promena dobavljača internet konekcije u kancelariji',
   'Trenutni dobavljač najavio poskupljenje, razmatra se alternativa.',
   'Kratak prekid konekcije očekivan tokom prelaska.',
   NULL, NULL,
   NULL, '2026-10-01', 'predlozeno', FALSE),
(4, 1, 'Neplanirana promena uslova korišćenja kod dobavljača cloud softvera',
   'Dobavljač je izmenio uslove korišćenja i politiku privatnosti bez prethodne najave.',
   'Potrebna provera da li nove odredbe utiču na postojeći DPA.',
   NULL, NULL,
   1, '2026-06-20', 'odobreno', TRUE);

-- =====================================================================
-- KORAK 15: Resursi (7.1) - videti resursi.php
-- =====================================================================

INSERT INTO isms_resources (id, organization_id, resource_type, description, amount_or_quantity, provided_by, status, review_date) VALUES
(1, 1, 'budzet',            'Godišnji budžet za bezbednosne alate i pripremu za sertifikaciju.', '150.000 RSD godišnje', 1, 'obezbedjeno', '2027-01-15'),
(2, 1, 'osoblje',           'Angažovanje spoljnog IT saradnika za administraciju sistema.', '1 dan nedeljno', 1, 'u_koriscenju', NULL),
(3, 1, 'alat_ili_licenca',  'Licenca za alat za upravljanje lozinkama za sve zaposlene.', '11 licenci', 8, 'obezbedjeno', NULL),
(4, 1, 'obuka',             'Godišnja obuka o bezbednosti informacija za sve zaposlene.', NULL, 2, 'u_koriscenju', '2027-02-01'),
(5, 1, 'infrastruktura',    'Nabavka rezervnog servera za offsite backup.', '1 server', 8, 'planirano', '2026-11-01');

-- =====================================================================
-- KORAK 16: Kompetentnost i obuka (7.2-7.3) - videti kompetentnost.php
-- =====================================================================

INSERT INTO competence_records (id, organization_id, personnel_id, role_id, required_competence, gap_identified, action_taken, evaluated_effective, evaluated_at) VALUES
(1, 1, 4, NULL, 'Poznavanje bezbednog rukovanja finansijskim podacima klijenata.', 'Nema formalnu obuku iz zaštite podataka.', 'Prisustvovao internoj obuci o zaštiti podataka.', TRUE, '2026-03-01'),
(2, 1, 11, NULL, 'Osnovna IT bezbednosna pismenost.', 'Nova osoba u timu, tek počinje sa radom.', 'U toku - mentorstvo od strane Marka Petrovića.', NULL, NULL),
(3, 1, 7, 2, 'Bezbedno rukovanje podacima o zaradama zaposlenih klijenata.', NULL, 'Već poseduje relevantno iskustvo iz prethodnog posla.', TRUE, '2026-01-15');

INSERT INTO training_sessions (id, organization_id, title, description, held_at, is_mandatory) VALUES
(1, 1, 'Prepoznavanje phishing napada', 'Interna radionica sa primerima stvarnih pokušaja prevare prijavljenih u firmi.', '2026-02-20', TRUE),
(2, 1, 'Uvod u politiku bezbednosti informacija', 'Predstavljanje opšte politike i osnovnih pravila ponašanja.', '2026-02-12', TRUE),
(3, 1, 'Bezbedno rukovanje podacima klijenata pri radu od kuće', 'Praktični saveti za rad van kancelarije preko VPN-a.', '2026-05-15', TRUE);

INSERT INTO training_attendance (training_session_id, personnel_id, completed_at) VALUES
(1, 1, '2026-02-20 10:00:00'), (1, 2, '2026-02-20 10:00:00'), (1, 3, '2026-02-20 10:00:00'),
(1, 4, '2026-02-20 10:00:00'), (1, 5, '2026-02-20 10:00:00'), (1, 6, '2026-02-20 10:00:00'),
(1, 7, '2026-02-20 10:00:00'), (1, 8, '2026-02-20 10:00:00'), (1, 9, '2026-02-20 10:00:00'),
(2, 1, '2026-02-12 09:00:00'), (2, 2, '2026-02-12 09:00:00'), (2, 3, '2026-02-12 09:00:00'),
(2, 4, '2026-02-12 09:00:00'), (2, 5, '2026-02-12 09:00:00'), (2, 6, '2026-02-12 09:00:00'),
(2, 7, '2026-02-12 09:00:00'), (2, 8, '2026-02-12 09:00:00'), (2, 9, '2026-02-12 09:00:00'),
(3, 1, '2026-05-15 14:00:00'), (3, 2, '2026-05-15 14:00:00'), (3, 4, '2026-05-15 14:00:00'),
(3, 5, '2026-05-15 14:00:00');

-- =====================================================================
-- KORAK 17: Komunikacija (7.4) - videti komunikacija.php
-- =====================================================================

INSERT INTO communications_plan (organization_id, what_is_communicated, audience, trigger_condition, channel) VALUES
(1, 'Obaveštenje o planiranom održavanju cloud sistema', 'Svi zaposleni', 'Najmanje 3 dana pre planiranog održavanja', 'E-mail celoj firmi'),
(1, 'Prijava bezbednosnog incidenta', 'Koordinator ISMS-a', 'Odmah po uočavanju sumnjivog događaja', 'Direktan razgovor ili interni chat'),
(1, 'Obaveštenje klijentima o promenama u rukovanju njihovim podacima', 'Klijenti agencije', 'Pre stupanja izmene na snagu', 'E-mail i objava na sajtu'),
(1, 'Rezultati internog audita', 'Rukovodstvo i vlasnici procesa', 'Nakon svakog internog audita', 'Sastanak i pisani izveštaj');

-- =====================================================================
-- KORAK 18: Dobavljači (A.5.19-5.23) - videti dobavljaci.php
-- =====================================================================

INSERT INTO suppliers (id, organization_id, name, has_data_access, risk_level, is_cloud_service, contract_start, contract_end, dpa_signed, sla_requirements, exit_strategy_confirmed, subprocessors_reviewed, last_reviewed_at) VALUES
(1, 1, 'Dobavljač cloud računovodstvenog softvera', TRUE, 'visok', TRUE, '2020-01-01', '2027-01-01', TRUE, 'Obavezna dvofaktorska autentifikacija za administratorski pristup, 99.9% dostupnost.', TRUE, TRUE, '2026-05-01'),
(2, 1, 'Banka - servis za e-bankarstvo',              TRUE, 'visok', TRUE, '2018-01-01', NULL,         TRUE, 'Dvofaktorska autentifikacija obavezna, dnevni limiti transakcija.', FALSE, FALSE, '2026-04-10'),
(3, 1, 'Dobavljač hostinga e-pošte',                    TRUE, 'srednji', TRUE, '2021-06-01', NULL,       FALSE, NULL, FALSE, FALSE, NULL),
(4, 1, 'Firma za održavanje klima uređaja u kancelariji', FALSE, 'nizak', FALSE, '2019-01-01', NULL,      FALSE, NULL, FALSE, FALSE, '2025-09-01');

INSERT INTO supplier_reviews (supplier_id, review_date, findings, reviewed_by) VALUES
(1, '2026-05-01', 'Dobavljač je proširio spisak podizvođača za skladištenje podataka, novi podizvođač proveren i odobren.', 2),
(2, '2026-04-10', 'Nema promena u odnosu na prošlogodišnji pregled.', 1),
(4, '2025-09-01', 'Nema pristup podacima klijenata, nizak rizik potvrđen.', 2);

-- =====================================================================
-- KORAK 19: Sistemi i pristup (8.1 / A.8.1-8.5) - videti sistemi-pristup.php
-- =====================================================================

INSERT INTO systems (id, organization_id, name, description, owner_id, hosting_type, supplier_id, criticality) VALUES
(1, 1, 'Cloud računovodstveni softver',      'Glavni sistem za vođenje poslovnih knjiga klijenata.', 3, 'cloud',    1, 'visok'),
(2, 1, 'Sistem za e-bankarstvo klijenata',   'Pristup elektronskom bankarstvu radi izvršavanja plaćanja u ime klijenata.', 1, 'cloud', 2, 'visok'),
(3, 1, 'Interni fajl server / NAS',           'Lokalni server za deljene fajlove i rezervne kopije.', 8, 'lokalno', NULL, 'srednji'),
(4, 1, 'Softver za obračun zarada',           'Aplikacija za obračun zarada zaposlenih kod klijenata.', 7, 'cloud',  NULL, 'visok'),
(5, 1, 'E-mail sistem',                        'Poslovna e-pošta cele firme.', 2, 'cloud', 3, 'srednji');

INSERT INTO access_grants (organization_id, system_id, personnel_id, access_level, scope_note, granted_by, revoked_at, revoked_by, status) VALUES
(1, 1, 3,  'privilegovan', NULL, 2, NULL, NULL, 'aktivan'),
(1, 1, 4,  'standardni',   'Samo dodeljeni portfolio klijenata', 3, NULL, NULL, 'aktivan'),
(1, 1, 5,  'standardni',   'Samo dodeljeni portfolio klijenata', 3, NULL, NULL, 'aktivan'),
(1, 1, 6,  'standardni',   'Samo dodeljeni portfolio klijenata', 3, NULL, NULL, 'aktivan'),
(1, 1, 10, 'standardni',   'Samo dodeljeni portfolio klijenata', 3, '2025-11-30 17:00:00', 8, 'ukinut'),
(1, 2, 1,  'privilegovan', NULL, 1, NULL, NULL, 'aktivan'),
(1, 2, 2,  'privilegovan', NULL, 1, NULL, NULL, 'aktivan'),
(1, 3, 8,  'privilegovan', NULL, 2, NULL, NULL, 'aktivan'),
(1, 3, 2,  'standardni',   NULL, 8, NULL, NULL, 'aktivan'),
(1, 3, 1,  'standardni',   NULL, 8, NULL, NULL, 'aktivan'),
(1, 4, 7,  'privilegovan', NULL, 3, NULL, NULL, 'aktivan'),
(1, 4, 3,  'standardni',   NULL, 7, NULL, NULL, 'aktivan'),
(1, 5, 1,  'standardni',   NULL, 2, NULL, NULL, 'aktivan'),
(1, 5, 2,  'privilegovan', NULL, 2, NULL, NULL, 'aktivan'),
(1, 5, 3,  'standardni',   NULL, 2, NULL, NULL, 'aktivan'),
(1, 5, 4,  'standardni',   NULL, 2, NULL, NULL, 'aktivan'),
(1, 5, 5,  'standardni',   NULL, 2, NULL, NULL, 'aktivan'),
(1, 5, 6,  'standardni',   NULL, 2, NULL, NULL, 'aktivan'),
(1, 5, 7,  'standardni',   NULL, 2, NULL, NULL, 'aktivan'),
(1, 5, 8,  'standardni',   NULL, 2, NULL, NULL, 'aktivan'),
(1, 5, 9,  'standardni',   NULL, 2, NULL, NULL, 'aktivan'),
(1, 5, 11, 'standardni',   NULL, 2, NULL, NULL, 'aktivan');

-- =====================================================================
-- KORAK 20: Fizička bezbednost (A.7) - videti fizicka-bezbednost.php
-- =====================================================================

INSERT INTO physical_locations (organization_id, name, address, perimeter_description, entry_control_method, has_monitoring) VALUES
(1, 'Kancelarija Bilans Plus - Beograd', 'Bulevar Kralja Aleksandra 73, Beograd', 'Kancelarija na trećem spratu poslovne zgrade sa recepcijom u prizemlju.', 'Elektronska brava sa karticama zaposlenih', TRUE);

-- =====================================================================
-- KORAK 21: Upravljanje incidentima (A.5.24-5.28) - videti incidenti.php
-- =====================================================================

INSERT INTO security_events (id, organization_id, reported_by, reported_at, description, assessment_outcome, severity, root_cause, evidence_reference, closed_at) VALUES
(1, 1, 4, '2026-01-14 11:20:00',
   'Zaposleni je prijavio sumnjiv e-mail koji traži podatke za prijavu na nalog za e-bankarstvo klijenta.',
   'potvrdjen_incident', 'visok',
   'Nalog nije imao uključenu dvofaktorsku autentifikaciju, pa je lozinka sama po sebi bila dovoljna za pristup.',
   'Snimak ekrana sumnjivog mejla sačuvan u internoj arhivi incidenata.', '2026-03-05 12:00:00'),
(2, 1, 8, '2026-02-02 22:40:00',
   'Uočen pokušaj prijave na cloud računovodstveni softver van uobičajenog radnog vremena.',
   'lazna_uzbuna', NULL,
   'Provereno - zaposleni je radio kasno na hitnom izveštaju za klijenta uz prethodnu najavu.',
   NULL, '2026-02-03 09:00:00'),
(3, 1, NULL, '2026-07-10 15:05:00',
   'Klijent se žalio da je dobio fakturu sa netačnim iznosom - proverava se da li je reč o ljudskoj grešci ili nečem ozbiljnijem.',
   'na_cekanju', NULL, NULL, NULL, NULL),
(4, 1, 8, '2026-04-18 08:15:00',
   'Privremeni gubitak interne mrežne konekcije usled kvara rutera u kancelariji.',
   'potvrdjen_incident', 'nizak',
   'Zastareo mrežni uređaj, dostigao kraj radnog veka.',
   NULL, '2026-04-18 11:00:00');

-- =====================================================================
-- KORAK 22: Kontinuitet poslovanja (A.5.29-5.30) - videti kontinuitet-poslovanja.php
-- =====================================================================

INSERT INTO continuity_plans (id, organization_id, scenario, plan_description, owner_id, last_tested_at, test_result, next_test_due) VALUES
(1, 1, 'Nestanak struje u kancelariji',
   'Prelazak na rad od kuće preko VPN-a; kritični sistemi hostovani su u cloud-u sa nezavisnim napajanjem.',
   2, '2026-03-10', 'uspesno', '2027-03-10'),
(2, 1, 'Pad cloud računovodstvenog softvera',
   'Privremeni ručni rad uz papirnu evidenciju do ponovne dostupnosti sistema, uz kasniji unos u softver.',
   3, '2026-06-01', 'delimicno_uspesno', '2026-12-01'),
(3, 1, 'Nedostupnost ključnog dobavljača (banke)',
   'Kontakt sa alternativnim kanalom banke i odlaganje neurgentnih plaćanja do uspostavljanja veze.',
   1, NULL, 'nije_testirano', '2026-10-01');

-- =====================================================================
-- KORAK 23: Usklađenost (A.5.31-5.36) - videti uskladjenost.php
-- =====================================================================

INSERT INTO compliance_items (organization_id, control_ref, title, description, status, owner_id, last_reviewed_at, next_review_due) VALUES
(1, '5.31', 'Zakon o zaštiti podataka o ličnosti', 'Zahteva pravni osnov za obradu i evidenciju aktivnosti obrade ličnih podataka klijenata i zaposlenih.', 'usaglaseno', 9, '2026-05-01', '2027-05-01'),
(1, '5.31', 'Zakon o računovodstvu i propisi Poreske uprave', 'Uređuje način vođenja poslovnih knjiga i elektronsko dostavljanje poreskih prijava.', 'usaglaseno', 3, '2026-05-01', '2027-05-01'),
(1, '5.32', 'Licence za korišćeni softver', 'Provera da li su sve licence za Office paket i računovodstveni softver validne i ažurne.', 'usaglaseno', 8, '2026-04-01', '2027-04-01'),
(1, '5.33', 'Čuvanje računovodstvene dokumentacije u zakonski propisanom roku', 'Zakonski rok čuvanja finansijske dokumentacije mora biti ispoštovan i za elektronsku i za papirnu arhivu.', 'delimicno', 3, '2026-05-15', '2026-11-15'),
(1, '5.34', 'Usklađenost sa opštim aktima o zaštiti ličnih podataka klijenata i zaposlenih', 'Interni akti treba da budu usklađeni sa zakonom o zaštiti podataka o ličnosti.', 'delimicno', 9, '2026-05-01', '2026-11-01'),
(1, '5.35', 'Nezavisna provera bezbednosti informacija pre sertifikacije', 'Planirana nezavisna provera pre zakazanog sertifikacionog audita.', 'neusaglaseno', 2, NULL, '2026-12-01'),
(1, '5.36', 'Interna provera usklađenosti sa politikom kontrole pristupa', 'Redovna provera da li se stvarna dodela pristupa poklapa sa politikom.', 'usaglaseno', 2, '2026-06-01', '2026-12-01');

-- =====================================================================
-- KORAK 24: Pokazatelji i merenje (9.1) - videti pokazatelji.php
-- =====================================================================

INSERT INTO metrics (id, organization_id, name, description, unit, target_value, measurement_frequency) VALUES
(1, 1, 'Vreme ukidanja pristupa nakon odlaska zaposlenog', 'Broj sati od datuma prestanka rada do ukidanja svih pristupa.', 'sati', 24.00, 'mesečno'),
(2, 1, 'Procenat zaposlenih koji su prošli obaveznu obuku o bezbednosti', NULL, 'procenat', 100.00, 'kvartalno'),
(3, 1, 'Broj bezbednosnih incidenata mesečno', NULL, 'broj', 2.00, 'mesečno');

INSERT INTO metric_measurements (metric_id, measured_at, value, measured_by, notes) VALUES
(1, '2025-12-15', 72.00, 8, 'Pre uvođenja checkliste za offboarding.'),
(1, '2026-03-10', 36.00, 8, 'Delimično poboljšanje, checklista još nije formalizovana.'),
(1, '2026-06-20', 18.00, 8, 'Cilj ispunjen nakon uvođenja formalne checkliste.'),
(2, '2025-11-01', 60.00, 2, NULL),
(2, '2026-02-15', 85.00, 2, NULL),
(2, '2026-06-01', 95.00, 2, 'Jedna osoba (Milan) tek započela obuke.'),
(3, '2026-05-01', 2.00, 2, NULL),
(3, '2026-06-01', 1.00, 2, NULL),
(3, '2026-07-01', 1.00, 2, NULL);

-- =====================================================================
-- KORAK 25: Interni audit (9.2) - videti interni-audit.php
-- =====================================================================

INSERT INTO internal_audits (id, organization_id, audit_date, scope, auditor_name, is_external_auditor, report_reference) VALUES
(1, 1, '2026-04-20', 'Kontrola pristupa i upravljanje pristupnim pravima za sve sisteme.', 'Ivana Marković', FALSE, '/dokumenti/interni-audit-2026-04.pdf'),
(2, 1, '2026-06-15', 'Upravljanje dokumentacijom i verzijama politika.', 'Bezbednost Consulting d.o.o.', TRUE, '/dokumenti/spoljni-audit-2026-06.pdf');

-- corrective_action_id na nalazu #2 se popunjava u koraku 27, posle
-- unosa korektivnih mera.
INSERT INTO audit_findings (id, internal_audit_id, description, severity) VALUES
(1, 1, 'Tri od jedanaest naloga nemaju uključenu dvofaktorsku autentifikaciju.', 'visok'),
(2, 1, 'Pristup bivšeg zaposlenog nije ukinut u roku od 24 časa nakon odlaska.', 'srednji'),
(3, 2, 'Politika rada na daljinu nema definisan datum sledećeg pregleda u trenutku audita.', 'nizak');

-- =====================================================================
-- KORAK 26: Pregled menadžmenta (9.3) - videti pregled-menadzmenta.php
-- =====================================================================

INSERT INTO management_reviews (id, organization_id, review_date, attendees, previous_actions_status, context_changes, interested_party_changes, performance_summary, interested_party_feedback, risk_treatment_status, improvement_opportunities) VALUES
(1, 1, '2026-03-01',
   'Ana Jovanović, Marko Petrović, Milica Stanković',
   'Prvi pregled menadžmenta otkako je ISMS uspostavljen - nema prethodnih radnji.',
   'Uočen porast phishing napada usmerenih na knjigovodstvene agencije u regionu.',
   'Nekoliko klijenata izrazilo interesovanje za ISO 27001 sertifikat kao uslov saradnje.',
   'Prvi kvartal: jedan potvrđen incident (phishing pokušaj), uspešno rešen. Dva cilja bezbednosti ostvarena.',
   'Klijenti nisu izražavali konkretne pritužbe na bezbednost tokom kvartala.',
   'Rizik od neovlašćenog pristupa nalogu za e-bankarstvo tretiran uvođenjem 2FA.',
   'Razmotriti automatizaciju testiranja rezervnih kopija.'),
(2, 1, '2026-06-20',
   'Ana Jovanović, Marko Petrović, Milica Stanković, Vladimir Kovačević',
   'Radnja o automatizaciji testiranja rezervnih kopija još uvek u toku.',
   'Nema značajnih promena konteksta od poslednjeg pregleda.',
   'Banka klijenata najavila obavezan prelazak na noviji protokol autentifikacije.',
   'Interni audit u aprilu otkrio dva nalaza, oba u fazi korektivne mere.',
   'Pozitivne povratne informacije od klijenata nakon uvođenja 2FA.',
   'Preostali otvoreni rizik: nedostupnost cloud softvera, plan tretmana u toku.',
   'Razmotriti angažovanje nezavisnog auditora pre sertifikacionog audita.');

INSERT INTO management_review_actions (management_review_id, action_description, owner_id, due_date, status) VALUES
(1, 'Uspostaviti automatsko mesečno testiranje obnavljanja rezervnih kopija.', 8, '2026-08-01', 'u_toku'),
(1, 'Sprovesti obuku o prepoznavanju phishing napada za sve zaposlene.', 2, '2026-02-28', 'zavrseno'),
(2, 'Angažovati nezavisnog spoljnog auditora pre sertifikacionog audita.', 2, '2026-09-01', 'otvoreno'),
(2, 'Definisati redovan godišnji raspored pregleda i ažuriranja politika.', 2, '2026-09-01', 'otvoreno');

-- =====================================================================
-- KORAK 27: Korektivne mere (10.2) - videti korektivne-mere.php
-- Poslednji korak koji povezuje incidenti.php i interni-audit.php preko
-- deljene corrective_actions tabele.
-- =====================================================================

INSERT INTO corrective_actions (id, organization_id, source_security_event_id, source_audit_finding_id, description, root_cause_generalized, owner_id, due_date, status, effectiveness_confirmed_at) VALUES
(1, 1, 1,    NULL, 'Uvesti obaveznu dvofaktorsku autentifikaciju za sve naloge sa pristupom bankarskim sistemima klijenata.',
   'Isti propust je moguć i na drugim sistemima koji koriste samo lozinku bez dodatnog faktora.', 2, '2026-03-01', 'provereno_efikasno', '2026-04-15'),
(2, 1, NULL, 2,    'Uvesti obaveznu checklistu za offboarding sa rokom od 24 časa za ukidanje svih pristupa.',
   'Trenutno ne postoji formalna procedura, oslanja se na sećanje administratora sistema.', 8, '2026-05-01', 'sprovedeno', NULL),
(3, 1, NULL, NULL, 'Definisati redovan godišnji raspored pregleda i ažuriranja svih politika bezbednosti.',
   NULL, 2, '2026-09-01', 'otvoreno', NULL);

-- Kružna veza iz šeme: incident i nalaz sad znaju svoju "glavnu" korektivnu meru.
UPDATE security_events SET corrective_action_id = 1 WHERE id = 1 AND organization_id = 1;
UPDATE audit_findings SET corrective_action_id = 2 WHERE id = 2;

-- =====================================================================
-- Kraj demo podataka.
-- Napomena: unapredjenje.php (10.1), liderstvo.php (5.1) i
-- pregled-sistema.php (4.4) nemaju svoje tabele - automatski će
-- prikazati sažetak izveden iz podataka unetih iznad, bez potrebe za
-- dodatnim INSERT naredbama ovde.
-- =====================================================================
