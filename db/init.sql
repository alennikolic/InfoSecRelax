-- =====================================================================
-- InfoSecRelax — MySQL šema baze podataka
-- =====================================================================
-- Namena: podrška aplikaciji za implementaciju i održavanje ISO/IEC
-- 27001 sistema upravljanja bezbednošću informacija (ISMS) za mala i
-- srednja preduzeća.
--
-- Struktura šeme namerno prati redosled uspostavljanja standarda:
-- kontekst -> liderstvo -> planiranje/rizik -> podrška -> operacija ->
-- ocenjivanje učinka -> unapređenje -> Aneks A.
-- Isti redosled treba da prati i meni same aplikacije, tako da korisnik
-- koji prvi put uvodi standard može da krene od prve tabele/stranice i
-- ide redom.
--
-- Konvencije:
--   - Sve tabele su multi-tenant (kolona organization_id), osim
--     referentne tabele annex_a_controls, koja je zajednička za sve.
--   - Svaka tabela ima created_at/updated_at radi audit traga.
--   - Gde god je moguće, FK veze su eksplicitne (ne polimorfne), radi
--     referencijalnog integriteta.
--   - Enum vrednosti su namerno kratke; aplikacija ih može mapirati na
--     prevode/labele u interfejsu.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================================
-- MODUL 0: JEZGRO I VIŠE-ZAKUPNIŠTVO (MULTI-TENANCY)
-- =====================================================================

