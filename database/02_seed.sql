-- =====================================================================
-- AgriSys — Agriculture Information System
-- Seed data for Supabase PostgreSQL (15+)
--
-- HOW TO USE
--   1. Run 01_schema.sql first (it creates the 13 tables and 2 views).
--   2. Paste this whole file into the SQL Editor and click "Run".
--
-- WARNING
--   This script TRUNCATEs every AgriSys table before loading, so it can be
--   re-run safely on a development database. Never run it on a database
--   that already holds real data.
--
-- WHAT IT LOADS
--   * 2 user accounts  (admin / admin123, staff / staff123)
--   * 7 lookup / reference tables (the controlled dropdown values)
--   * 8 farmers, 10 farms, 8 crops, 13 plantings, 10 harvests
--   * explicit primary keys so the foreign keys below line up, then the
--     identity sequences are advanced past the seeded ids
--
-- Demo passwords are bcrypt hashes. CHANGE THEM before submitting or
-- deploying (sign in as admin -> User Accounts -> edit -> set a password).
-- =====================================================================

BEGIN;

-- ---------------------------------------------------------------------
-- 0. Reset (fresh seed only)
-- ---------------------------------------------------------------------
TRUNCATE TABLE
    harvest, planting, farm, farmer, crop,
    quality_grade, season, crop_category, planting_status,
    irrigation_type, soil_type, harvest_remark, app_user
RESTART IDENTITY CASCADE;

-- ---------------------------------------------------------------------
-- 1. User accounts
--    admin / admin123   ·   staff / staff123
-- ---------------------------------------------------------------------
INSERT INTO app_user (user_id, username, full_name, password_hash, role, is_active) VALUES
    (1, 'admin', 'System Administrator',
     '$2y$10$EHehKGt2GEeujorxkthOAeV8meKBX7AsC.Cm82urobNh6WX4Et0RS', 'admin', TRUE),
    (2, 'staff', 'Farm Field Staff',
     '$2y$10$it8MSSTSzp5qdquVtaNmQeqzXAIxWQWGpvhUAYW7T4i9uxznAnOya', 'staff', TRUE);

-- ---------------------------------------------------------------------
-- 2. Lookup / reference tables
--    The label lives here; the core tables store only the numeric id.
-- ---------------------------------------------------------------------
INSERT INTO soil_type (soil_type_id, soil_type_name) VALUES
    (1, 'Clay Loam'),
    (2, 'Sandy Loam'),
    (3, 'Silt Loam'),
    (4, 'Loam'),
    (5, 'Clay'),
    (6, 'Sandy'),
    (7, 'Volcanic Loam');

INSERT INTO irrigation_type (irrigation_id, irrigation_name) VALUES
    (1, 'Rainfed'),
    (2, 'Drip Irrigation'),
    (3, 'Sprinkler'),
    (4, 'Furrow'),
    (5, 'Pump / Manual');

INSERT INTO planting_status (status_id, status_name) VALUES
    (1, 'Planned'),
    (2, 'Planted'),
    (3, 'Growing'),
    (4, 'Ready for Harvest'),
    (5, 'Harvested'),
    (6, 'Failed');

INSERT INTO crop_category (category_id, category_name) VALUES
    (1, 'Cereal'),
    (2, 'Vegetable'),
    (3, 'Fruit'),
    (4, 'Root Crop'),
    (5, 'Legume'),
    (6, 'Industrial Crop');

INSERT INTO season (season_id, season_name) VALUES
    (1, 'Dry Season'),
    (2, 'Wet Season'),
    (3, 'Year-round');

INSERT INTO quality_grade (grade_id, grade_name) VALUES
    (1, 'Grade A - Premium'),
    (2, 'Grade B - Good'),
    (3, 'Grade C - Fair'),
    (4, 'Grade D - Poor');

-- Remark ids 1..5 match the descriptions required by the project spec.
INSERT INTO harvest_remark (remark_id, remark_description) VALUES
    (1, 'Good Harvest'),
    (2, 'Acceptable Quality'),
    (3, 'Low Yield'),
    (4, 'Poor Quality'),
    (5, 'Damaged Harvest');

