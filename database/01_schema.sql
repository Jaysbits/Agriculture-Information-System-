-- =====================================================================
-- AgriSys — Agriculture Information System
-- Schema for Supabase PostgreSQL (15+)
--
-- HOW TO USE
--   1. Open your Supabase project -> SQL Editor -> New query.
--   2. Paste this whole file and click "Run". It builds a complete,
--      empty database (WARNING: drops any existing AgriSys tables).
--   3. Run 02_seed.sql next to load lookup values, demo records and
--      the default user accounts.
--
-- Design notes
--   * Lookup / reference tables hold descriptive values; the core
--     tables store only the numeric foreign key (requirement: controlled
--     dropdown values with a label stored in a separate table).
--   * Every table carries created_at / updated_at. Core tables also
--     record created_by / updated_by (audit information).
--   * updated_at is maintained by the set_updated_at() trigger.
--   * Cross-table business rules (planted area vs. farm size, harvest
--     date vs. planting date, farm size vs. planted area) are enforced
--     with trigger functions so the rules hold even outside the app.
--   * Foreign keys use ON DELETE RESTRICT so a record that is still
--     referenced can never be deleted. The application catches the
--     resulting error and shows a friendly message.
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 0. Rebuild (fresh install only)
-- ---------------------------------------------------------------------
DROP VIEW  IF EXISTS v_harvest_detail          CASCADE;
DROP VIEW  IF EXISTS v_planting_detail         CASCADE;
DROP TABLE IF EXISTS harvest                   CASCADE;
DROP TABLE IF EXISTS planting                  CASCADE;
DROP TABLE IF EXISTS farm                      CASCADE;
DROP TABLE IF EXISTS crop                      CASCADE;
DROP TABLE IF EXISTS farmer                    CASCADE;
DROP TABLE IF EXISTS harvest_remark            CASCADE;
DROP TABLE IF EXISTS quality_grade             CASCADE;
DROP TABLE IF EXISTS season                    CASCADE;
DROP TABLE IF EXISTS crop_category             CASCADE;
DROP TABLE IF EXISTS planting_status           CASCADE;
DROP TABLE IF EXISTS irrigation_type           CASCADE;
DROP TABLE IF EXISTS soil_type                 CASCADE;
DROP TABLE IF EXISTS app_user                  CASCADE;
DROP FUNCTION IF EXISTS set_updated_at()       CASCADE;
DROP FUNCTION IF EXISTS validate_farm()        CASCADE;
DROP FUNCTION IF EXISTS validate_planting()    CASCADE;
DROP FUNCTION IF EXISTS validate_harvest()     CASCADE;
DROP FUNCTION IF EXISTS fn_audit_stamp()       CASCADE;

-- ---------------------------------------------------------------------
-- 1. Users (authentication + role based access control)
-- ---------------------------------------------------------------------
CREATE TABLE app_user (
    user_id        SERIAL       PRIMARY KEY,
    username       VARCHAR(50)  NOT NULL UNIQUE,
    full_name      VARCHAR(120) NOT NULL,
    password_hash  TEXT         NOT NULL,
    role           VARCHAR(20)  NOT NULL DEFAULT 'staff'
                                CHECK (role IN ('admin', 'staff')),
    is_active      BOOLEAN      NOT NULL DEFAULT TRUE,
    last_login_at  TIMESTAMPTZ,
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ  NOT NULL DEFAULT now(),
    CONSTRAINT chk_app_user_username_len CHECK (char_length(username) >= 3)
);

COMMENT ON TABLE app_user IS 'Application accounts. Passwords are stored as bcrypt hashes, never plain text.';