CREATE TABLE organizations (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                VARCHAR(255) NOT NULL,
    industry            VARCHAR(255) NULL COMMENT 'npr. knjigovodstvo, IT, proizvodnja - koristi se za predloške po delatnosti',
    employee_count      INT UNSIGNED NULL,
    certification_status ENUM('priprema','sertifikovano','nadzorna_provera','resertifikacija') NOT NULL DEFAULT 'priprema',
    certification_date  DATE NULL,
    recertification_due DATE NULL COMMENT 'kraj trogodišnjeg ciklusa',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Podrazumevana organizacija za razvojno okruženje, dok ne postoji
-- prava registracija/prijava firmi. Ista logika je dupliran kao
-- bezbednosna mreža i u src/config/database.php (ensureDefaultOrganization).
INSERT INTO organizations (id, name) VALUES (1, 'Moja firma');

CREATE TABLE personnel (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    full_name           VARCHAR(255) NOT NULL,
    email               VARCHAR(255) NULL,
    employment_type     ENUM('zaposleni','honorarni_saradnik','spoljni_dobavljac','ostalo') NOT NULL DEFAULT 'zaposleni',
    job_title           VARCHAR(255) NULL,
    start_date          DATE NULL,
    end_date            DATE NULL COMMENT 'A.6.5 - koristi se i za merenje vremena ukidanja pristupa (9.1)',
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_personnel_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Svako ko radi za firmu - zaposleni, honorarci, spoljni saradnici. Ne moraju svi imati nalog u users.';

CREATE TABLE users (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    personnel_id        BIGINT UNSIGNED NULL COMMENT 'veza ka personnel, ako ovaj nalog odgovara stvarnoj osobi u firmi',
    email               VARCHAR(255) NOT NULL,
    password_hash       VARCHAR(255) NOT NULL,
    is_active           BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at       TIMESTAMP NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE SET NULL,
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Nalozi za prijavu u samu InfoSecRelax aplikaciju.';

-- =====================================================================
-- MODUL 1: KONTEKST I OBIM (Klauzula 4.1-4.3)
-- =====================================================================

CREATE TABLE context_factors (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    factor_type         ENUM('spoljni','unutrasnji') NOT NULL COMMENT 'Klauzula 4.1',
    description         TEXT NOT NULL,
    category            VARCHAR(100) NULL COMMENT 'npr. zakonski, trziste, tehnologija, organizacija',
    last_reviewed_at    DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_context_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE interested_parties (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL COMMENT 'npr. klijenti, zaposleni, regulator, dobavljac',
    party_type          ENUM('interna','eksterna') NOT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_parties_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Klauzula 4.2';

CREATE TABLE interested_party_requirements (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    interested_party_id BIGINT UNSIGNED NOT NULL,
    requirement         TEXT NOT NULL,
    addressed_by_isms   BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'treca tacka klauzule 4.2 - da li ISMS pokriva ovo',
    notes               TEXT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (interested_party_id) REFERENCES interested_parties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scope_statements (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    scope_text          TEXT NOT NULL COMMENT 'Klauzula 4.3 - tekst koji se prenosi i na sertifikat',
    version             VARCHAR(20) NOT NULL DEFAULT '1.0',
    approved_by         BIGINT UNSIGNED NULL,
    approved_at         DATE NULL,
    effective_from      DATE NULL,
    is_current          BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_scope_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE scope_exclusions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope_statement_id  BIGINT UNSIGNED NOT NULL,
    excluded_item        VARCHAR(255) NOT NULL,
    justification        TEXT NOT NULL,
    FOREIGN KEY (scope_statement_id) REFERENCES scope_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE third_party_dependencies (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    scope_statement_id  BIGINT UNSIGNED NOT NULL,
    description         TEXT NOT NULL COMMENT 'Klauzula 4.3 - interfejsi i zavisnosti od trecih strana',
    managed_via         VARCHAR(255) NULL COMMENT 'npr. ugovor o nivou usluge, DPA',
    FOREIGN KEY (scope_statement_id) REFERENCES scope_statements(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODUL 2: LIDERSTVO I DOKUMENTACIJA (Klauzula 5, 7.5, A.5.1-5.4)
-- =====================================================================

-- Opšta tabela za kontrolu dokumenata (Klauzula 7.5) - koriste je
-- policies, procedures i drugi moduli preko document_id.
CREATE TABLE documents (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    title               VARCHAR(255) NOT NULL,
    doc_type            ENUM('politika','procedura','registar','zapisnik','ostalo') NOT NULL,
    classification       ENUM('javno','interno','poverljivo','strogo_poverljivo') NOT NULL DEFAULT 'interno' COMMENT 'A.5.12',
    current_version     VARCHAR(20) NOT NULL DEFAULT '1.0',
    file_reference       VARCHAR(500) NULL COMMENT 'putanja ili URL do fajla',
    owner_id            BIGINT UNSIGNED NULL,
    approved_by         BIGINT UNSIGNED NULL,
    approved_at         DATE NULL,
    next_review_due     DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES personnel(id) ON DELETE SET NULL,
    FOREIGN KEY (approved_by) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_documents_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE document_versions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id         BIGINT UNSIGNED NOT NULL,
    version_number      VARCHAR(20) NOT NULL,
    changed_by          BIGINT UNSIGNED NULL,
    change_summary      TEXT NULL,
    file_reference       VARCHAR(500) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Klauzula 7.5.2 - istorija verzija',
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE policies (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    document_id         BIGINT UNSIGNED NOT NULL COMMENT 'veza ka documents za verzionisanje',
    policy_type         ENUM('opsta','tematska') NOT NULL COMMENT 'opsta = Klauzula 5.2, tematska = A.5.1',
    topic               VARCHAR(100) NULL COMMENT 'npr. kontrola_pristupa, prihvatljiva_upotreba, backup, dobavljaci, rad_na_daljinu, kriptografija',
    acknowledgment_required BOOLEAN NOT NULL DEFAULT TRUE,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE,
    INDEX idx_policies_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE policy_acknowledgments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    policy_id           BIGINT UNSIGNED NOT NULL,
    personnel_id        BIGINT UNSIGNED NOT NULL,
    acknowledged_at     TIMESTAMP NULL,
    FOREIGN KEY (policy_id) REFERENCES policies(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    UNIQUE KEY uq_policy_person (policy_id, personnel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE roles_responsibilities (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    role_name           VARCHAR(255) NOT NULL COMMENT 'npr. Koordinator ISMS-a, Vlasnik sredstva X',
    description         TEXT NULL,
    assigned_to         BIGINT UNSIGNED NULL,
    authority_level      VARCHAR(255) NULL COMMENT 'Klauzula 5.3 - odgovornost bez ovlascenja je nepotpuna',
    related_control_ref  VARCHAR(20) NULL COMMENT 'npr. A.5.2, ako uloga direktno odgovara na kontrolu',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_roles_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Klauzula 5.3, A.5.2';

-- =====================================================================
-- MODUL 3: UPRAVLJANJE RIZIKOM (Klauzula 6.1, 8.2, 8.3, A.5.9)
-- =====================================================================

CREATE TABLE assets (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL,
    asset_type          ENUM('informacija','hardver','softver','usluga','ljudi') NOT NULL COMMENT 'A.5.9',
    description         TEXT NULL,
    owner_id            BIGINT UNSIGNED NULL COMMENT 'vlasnik sredstva - Korak 2 iz procene rizika',
    classification       ENUM('javno','interno','poverljivo','strogo_poverljivo') NOT NULL DEFAULT 'interno',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_assets_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE risk_criteria (
    id                      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id         BIGINT UNSIGNED NOT NULL,
    likelihood_scale_max    TINYINT UNSIGNED NOT NULL DEFAULT 5,
    impact_scale_max        TINYINT UNSIGNED NOT NULL DEFAULT 5,
    low_threshold_max       SMALLINT UNSIGNED NOT NULL DEFAULT 6,
    medium_threshold_max    SMALLINT UNSIGNED NOT NULL DEFAULT 14,
    notes                   TEXT NULL COMMENT 'firma sama definise pragove - Korak 1 iz procene rizika',
    created_at              TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    UNIQUE KEY uq_risk_criteria_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Jedan red po organizaciji - metodologija iz Klauzule 6.1.2 pre pocetka procene';

CREATE TABLE risks (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    asset_id            BIGINT UNSIGNED NULL,
    title               VARCHAR(255) NOT NULL,
    threat_description   TEXT NOT NULL,
    vulnerability_description TEXT NOT NULL,
    likelihood          TINYINT UNSIGNED NOT NULL,
    impact              TINYINT UNSIGNED NOT NULL,
    risk_score          SMALLINT UNSIGNED GENERATED ALWAYS AS (likelihood * impact) STORED,
    risk_level          ENUM('nizak','srednji','visok') NULL COMMENT 'racuna aplikacija na osnovu risk_criteria',
    identified_at       DATE NOT NULL,
    review_trigger      ENUM('godisnji_ciklus','incident','promena','ostalo') NOT NULL DEFAULT 'godisnji_ciklus' COMMENT 'Klauzula 8.2',
    status               ENUM('otvoren','u_tretmanu','tretiran','prihvacen','zatvoren') NOT NULL DEFAULT 'otvoren',
    last_reviewed_at    DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
    INDEX idx_risks_org (organization_id),
    INDEX idx_risks_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registar rizika - Klauzula 6.1.2, 8.2';

CREATE TABLE risk_treatments (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    risk_id             BIGINT UNSIGNED NOT NULL,
    treatment_option     ENUM('smanjiti','izbeci','preneti','prihvatiti') NOT NULL COMMENT 'Klauzula 6.1.3',
    description         TEXT NOT NULL,
    owner_id            BIGINT UNSIGNED NULL,
    due_date            DATE NULL,
    status               ENUM('planirano','u_toku','sprovedeno','ponovo_otvoreno') NOT NULL DEFAULT 'planirano',
    residual_risk_score  SMALLINT UNSIGNED NULL COMMENT 'nivo rizika posle sprovedene mere - Klauzula 8.3',
    completed_at         DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (risk_id) REFERENCES risks(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE objectives (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    title               VARCHAR(255) NOT NULL,
    linked_risk_id       BIGINT UNSIGNED NULL,
    what_will_be_done   TEXT NOT NULL COMMENT 'Klauzula 6.2 - pet pitanja plana ostvarenja',
    resources_required   TEXT NULL,
    owner_id            BIGINT UNSIGNED NULL,
    due_date            DATE NULL,
    evaluation_method    TEXT NULL COMMENT 'kako se meri uspeh',
    status               ENUM('planiran','u_toku','ostvaren','neostvaren') NOT NULL DEFAULT 'planiran',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (linked_risk_id) REFERENCES risks(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_objectives_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE planned_changes (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    title               VARCHAR(255) NOT NULL,
    description         TEXT NOT NULL,
    impact_assessment    TEXT NULL COMMENT 'Klauzula 6.3, 8.32 - uticaj na obim/rizike/dokumentaciju',
    test_plan            TEXT NULL,
    rollback_plan        TEXT NULL COMMENT 'A.8.32',
    approved_by         BIGINT UNSIGNED NULL,
    planned_date         DATE NULL,
    status               ENUM('predlozeno','odobreno','sprovedeno','odbaceno') NOT NULL DEFAULT 'predlozeno',
    is_unintended         BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Klauzula 8.1 - promena koju je uocila, ne pokrenula, firma',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_changes_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODUL 4: PODRŠKA - KOMPETENTNOST I SVEST (Klauzula 7.2-7.4, A.6.1-6.3)
-- =====================================================================

CREATE TABLE competence_records (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    personnel_id        BIGINT UNSIGNED NOT NULL,
    role_id              BIGINT UNSIGNED NULL,
    required_competence  TEXT NOT NULL COMMENT 'Klauzula 7.2',
    gap_identified        TEXT NULL,
    action_taken          TEXT NULL COMMENT 'obrazovanje / obuka / iskustvo',
    evaluated_effective    BOOLEAN NULL,
    evaluated_at           DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles_responsibilities(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE personnel_screening (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    personnel_id        BIGINT UNSIGNED NOT NULL,
    screening_type       VARCHAR(255) NOT NULL COMMENT 'A.6.1 - proporcionalno riziku pozicije',
    completed_at         DATE NULL,
    notes                TEXT NULL,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE training_sessions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    title               VARCHAR(255) NOT NULL,
    description         TEXT NULL COMMENT 'Klauzula 7.3, A.6.3 - najbolje kad koristi stvarne primere',
    held_at              DATE NOT NULL,
    is_mandatory          BOOLEAN NOT NULL DEFAULT TRUE,
    related_incident_id   BIGINT UNSIGNED NULL COMMENT 'ako je obuka nastala kao pouka iz konkretnog incidenta',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_training_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE training_attendance (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    training_session_id  BIGINT UNSIGNED NOT NULL,
    personnel_id         BIGINT UNSIGNED NOT NULL,
    completed_at          TIMESTAMP NULL,
    FOREIGN KEY (training_session_id) REFERENCES training_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    UNIQUE KEY uq_training_person (training_session_id, personnel_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE communications_plan (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    what_is_communicated TEXT NOT NULL COMMENT 'Klauzula 7.4',
    audience             VARCHAR(255) NOT NULL COMMENT 'kome',
    trigger_condition     VARCHAR(255) NOT NULL COMMENT 'kada',
    channel               VARCHAR(255) NOT NULL COMMENT 'kako',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODUL 5: SISTEMI I KONTROLA PRISTUPA (A.5.15-5.18, A.8.1-8.5)
-- =====================================================================

CREATE TABLE systems (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL,
    description         TEXT NULL,
    owner_id            BIGINT UNSIGNED NULL,
    hosting_type         ENUM('cloud','lokalno','hibridno') NOT NULL DEFAULT 'cloud',
    supplier_id          BIGINT UNSIGNED NULL COMMENT 'FK ka suppliers, definisano nize',
    criticality          ENUM('nizak','srednji','visok') NOT NULL DEFAULT 'srednji',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_systems_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE access_grants (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    system_id           BIGINT UNSIGNED NOT NULL,
    personnel_id        BIGINT UNSIGNED NOT NULL,
    access_level         ENUM('standardni','privilegovan') NOT NULL DEFAULT 'standardni' COMMENT 'A.8.2',
    scope_note            VARCHAR(255) NULL COMMENT 'npr. samo dodeljeni portfolio klijenata - A.8.3',
    granted_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    granted_by           BIGINT UNSIGNED NULL,
    revoked_at           TIMESTAMP NULL COMMENT 'koristi se za pokazatelj iz Klauzule 9.1',
    revoked_by           BIGINT UNSIGNED NULL,
    status               ENUM('aktivan','ukinut') NOT NULL DEFAULT 'aktivan',
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE CASCADE,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES personnel(id) ON DELETE SET NULL,
    FOREIGN KEY (revoked_by) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_access_org (organization_id),
    INDEX idx_access_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sredisnja tabela za A.5.15-5.18 i A.8.2. vreme_ukidanja = revoked_at - personnel.end_date';

CREATE TABLE equipment (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    asset_id             BIGINT UNSIGNED NULL COMMENT 'veza ka opstem popisu sredstava',
    equipment_type        VARCHAR(100) NOT NULL COMMENT 'laptop, telefon, stampac...',
    serial_number         VARCHAR(255) NULL,
    assigned_to           BIGINT UNSIGNED NULL,
    location_id            BIGINT UNSIGNED NULL COMMENT 'FK ka physical_locations, definisano nize',
    encrypted             BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'A.8.1, A.8.24',
    disposal_date          DATE NULL COMMENT 'A.7.14',
    disposal_method        VARCHAR(255) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (asset_id) REFERENCES assets(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_equipment_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE storage_media (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    media_type            VARCHAR(100) NOT NULL COMMENT 'USB, spoljni disk...',
    is_encrypted           BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'A.7.10',
    assigned_to             BIGINT UNSIGNED NULL,
    last_used_at            TIMESTAMP NULL,
    disposed_at              DATE NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE backup_records (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    system_id            BIGINT UNSIGNED NULL,
    backup_date           DATE NOT NULL,
    backup_type            VARCHAR(100) NULL,
    test_restore_performed BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'A.5.30, A.8.13 - test obnavljanja, ne samo pravljenje kopije',
    test_restore_date       DATE NULL,
    test_result             ENUM('uspesno','neuspesno','nije_testirano') NOT NULL DEFAULT 'nije_testirano',
    notes                    TEXT NULL,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (system_id) REFERENCES systems(id) ON DELETE SET NULL,
    INDEX idx_backup_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODUL 6: DOBAVLJAČI (A.5.19-5.23)
-- =====================================================================

CREATE TABLE suppliers (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL,
    has_data_access       BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'A.5.19 - odredjuje nivo paznje',
    risk_level             ENUM('nizak','srednji','visok') NOT NULL DEFAULT 'srednji',
    is_cloud_service        BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'A.5.23',
    contract_start          DATE NULL,
    contract_end             DATE NULL,
    dpa_signed               BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'ugovor o obradi podataka - A.5.20',
    sla_requirements          TEXT NULL COMMENT 'npr. zahtev za MFA - A.5.20',
    exit_strategy_confirmed   BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'A.5.23 - izvoz i brisanje podataka pri raskidu',
    subprocessors_reviewed    BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'A.5.21 - IKT lanac snabdevanja',
    last_reviewed_at          DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_suppliers_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE supplier_reviews (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    supplier_id          BIGINT UNSIGNED NOT NULL,
    review_date           DATE NOT NULL,
    findings              TEXT NULL COMMENT 'A.5.22 - npr. otkrivena promena prakse dobavljaca',
    reviewed_by            BIGINT UNSIGNED NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE systems ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

-- =====================================================================
-- MODUL 7: FIZIČKA BEZBEDNOST (A.7.1-7.7)
-- =====================================================================

CREATE TABLE physical_locations (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL,
    address              VARCHAR(500) NULL,
    perimeter_description TEXT NULL COMMENT 'A.7.1',
    entry_control_method    VARCHAR(255) NULL COMMENT 'A.7.2 - npr. elektronska brava sa kodovima',
    has_monitoring            BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'A.7.4',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_locations_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE equipment ADD FOREIGN KEY (location_id) REFERENCES physical_locations(id) ON DELETE SET NULL;

-- =====================================================================
-- MODUL 8: UPRAVLJANJE INCIDENTIMA (A.5.24-5.28, Klauzula 10.2)
-- =====================================================================

CREATE TABLE security_events (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    reported_by           BIGINT UNSIGNED NULL COMMENT 'A.5.24 - kanal za prijavu; NULL ako anonimno',
    reported_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    description             TEXT NOT NULL,
    assessment_outcome       ENUM('na_cekanju','lazna_uzbuna','potvrdjen_incident') NOT NULL DEFAULT 'na_cekanju' COMMENT 'A.5.25',
    severity                 ENUM('nizak','srednji','visok') NULL,
    root_cause                TEXT NULL COMMENT 'A.5.27 - ne samo sta, nego zasto',
    evidence_reference          VARCHAR(500) NULL COMMENT 'A.5.28',
    closed_at                  TIMESTAMP NULL,
    created_at             TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_events_org (organization_id),
    INDEX idx_events_outcome (assessment_outcome)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Registar incidenata iz vodica za Klauzulu 4.4. Jedna tabela pokriva ceo A.5.24-5.28 ciklus.';

CREATE TABLE corrective_actions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    source_security_event_id BIGINT UNSIGNED NULL,
    source_audit_finding_id   BIGINT UNSIGNED NULL COMMENT 'FK definisan nize, posle audit_findings',
    description               TEXT NOT NULL COMMENT 'Klauzula 10.2',
    root_cause_generalized     TEXT NULL COMMENT 'da li se slicno moze desiti i na drugom mestu',
    owner_id                    BIGINT UNSIGNED NULL,
    due_date                     DATE NULL,
    status                       ENUM('otvoreno','sprovedeno','provereno_efikasno','ponovo_otvoreno') NOT NULL DEFAULT 'otvoreno',
    effectiveness_confirmed_at    DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (source_security_event_id) REFERENCES security_events(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_corrective_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE security_events ADD COLUMN corrective_action_id BIGINT UNSIGNED NULL AFTER root_cause,
    ADD FOREIGN KEY (corrective_action_id) REFERENCES corrective_actions(id) ON DELETE SET NULL;

-- =====================================================================
-- MODUL 9: OCENJIVANJE UČINKA (Klauzula 9.1-9.3)
-- =====================================================================

CREATE TABLE metrics (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    name                VARCHAR(255) NOT NULL COMMENT 'npr. vreme ukidanja pristupa nakon odlaska',
    description         TEXT NULL,
    unit                 VARCHAR(50) NULL COMMENT 'npr. sati, procenat, broj',
    target_value          DECIMAL(10,2) NULL,
    measurement_frequency  VARCHAR(100) NULL COMMENT 'Klauzula 9.1 - kada se meri',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_metrics_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE metric_measurements (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    metric_id            BIGINT UNSIGNED NOT NULL,
    measured_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    value                  DECIMAL(10,2) NOT NULL,
    measured_by             BIGINT UNSIGNED NULL,
    notes                    TEXT NULL,
    FOREIGN KEY (metric_id) REFERENCES metrics(id) ON DELETE CASCADE,
    FOREIGN KEY (measured_by) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_measurements_metric (metric_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE internal_audits (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    audit_date            DATE NOT NULL,
    scope                  TEXT NULL COMMENT 'Klauzula 9.2 - obim ovog konkretnog audita',
    auditor_name            VARCHAR(255) NOT NULL COMMENT 'mora biti nezavisan od procesa koji se proverava',
    is_external_auditor       BOOLEAN NOT NULL DEFAULT FALSE,
    report_reference          VARCHAR(500) NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_audits_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_findings (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    internal_audit_id     BIGINT UNSIGNED NOT NULL,
    description             TEXT NOT NULL,
    severity                 ENUM('nizak','srednji','visok') NOT NULL DEFAULT 'srednji',
    corrective_action_id      BIGINT UNSIGNED NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (internal_audit_id) REFERENCES internal_audits(id) ON DELETE CASCADE,
    FOREIGN KEY (corrective_action_id) REFERENCES corrective_actions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE corrective_actions ADD FOREIGN KEY (source_audit_finding_id) REFERENCES audit_findings(id) ON DELETE SET NULL;

CREATE TABLE management_reviews (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    review_date            DATE NOT NULL,
    attendees               TEXT NULL,
    previous_actions_status   TEXT NULL COMMENT 'Klauzula 9.3(a)',
    context_changes            TEXT NULL COMMENT '9.3(b)',
    interested_party_changes    TEXT NULL COMMENT '9.3(c)',
    performance_summary          TEXT NULL COMMENT '9.3(d) - neusaglasenosti, pokazatelji, audit, ciljevi',
    interested_party_feedback     TEXT NULL COMMENT '9.3(e)',
    risk_treatment_status          TEXT NULL COMMENT '9.3(f)',
    improvement_opportunities       TEXT NULL COMMENT '9.3(g)',
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_reviews_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE management_review_actions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    management_review_id   BIGINT UNSIGNED NOT NULL,
    action_description        TEXT NOT NULL,
    owner_id                    BIGINT UNSIGNED NULL,
    due_date                     DATE NULL,
    status                       ENUM('otvoreno','u_toku','zavrseno') NOT NULL DEFAULT 'otvoreno',
    FOREIGN KEY (management_review_id) REFERENCES management_reviews(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES personnel(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
-- MODUL 10: PRAVNA USKLAĐENOST (A.5.31-5.34, A.6.4-6.6)
-- =====================================================================

CREATE TABLE legal_requirements (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    requirement_name       VARCHAR(255) NOT NULL COMMENT 'npr. Zakon o zastiti podataka o licnosti',
    source                   ENUM('zakon','propis','ugovor','ostalo') NOT NULL DEFAULT 'zakon' COMMENT 'A.5.31',
    description               TEXT NULL,
    last_reviewed_at            DATE NULL,
    next_review_due              DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    INDEX idx_legal_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE confidentiality_agreements (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    personnel_id           BIGINT UNSIGNED NOT NULL,
    signed_at                DATE NULL COMMENT 'A.6.6 - vazi i za honorarne saradnike, ne samo zaposlene',
    covers_post_termination   BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'A.6.5',
    document_reference          VARCHAR(500) NULL,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE disciplinary_actions (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    personnel_id           BIGINT UNSIGNED NOT NULL,
    related_event_id         BIGINT UNSIGNED NULL COMMENT 'A.6.4 - iskljucivo za namerno/ponovljeno krsenje, ne za postene greske',
    description               TEXT NOT NULL,
    action_taken               VARCHAR(255) NOT NULL,
    action_date                DATE NOT NULL,
    FOREIGN KEY (personnel_id) REFERENCES personnel(id) ON DELETE CASCADE,
    FOREIGN KEY (related_event_id) REFERENCES security_events(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Osetljiva tabela - pristup treba dodatno ograniciti na nivou aplikacije.';

-- =====================================================================
-- MODUL 11: ANEKS A - MASTER KATALOG I IZJAVA O PRIMENLJIVOSTI
-- =====================================================================

-- Zajednicka, ne-tenant tabela - ista za sve organizacije koje koriste
-- aplikaciju. Sluzi kao referenca za statement_of_applicability.
CREATE TABLE annex_a_controls (
    id                  SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    control_ref          VARCHAR(10) NOT NULL COMMENT 'npr. 5.1, 8.24',
    theme                 ENUM('organizacione','ljudske','fizicke','tehnoloske') NOT NULL,
    title                 VARCHAR(255) NOT NULL,
    UNIQUE KEY uq_control_ref (control_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Staticki katalog svih 93 kontrole - videti seed podatke na kraju fajla.';

CREATE TABLE statement_of_applicability (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    control_id            SMALLINT UNSIGNED NOT NULL,
    is_applicable          BOOLEAN NOT NULL DEFAULT TRUE,
    justification            TEXT NOT NULL COMMENT 'obavezno i za ukljucene i za iskljucene kontrole',
    linked_risk_id            BIGINT UNSIGNED NULL,
    implementation_status      ENUM('nije_zapoceto','u_toku','implementirano') NOT NULL DEFAULT 'nije_zapoceto',
    owner_id                    BIGINT UNSIGNED NULL,
    last_reviewed_at             DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (control_id) REFERENCES annex_a_controls(id) ON DELETE RESTRICT,
    FOREIGN KEY (linked_risk_id) REFERENCES risks(id) ON DELETE SET NULL,
    FOREIGN KEY (owner_id) REFERENCES personnel(id) ON DELETE SET NULL,
    UNIQUE KEY uq_soa_org_control (organization_id, control_id),
    INDEX idx_soa_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Izjava o primenljivosti - Klauzula 6.1.3. Po jedan red po kontroli po organizaciji.';

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED PODACI: SVIH 93 KONTROLE ANEKSA A
-- =====================================================================

INSERT INTO annex_a_controls (control_ref, theme, title) VALUES
-- A.5 Organizacione kontrole (37)
('5.1','organizacione','Politike za bezbednost informacija'),
('5.2','organizacione','Uloge i odgovornosti za bezbednost informacija'),
('5.3','organizacione','Razdvajanje dužnosti'),
('5.4','organizacione','Odgovornosti rukovodstva'),
('5.5','organizacione','Odnos sa vlastima'),
('5.6','organizacione','Odnos sa interesnim grupama'),
('5.7','organizacione','Obaveštajni podaci o pretnjama'),
('5.8','organizacione','Bezbednost informacija u upravljanju projektima'),
('5.9','organizacione','Popis informacija i pridruženih sredstava'),
('5.10','organizacione','Prihvatljiva upotreba informacija i sredstava'),
('5.11','organizacione','Povraćaj sredstava'),
('5.12','organizacione','Klasifikacija informacija'),
('5.13','organizacione','Označavanje informacija'),
('5.14','organizacione','Prenos informacija'),
('5.15','organizacione','Kontrola pristupa'),
('5.16','organizacione','Upravljanje identitetom'),
('5.17','organizacione','Autentifikacione informacije'),
('5.18','organizacione','Prava pristupa'),
('5.19','organizacione','Bezbednost informacija u odnosima sa dobavljačima'),
('5.20','organizacione','Bezbednost informacija u ugovorima sa dobavljačima'),
('5.21','organizacione','Upravljanje bezbednošću u IKT lancu snabdevanja'),
('5.22','organizacione','Praćenje, pregled i upravljanje promenama kod usluga dobavljača'),
('5.23','organizacione','Bezbednost informacija za korišćenje cloud usluga'),
('5.24','organizacione','Planiranje i priprema upravljanja incidentima'),
('5.25','organizacione','Procena i odluka o bezbednosnim događajima'),
('5.26','organizacione','Odgovor na bezbednosne incidente'),
('5.27','organizacione','Učenje iz bezbednosnih incidenata'),
('5.28','organizacione','Prikupljanje dokaza'),
('5.29','organizacione','Bezbednost informacija tokom poremećaja'),
('5.30','organizacione','Spremnost IKT sistema za kontinuitet poslovanja'),
('5.31','organizacione','Pravni, statutarni, regulatorni i ugovorni zahtevi'),
('5.32','organizacione','Prava intelektualne svojine'),
('5.33','organizacione','Zaštita zapisa'),
('5.34','organizacione','Privatnost i zaštita ličnih podataka'),
('5.35','organizacione','Nezavisna provera bezbednosti informacija'),
('5.36','organizacione','Usklađenost sa politikama, pravilima i standardima'),
('5.37','organizacione','Dokumentovane operativne procedure'),
-- A.6 Ljudske kontrole (8)
('6.1','ljudske','Provera pre zapošljavanja'),
('6.2','ljudske','Uslovi zaposlenja'),
('6.3','ljudske','Svest, edukacija i obuka o bezbednosti informacija'),
('6.4','ljudske','Disciplinski proces'),
('6.5','ljudske','Odgovornosti posle prestanka ili promene zaposlenja'),
('6.6','ljudske','Ugovori o poverljivosti'),
('6.7','ljudske','Rad na daljinu'),
('6.8','ljudske','Prijava bezbednosnih događaja'),
-- A.7 Fizičke kontrole (14)
('7.1','fizicke','Fizički bezbednosni perimetri'),
('7.2','fizicke','Fizički ulazak'),
('7.3','fizicke','Obezbeđivanje kancelarija, prostorija i objekata'),
('7.4','fizicke','Fizičko bezbednosno praćenje'),
('7.5','fizicke','Zaštita od fizičkih i ekoloških pretnji'),
('7.6','fizicke','Rad u bezbednim zonama'),
('7.7','fizicke','Čist radni sto i čist ekran'),
('7.8','fizicke','Postavljanje i zaštita opreme'),
('7.9','fizicke','Bezbednost sredstava van prostorija'),
('7.10','fizicke','Mediji za skladištenje'),
('7.11','fizicke','Prateće instalacije'),
('7.12','fizicke','Bezbednost kablova'),
('7.13','fizicke','Održavanje opreme'),
('7.14','fizicke','Bezbedno odlaganje ili ponovna upotreba opreme'),
-- A.8 Tehnološke kontrole (34)
('8.1','tehnoloske','Korisnički uređaji'),
('8.2','tehnoloske','Privilegovana prava pristupa'),
('8.3','tehnoloske','Ograničenje pristupa informacijama'),
('8.4','tehnoloske','Pristup izvornom kodu'),
('8.5','tehnoloske','Bezbedna autentifikacija'),
('8.6','tehnoloske','Upravljanje kapacitetom'),
('8.7','tehnoloske','Zaštita od malvera'),
('8.8','tehnoloske','Upravljanje tehničkim ranjivostima'),
('8.9','tehnoloske','Upravljanje konfiguracijom'),
('8.10','tehnoloske','Brisanje informacija'),
('8.11','tehnoloske','Maskiranje podataka'),
('8.12','tehnoloske','Sprečavanje curenja podataka'),
('8.13','tehnoloske','Rezervne kopije informacija'),
('8.14','tehnoloske','Redundantnost objekata za obradu informacija'),
('8.15','tehnoloske','Logovanje'),
('8.16','tehnoloske','Aktivnosti praćenja'),
('8.17','tehnoloske','Sinhronizacija časovnika'),
('8.18','tehnoloske','Upotreba privilegovanih uslužnih programa'),
('8.19','tehnoloske','Instalacija softvera na operativnim sistemima'),
('8.20','tehnoloske','Mrežna bezbednost'),
('8.21','tehnoloske','Bezbednost mrežnih usluga'),
('8.22','tehnoloske','Segregacija mreža'),
('8.23','tehnoloske','Veb filtriranje'),
('8.24','tehnoloske','Kriptografija'),
('8.25','tehnoloske','Bezbedan životni ciklus razvoja'),
('8.26','tehnoloske','Bezbednosni zahtevi aplikacije'),
('8.27','tehnoloske','Bezbedna arhitektura i inženjerski principi'),
('8.28','tehnoloske','Bezbedno kodiranje'),
('8.29','tehnoloske','Bezbednosno testiranje u razvoju i prihvatanju'),
('8.30','tehnoloske','Autsorsovan razvoj'),
('8.31','tehnoloske','Razdvajanje razvojnog, test i produkcionog okruženja'),
('8.32','tehnoloske','Upravljanje promenama'),
('8.33','tehnoloske','Test podaci'),
('8.34','tehnoloske','Zaštita informacionih sistema tokom audit testiranja');

-- =====================================================================
-- Kraj šeme.
-- Napomena za dalji razvoj: tabele iz Modula 5-11 (sistemi, dobavljači,
-- lokacije, incidenti...) namerno su generičke, ne "IT-only" - isti
-- obrazac koristi i proizvodni pogon, i advokatska kancelarija, i
-- turistička agencija iz primera na blogu, svaka sa svojim sadržajem.
-- =====================================================================


-- db/migrations/001_add_isms_resources.sql
--
-- Dodaje tabelu za Klauzulu 7.1 (Resursi) - šta je obezbeđeno za ISMS:
-- budžet, osoblje, alati/licence, obuka, infrastruktura.
--
-- PRIMENA NA POSTOJEĆU BAZU (bez gubitka podataka - db_data volume
-- ostaje netaknut, ovo samo dodaje jednu novu tabelu):
--
--   docker exec -i InfoSecRelax_db mysql \
--       -u infosecrelax_user -pinfosecrelax_password infosecrelax \
--       < db/migrations/001_add_isms_resources.sql
--
-- ZA BUDUĆE SVEŽE INSTALACIJE (nov, prazan Docker volume):
--   docker-entrypoint-initdb.d pokreće samo db/init.sql, ne i fajlove iz
--   ove migrations/ fascikle. Da svež "docker-compose up" odmah dobije i
--   ovu tabelu, prekopiraj CREATE TABLE blok ispod na kraj db/init.sql,
--   pre "-- Kraj šeme" komentara. Namerno nije urađeno automatski ovde -
--   izmena celog init.sql fajla nosi rizik greške pri prepisivanju, dok
--   je ovaj mali dodatak siguran da se doda ručno, uz kopiranje.
--
-- IF NOT EXISTS čini ovaj fajl bezbednim za slučajno ponovno pokretanje.

CREATE TABLE IF NOT EXISTS isms_resources (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    resource_type       ENUM('budzet','osoblje','alat_ili_licenca','obuka','infrastruktura','ostalo') NOT NULL,
    description         TEXT NOT NULL,
    amount_or_quantity  VARCHAR(255) NULL COMMENT 'npr. 40.000 RSD, 2 dana mesecno, 1 licenca',
    provided_by         BIGINT UNSIGNED NULL COMMENT 'ko je odobrio/obezbedio',
    status              ENUM('planirano','obezbedjeno','u_koriscenju') NOT NULL DEFAULT 'planirano',
    review_date         DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (provided_by) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_resources_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Klauzula 7.1 - resursi obezbedjeni za ISMS.';
--
-- Dodaje tabelu za A.5.29 (bezbednost informacija tokom poremećaja) i
-- A.5.30 (spremnost IKT sistema za kontinuitet poslovanja) - plan
-- odgovora po scenariju prekida, sa istorijom poslednjeg testiranja.
--
-- ZA BUDUĆE SVEŽE INSTALACIJE: prekopirati CREATE TABLE blok ispod na
-- kraj db/init.sql, pre "-- Kraj šeme" komentara (isti princip kao
-- 001_add_isms_resources.sql).
--
-- IF NOT EXISTS čini ovaj fajl bezbednim za slučajno ponovno pokretanje.

CREATE TABLE IF NOT EXISTS continuity_plans (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    scenario            VARCHAR(255) NOT NULL COMMENT 'npr. Nestanak struje, Pad glavnog servera',
    plan_description    TEXT NOT NULL,
    owner_id            BIGINT UNSIGNED NULL,
    last_tested_at      DATE NULL,
    test_result         ENUM('uspesno','delimicno_uspesno','neuspesno','nije_testirano') NOT NULL DEFAULT 'nije_testirano',
    next_test_due       DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_continuity_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='A.5.29-5.30 - planovi kontinuiteta poslovanja po scenariju prekida.';
--
-- Dodaje tabelu za A.5.31-5.36 (Usklađenost) - jedan registar za svih
-- šest kontrola (pravni zahtevi, intelektualna svojina, zaštita
-- zapisa, privatnost, nezavisna provera, usklađenost sa politikama),
-- razlikovanih preko control_ref.
--
-- Postojeća legal_requirements tabela (koja pokriva samo A.5.31)
-- ostaje u šemi neiskorišćena - ista situacija kao equipment,
-- storage_media i slične tabele bez svoje stavke menija. Jedan
-- dosledan registar za svih šest kontrola je jednostavniji za
-- održavanje od dva paralelna mehanizma.
-- IF NOT EXISTS čini ovaj fajl bezbednim za slučajno ponovno pokretanje.

CREATE TABLE IF NOT EXISTS compliance_items (
    id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    organization_id     BIGINT UNSIGNED NOT NULL,
    control_ref         ENUM('5.31','5.32','5.33','5.34','5.35','5.36') NOT NULL,
    title               VARCHAR(255) NOT NULL,
    description         TEXT NULL,
    status              ENUM('usaglaseno','delimicno','neusaglaseno','nije_primenjivo') NOT NULL DEFAULT 'delimicno',
    owner_id            BIGINT UNSIGNED NULL,
    last_reviewed_at    DATE NULL,
    next_review_due     DATE NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (organization_id) REFERENCES organizations(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES personnel(id) ON DELETE SET NULL,
    INDEX idx_compliance_org (organization_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='A.5.31-5.36 - registar uskladjenosti po kontroli.';
-- Dodaje tabelu za sadržaj pomoći po stranici - nezavisno od
-- organization_id, jer je isti tekst za sve (objašnjava standard, ne
-- podatke konkretne firme). Zato ova tabela namerno NIJE deo
-- demo-data.sql niti se briše pri uvozu demo podataka - sadržaj pomoći
-- treba da preživi bez obzira na to koliko puta se demo podaci uvezu.
--
-- Sadržaj se čuva kao sirov HTML (ne čist tekst) da bi mogao da nosi
-- naslove, liste i linkove ka spoljnim izvorima - uređuje se direktno
-- kroz modal na svakoj stranici (dugme "Uredi" unutar prozorčića
-- pomoći), ne kroz poseban administratorski ekran.
 
CREATE TABLE IF NOT EXISTS help_content (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_slug   VARCHAR(100) NOT NULL,
    title       VARCHAR(255) NOT NULL,
    body        TEXT NOT NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_help_content_slug (page_slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Sadrzaj pomoci po stranici - deljen za sve organizacije, uredjuje se kroz modal na samoj stranici.';
 
-- Seed za kontekst.php - prva stranica koja dobija pomoć, ujedno i
-- primer kako izgleda popunjen sadržaj (može se slobodno prepraviti
-- kroz dugme "Uredi" u samom modalu).
INSERT IGNORE INTO help_content (page_slug, title, body) VALUES (
    'kontekst',
    'Pomoć — Kontekst organizacije',
    '<p>Klauzula 4.1 traži da firma identifikuje spoljne i unutrašnje faktore koji mogu uticati na to da li će sistem bezbednosti informacija (ISMS) zaista postići svoju svrhu. Ovo nije formalnost - na ovome počiva sve što dolazi kasnije: obim ISMS-a, procena rizika, pa i sami ciljevi bezbednosti.</p>
 
<h4>Spoljni faktori</h4>
<p>Sve ono što dolazi izvan firme, na šta firma nema direktan uticaj, ali mora da se prilagodi. Obično su to:</p>
<ul>
<li><strong>Zakonski i regulatorni</strong> - npr. zakon o zaštiti podataka o ličnosti, propisi za vašu delatnost.</li>
<li><strong>Tržišni</strong> - npr. zahtevi klijenata za sertifikatom, konkurencija koja već ima ISO 27001.</li>
<li><strong>Tehnološki</strong> - npr. novi tipovi napada relevantni za vašu delatnost, promene kod dobavljača softvera.</li>
<li><strong>Ekonomski i društveni</strong> - npr. rast cena IT usluga, promena navika korisnika (rad od kuće).</li>
</ul>
 
<h4>Unutrašnji faktori</h4>
<p>Sve ono što je deo same firme - njena struktura, kultura, resursi, tehnologija koju koristi. Primeri:</p>
<ul>
<li><strong>Organizacioni</strong> - npr. broj zaposlenih, način rada (hibridno, na daljinu), ograničeni IT resursi.</li>
<li><strong>Tehnološki</strong> - npr. koji sistemi se koriste, da li su u cloud-u ili lokalno.</li>
<li><strong>Ljudski</strong> - npr. nivo IT pismenosti zaposlenih, fluktuacija kadra.</li>
</ul>
 
<h4>Nekoliko saveta</h4>
<ul>
<li>Faktor ne mora biti savršeno formulisan da bi bio koristan - bolje ga uneti kratko i vratiti se kasnije nego čekati "idealnu" rečenicu.</li>
<li>Nije potrebno nabrojati baš sve što postoji - fokusirajte se na ono što stvarno utiče na zaštitu informacija.</li>
<li>Ovaj spisak nije zauvek - vredi ga pregledati kad se nešto značajno promeni (novi zakon, novi sistem, veći rast firme).</li>
<li>Ovde možeš dodati i linkove ka spoljnim vodičima ili tekstu standarda koje tvoj tim koristi kao referencu.</li>
</ul>'
);
 