-- ---------------------------------------------------------------------
-- 3. Farmers
-- ---------------------------------------------------------------------
INSERT INTO farmer (farmer_id, first_name, last_name, contact_number, address, date_registered, created_by) VALUES
    (1, 'Juan',     'Dela Cruz', '09171234567', 'Barangay Talisay, Talisay City, Cebu',   '2023-01-15', 1),
    (2, 'Maria',    'Santos',    '09182234567', 'Barangay Poblacion, Argao, Cebu',        '2023-02-20', 1),
    (3, 'Pedro',    'Reyes',     '09193234567', 'Barangay Basak, Cebu City, Cebu',        '2023-03-05', 1),
    (4, 'Ana',      'Villanueva','09204234567', 'Barangay Lagtang, Talisay City, Cebu',   '2023-05-12', 1),
    (5, 'Jose',     'Bacalso',   '09215234567', 'Barangay Guadalupe, Cebu City, Cebu',    '2023-07-08', 1),
    (6, 'Lourdes',  'Manalili',  '09226234567', 'Barangay Ocaña, Carcar City, Cebu',      '2023-09-18', 1),
    (7, 'Ricardo',  'Abellana',  '09237234567', 'Barangay Tanke, Talisay City, Cebu',     '2024-01-22', 1),
    (8, 'Elena',    'Cabrera',   '09248234567', 'Barangay Poblacion, Naga City, Cebu',    '2024-03-11', 1);

-- ---------------------------------------------------------------------
-- 4. Farms
-- ---------------------------------------------------------------------
INSERT INTO farm (farm_id, farmer_id, soil_type_id, irrigation_id, farm_name, location, area_hectares, created_by) VALUES
    (1,  1, 1, 1, 'Dela Cruz North Field',       'Talisay City, Cebu', 2.50, 1),
    (2,  1, 4, 2, 'Dela Cruz Riverside Plot',    'Talisay City, Cebu', 1.20, 1),
    (3,  2, 2, 4, 'Santos Cornfield',            'Argao, Cebu',        3.00, 1),
    (4,  2, 3, 3, 'Santos Backlot Garden',       'Argao, Cebu',        0.80, 1),
    (5,  3, 7, 1, 'Reyes Upland Farm',           'Cebu City, Cebu',    1.50, 1),
    (6,  4, 4, 2, 'Villanueva Vegetable Garden', 'Talisay City, Cebu', 0.60, 1),
    (7,  5, 5, 5, 'Bacalso Rice Terrace',        'Cebu City, Cebu',    4.00, 1),
    (8,  6, 2, 1, 'Manalili Mango Grove',        'Carcar City, Cebu',  2.20, 1),
    (9,  7, 4, 3, 'Abellana Mixed Farm',         'Talisay City, Cebu', 1.80, 1),
    (10, 8, 7, 5, 'Cabrera Highland Plot',       'Naga City, Cebu',    1.10, 1);

-- ---------------------------------------------------------------------
-- 5. Crops
-- ---------------------------------------------------------------------
INSERT INTO crop (crop_id, category_id, season_id, crop_name, variety, growing_period_days, created_by) VALUES
    (1, 1, 2, 'Rice',     'NSIC Rc 222',           115, 1),
    (2, 1, 2, 'Corn',     'Pioneer 30T80',          100, 1),
    (3, 2, 3, 'Eggplant', 'Dumaguete Long Purple',   75, 1),
    (4, 2, 1, 'Tomato',   'Diamante Max',            65, 1),
    (5, 3, 1, 'Mango',    'Carabao',               1095, 1),
    (6, 3, 3, 'Banana',   'Lakatan',                300, 1),
    (7, 4, 3, 'Cassava',  'KU-50',                  270, 1),
    (8, 5, 1, 'Mongo',    'Pag-asa 7',               60, 1);