-- ---------------------------------------------------------------------
-- 2. Lookup / reference tables
--    (descriptive label lives here, numeric id lives in core tables)
-- ---------------------------------------------------------------------
CREATE TABLE soil_type (
    soil_type_id    SERIAL       PRIMARY KEY,
    soil_type_name  VARCHAR(100) NOT NULL UNIQUE,
    created_at      TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE irrigation_type (
    irrigation_id    SERIAL       PRIMARY KEY,
    irrigation_name  VARCHAR(100) NOT NULL UNIQUE,
    created_at       TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE planting_status (
    status_id    SERIAL       PRIMARY KEY,
    status_name  VARCHAR(100) NOT NULL UNIQUE,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE crop_category (
    category_id    SERIAL       PRIMARY KEY,
    category_name  VARCHAR(100) NOT NULL UNIQUE,
    created_at     TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at     TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE season (
    season_id    SERIAL       PRIMARY KEY,
    season_name  VARCHAR(100) NOT NULL UNIQUE,
    created_at   TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at   TIMESTAMPTZ  NOT NULL DEFAULT now()
);

CREATE TABLE quality_grade (
    grade_id    SERIAL       PRIMARY KEY,
    grade_name  VARCHAR(100) NOT NULL UNIQUE,
    created_at  TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- Harvest remarks: numeric id 1..5 maps to the descriptions required
-- by the project specification.
CREATE TABLE harvest_remark (
    remark_id           SERIAL       PRIMARY KEY,
    remark_description  VARCHAR(255) NOT NULL UNIQUE,
    created_at          TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at          TIMESTAMPTZ  NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- 3. Core tables
-- ---------------------------------------------------------------------
CREATE TABLE farmer (
    farmer_id        SERIAL       PRIMARY KEY,
    first_name       VARCHAR(100) NOT NULL,
    last_name        VARCHAR(100) NOT NULL,
    contact_number   VARCHAR(30)  NOT NULL UNIQUE,
    address          VARCHAR(255) NOT NULL,
    date_registered  DATE         NOT NULL DEFAULT CURRENT_DATE
                                  CHECK (date_registered >= DATE '2000-01-01'),
    created_at       TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at       TIMESTAMPTZ  NOT NULL DEFAULT now(),
    created_by       INTEGER      REFERENCES app_user (user_id) ON DELETE SET NULL,
    updated_by       INTEGER      REFERENCES app_user (user_id) ON DELETE SET NULL,
    CONSTRAINT chk_farmer_names CHECK (
        first_name <> '' AND last_name <> ''
        AND first_name !~ '[0-9]' AND last_name !~ '[0-9]'
    ),
    CONSTRAINT chk_farmer_contact CHECK (contact_number ~ '^[0-9+\-\s()]{7,30}$')
);

CREATE TABLE farm (
    farm_id         SERIAL        PRIMARY KEY,
    farmer_id       INTEGER       NOT NULL
                                  REFERENCES farmer (farmer_id) ON DELETE RESTRICT,
    soil_type_id    INTEGER       NOT NULL
                                  REFERENCES soil_type (soil_type_id) ON DELETE RESTRICT,
    irrigation_id   INTEGER       NOT NULL
                                  REFERENCES irrigation_type (irrigation_id) ON DELETE RESTRICT,
    farm_name       VARCHAR(150)  NOT NULL,
    location        VARCHAR(255)  NOT NULL,
    area_hectares   NUMERIC(10,2) NOT NULL,
    created_at      TIMESTAMPTZ   NOT NULL DEFAULT now(),
    updated_at      TIMESTAMPTZ   NOT NULL DEFAULT now(),
    created_by      INTEGER       REFERENCES app_user (user_id) ON DELETE SET NULL,
    updated_by      INTEGER       REFERENCES app_user (user_id) ON DELETE SET NULL,
    CONSTRAINT uq_farm_farmer_name UNIQUE (farmer_id, farm_name),
    CONSTRAINT chk_farm_area CHECK (area_hectares > 0 AND area_hectares <= 10000)
);

CREATE TABLE crop (
    crop_id               SERIAL       PRIMARY KEY,
    category_id           INTEGER      NOT NULL
                                       REFERENCES crop_category (category_id) ON DELETE RESTRICT,
    season_id             INTEGER      REFERENCES season (season_id) ON DELETE RESTRICT,
    crop_name             VARCHAR(150) NOT NULL,
    variety               VARCHAR(100),
    growing_period_days   INTEGER      NOT NULL,
    created_at            TIMESTAMPTZ  NOT NULL DEFAULT now(),
    updated_at            TIMESTAMPTZ  NOT NULL DEFAULT now(),
    created_by            INTEGER      REFERENCES app_user (user_id) ON DELETE SET NULL,
    updated_by            INTEGER      REFERENCES app_user (user_id) ON DELETE SET NULL,
    CONSTRAINT chk_crop_period CHECK (growing_period_days BETWEEN 1 AND 3650)
);

-- A crop name + variety pair must not be duplicated.
CREATE UNIQUE INDEX uq_crop_name_variety
    ON crop (crop_name, COALESCE(variety, ''));

CREATE TABLE planting (
    planting_id            SERIAL        PRIMARY KEY,
    farm_id                INTEGER       NOT NULL
                                         REFERENCES farm (farm_id) ON DELETE RESTRICT,
    crop_id                INTEGER       NOT NULL
                                         REFERENCES crop (crop_id) ON DELETE RESTRICT,
    status_id              INTEGER       NOT NULL
                                         REFERENCES planting_status (status_id) ON DELETE RESTRICT,
    planting_date          DATE          NOT NULL,
    area_planted           NUMERIC(10,2) NOT NULL,
    seed_quantity          NUMERIC(10,2) NOT NULL DEFAULT 0,
    expected_harvest_date  DATE,
    created_at             TIMESTAMPTZ   NOT NULL DEFAULT now(),
    updated_at             TIMESTAMPTZ   NOT NULL DEFAULT now(),
    created_by             INTEGER       REFERENCES app_user (user_id) ON DELETE SET NULL,
    updated_by             INTEGER       REFERENCES app_user (user_id) ON DELETE SET NULL,
    CONSTRAINT chk_planting_area CHECK (area_planted > 0),
    CONSTRAINT chk_planting_seed CHECK (seed_quantity >= 0),
    CONSTRAINT chk_planting_dates CHECK (
        expected_harvest_date IS NULL OR expected_harvest_date >= planting_date
    )
);

CREATE TABLE harvest (
    harvest_id            SERIAL        PRIMARY KEY,
    planting_id           INTEGER       NOT NULL
                                        REFERENCES planting (planting_id) ON DELETE RESTRICT,
    grade_id              INTEGER       NOT NULL
                                        REFERENCES quality_grade (grade_id) ON DELETE RESTRICT,
    remark_id             INTEGER       NOT NULL
                                        REFERENCES harvest_remark (remark_id) ON DELETE RESTRICT,
    harvest_date          DATE          NOT NULL,
    quantity_kg           NUMERIC(12,2) NOT NULL,
    selling_price_per_kg  NUMERIC(12,2) NOT NULL,
    created_at            TIMESTAMPTZ   NOT NULL DEFAULT now(),
    updated_at            TIMESTAMPTZ   NOT NULL DEFAULT now(),
    created_by            INTEGER       REFERENCES app_user (user_id) ON DELETE SET NULL,
    updated_by            INTEGER       REFERENCES app_user (user_id) ON DELETE SET NULL,
    CONSTRAINT chk_harvest_qty CHECK (quantity_kg > 0 AND quantity_kg <= 1000000),
    CONSTRAINT chk_harvest_price CHECK (selling_price_per_kg >= 0 AND selling_price_per_kg <= 100000)
);

-- ---------------------------------------------------------------------
-- 4. Indexes (foreign keys + common filters / sorts)
-- ---------------------------------------------------------------------
CREATE INDEX idx_farm_farmer        ON farm (farmer_id);
CREATE INDEX idx_farm_soil_type     ON farm (soil_type_id);
CREATE INDEX idx_farm_irrigation    ON farm (irrigation_id);

CREATE INDEX idx_crop_category      ON crop (category_id);
CREATE INDEX idx_crop_season        ON crop (season_id);

CREATE INDEX idx_planting_farm      ON planting (farm_id);
CREATE INDEX idx_planting_crop      ON planting (crop_id);
CREATE INDEX idx_planting_status    ON planting (status_id);
CREATE INDEX idx_planting_date      ON planting (planting_date);

CREATE INDEX idx_harvest_planting   ON harvest (planting_id);
CREATE INDEX idx_harvest_grade      ON harvest (grade_id);
CREATE INDEX idx_harvest_remark     ON harvest (remark_id);
CREATE INDEX idx_harvest_date       ON harvest (harvest_date);

-- ---------------------------------------------------------------------
-- 5. Audit timestamps
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_at := now();
    RETURN NEW;
END;
$$;

DO $$
DECLARE t TEXT;
BEGIN
    FOREACH t IN ARRAY ARRAY[
        'app_user', 'soil_type', 'irrigation_type', 'planting_status',
        'crop_category', 'season', 'quality_grade', 'harvest_remark',
        'farmer', 'farm', 'crop', 'planting', 'harvest'
    ] LOOP
        EXECUTE format(
            'DROP TRIGGER IF EXISTS trg_%1$s_updated_at ON %1$s;
             CREATE TRIGGER trg_%1$s_updated_at
             BEFORE UPDATE ON %1$s
             FOR EACH ROW EXECUTE FUNCTION set_updated_at();', t);
    END LOOP;
END;
$$;

-- Record which user created / last changed a core record.
CREATE OR REPLACE FUNCTION fn_audit_stamp()
RETURNS TRIGGER
LANGUAGE plpgsql AS $$
BEGIN
    NEW.updated_by := NULLIF(current_setting('agrisys.user_id', true), '')::INTEGER;
    IF TG_OP = 'INSERT' THEN
        NEW.created_by := COALESCE(NEW.created_by, NEW.updated_by);
    END IF;
    RETURN NEW;
END;
$$;

DO $$
DECLARE t TEXT;
BEGIN
    FOREACH t IN ARRAY ARRAY['farmer', 'farm', 'crop', 'planting', 'harvest'] LOOP
        EXECUTE format(
            'DROP TRIGGER IF EXISTS trg_%1$s_audit ON %1$s;
             CREATE TRIGGER trg_%1$s_audit
             BEFORE INSERT OR UPDATE ON %1$s
             FOR EACH ROW EXECUTE FUNCTION fn_audit_stamp();', t);
    END LOOP;
END;
$$;

-- ---------------------------------------------------------------------
-- 6. Cross-table business rules
-- ---------------------------------------------------------------------
-- A planting cannot use more land than its farm has.
CREATE OR REPLACE FUNCTION validate_planting()
RETURNS TRIGGER
LANGUAGE plpgsql AS $$
DECLARE
    v_area  NUMERIC(10,2);
    v_name  VARCHAR(150);
BEGIN
    SELECT area_hectares, farm_name INTO v_area, v_name
    FROM farm WHERE farm_id = NEW.farm_id;

    IF v_area IS NOT NULL AND NEW.area_planted > v_area THEN
        RAISE EXCEPTION
            'Planted area (% ha) is larger than the farm size (% ha) of "%".',
            NEW.area_planted, v_area, v_name
            USING ERRCODE = '23514';
    END IF;

    IF NEW.expected_harvest_date IS NOT NULL
       AND NEW.expected_harvest_date < NEW.planting_date THEN
        RAISE EXCEPTION
            'Expected harvest date cannot be earlier than the planting date.'
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_planting_validate ON planting;
CREATE TRIGGER trg_planting_validate
BEFORE INSERT OR UPDATE ON planting
FOR EACH ROW EXECUTE FUNCTION validate_planting();

-- A farm's size cannot be reduced below the area already planted.
CREATE OR REPLACE FUNCTION validate_farm()
RETURNS TRIGGER
LANGUAGE plpgsql AS $$
DECLARE
    v_used NUMERIC(10,2);
BEGIN
    SELECT COALESCE(SUM(area_planted), 0) INTO v_used
    FROM planting WHERE farm_id = NEW.farm_id;

    IF NEW.area_hectares < v_used THEN
        RAISE EXCEPTION
            'Farm size (% ha) cannot be smaller than the planted area (% ha).',
            NEW.area_hectares, v_used
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_farm_validate ON farm;
CREATE TRIGGER trg_farm_validate
BEFORE UPDATE OF area_hectares ON farm
FOR EACH ROW EXECUTE FUNCTION validate_farm();

-- A harvest cannot happen before its planting was made.
CREATE OR REPLACE FUNCTION validate_harvest()
RETURNS TRIGGER
LANGUAGE plpgsql AS $$
DECLARE
    v_planted DATE;
BEGIN
    SELECT planting_date INTO v_planted
    FROM planting WHERE planting_id = NEW.planting_id;

    IF v_planted IS NOT NULL AND NEW.harvest_date < v_planted THEN
        RAISE EXCEPTION
            'Harvest date (%) cannot be earlier than the planting date (%).',
            NEW.harvest_date, v_planted
            USING ERRCODE = '23514';
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS trg_harvest_validate ON harvest;
CREATE TRIGGER trg_harvest_validate
BEFORE INSERT OR UPDATE ON harvest
FOR EACH ROW EXECUTE FUNCTION validate_harvest();

-- ---------------------------------------------------------------------
-- 7. Join views used by lists, dashboard and reports
-- ---------------------------------------------------------------------
CREATE VIEW v_planting_detail AS
SELECT p.planting_id,
       p.farm_id,
       f.farm_name,
       f.farmer_id,
       fa.first_name || ' ' || fa.last_name AS farmer_name,
       p.crop_id,
       c.crop_name,
       c.variety,
       p.status_id,
       ps.status_name,
       p.planting_date,
       p.area_planted,
       p.seed_quantity,
       p.expected_harvest_date,
       p.created_at,
       p.updated_at
FROM planting p
JOIN farm   f  ON f.farm_id  = p.farm_id
JOIN farmer fa ON fa.farmer_id = f.farmer_id
JOIN crop   c  ON c.crop_id  = p.crop_id
JOIN planting_status ps ON ps.status_id = p.status_id;

CREATE VIEW v_harvest_detail AS
SELECT h.harvest_id,
       h.planting_id,
       p.planting_date,
       p.farm_id,
       f.farm_name,
       f.farmer_id,
       fa.first_name || ' ' || fa.last_name AS farmer_name,
       p.crop_id,
       c.crop_name,
       c.variety,
       h.grade_id,
       qg.grade_name,
       h.remark_id,
       hr.remark_description,
       h.harvest_date,
       h.quantity_kg,
       h.selling_price_per_kg,
       ROUND(h.quantity_kg * h.selling_price_per_kg, 2) AS estimated_value,
       h.created_at,
       h.updated_at
FROM harvest h
JOIN planting p  ON p.planting_id  = h.planting_id
JOIN farm     f  ON f.farm_id      = p.farm_id
JOIN farmer   fa ON fa.farmer_id   = f.farmer_id
JOIN crop     c  ON c.crop_id      = p.crop_id
JOIN quality_grade  qg ON qg.grade_id  = h.grade_id
JOIN harvest_remark hr ON hr.remark_id = h.remark_id;

COMMIT;