-- ---------------------------------------------------------------------
-- 6. Plantings
--    (area_planted must not exceed the farm size — enforced by trigger)
-- ---------------------------------------------------------------------
INSERT INTO planting (planting_id, farm_id, crop_id, status_id, planting_date, area_planted, seed_quantity, expected_harvest_date, created_by) VALUES
    (1,  1,  2, 5, '2026-06-17', 1.50,  25.00, '2026-09-25', 1),
    (2,  3,  2, 5, '2026-06-27', 2.50,  40.00, '2026-10-05', 1),
    (3,  2,  4, 4, '2026-09-15', 0.80,   2.00, '2026-11-19', 1),
    (4,  6,  3, 3, '2026-09-30', 0.50,   1.20, '2026-12-14', 1),
    (5,  7,  1, 3, '2026-09-05', 3.00,  60.00, '2026-12-29', 1),
    (6,  5,  7, 3, '2026-08-16', 1.00, 500.00, '2027-05-13', 1),
    (7,  8,  5, 5, '2025-10-10', 2.00,   0.00, '2026-10-10', 1),
    (8,  4,  8, 5, '2026-08-06', 0.50,  12.00, '2026-10-10', 1),
    (9,  9,  3, 2, '2026-10-25', 0.90,   2.50, '2027-01-08', 1),
    (10, 10, 6, 3, '2026-07-17', 0.80, 300.00, '2027-05-13', 1),
    (11, 1,  1, 1, '2026-11-24', 1.00,  20.00, '2027-03-19', 1),
    (12, 3,  8, 6, '2026-07-17', 1.20,  20.00, '2026-09-15', 1),
    (13, 6,  4, 4, '2026-09-20', 0.60,   1.50, '2026-11-22', 1);

-- ---------------------------------------------------------------------
-- 7. Harvests
--    (harvest_date must not be earlier than the planting date — trigger)
-- ---------------------------------------------------------------------
INSERT INTO harvest (harvest_id, planting_id, grade_id, remark_id, harvest_date, quantity_kg, selling_price_per_kg, created_by) VALUES
    (1,  1,  1, 1, '2026-09-20', 4200.00, 22.50, 1),
    (2,  1,  2, 2, '2026-09-23', 1500.00, 18.00, 1),
    (3,  2,  1, 1, '2026-09-30', 6800.00, 21.00, 1),
    (4,  7,  1, 1, '2026-10-15', 3200.00, 65.00, 1),
    (5,  7,  3, 4, '2026-10-20',  800.00, 35.00, 1),
    (6,  8,  2, 2, '2026-10-07',  420.00, 85.00, 1),
    (7,  4,  2, 2, '2026-11-09',  150.00, 40.00, 1),
    (8,  5,  1, 1, '2026-11-04',  900.00, 25.00, 1),
    (9,  12, 4, 5, '2026-09-10',   60.00, 70.00, 1),
    (10, 2,  3, 3, '2026-10-04',  900.00, 19.50, 1);

-- ---------------------------------------------------------------------
-- 8. Advance the identity sequences past the seeded ids
-- ---------------------------------------------------------------------
SELECT setval(pg_get_serial_sequence('app_user',      'user_id'),    (SELECT MAX(user_id)    FROM app_user));
SELECT setval(pg_get_serial_sequence('soil_type',     'soil_type_id'), (SELECT MAX(soil_type_id) FROM soil_type));
SELECT setval(pg_get_serial_sequence('irrigation_type','irrigation_id'),(SELECT MAX(irrigation_id) FROM irrigation_type));
SELECT setval(pg_get_serial_sequence('planting_status','status_id'),  (SELECT MAX(status_id)  FROM planting_status));
SELECT setval(pg_get_serial_sequence('crop_category', 'category_id'), (SELECT MAX(category_id) FROM crop_category));
SELECT setval(pg_get_serial_sequence('season',        'season_id'),   (SELECT MAX(season_id)  FROM season));
SELECT setval(pg_get_serial_sequence('quality_grade', 'grade_id'),    (SELECT MAX(grade_id)   FROM quality_grade));
SELECT setval(pg_get_serial_sequence('harvest_remark','remark_id'),   (SELECT MAX(remark_id)  FROM harvest_remark));
SELECT setval(pg_get_serial_sequence('farmer',        'farmer_id'),   (SELECT MAX(farmer_id)  FROM farmer));
SELECT setval(pg_get_serial_sequence('farm',          'farm_id'),     (SELECT MAX(farm_id)    FROM farm));
SELECT setval(pg_get_serial_sequence('crop',          'crop_id'),     (SELECT MAX(crop_id)    FROM crop));
SELECT setval(pg_get_serial_sequence('planting',      'planting_id'), (SELECT MAX(planting_id) FROM planting));
SELECT setval(pg_get_serial_sequence('harvest',       'harvest_id'),  (SELECT MAX(harvest_id) FROM harvest));

COMMIT;
