-- ============================================================
-- N3XT 3D — Actualización de una base creada con supabase_schema.sql
-- (registrada hasta 2026_05_12) a la versión de octubre 2026.
--
-- Cómo usarlo:
--   1. Supabase → SQL Editor → pega este archivo completo → Run.
--      Se puede correr varias veces: todo es IF NOT EXISTS / ON CONFLICT.
--   2. No hace falta php artisan migrate: las dos migraciones de datos (cupones)
--      se marcan como hechas aquí. La de ejemplo crearía cupones N3XT10, MAKER5 y
--      FDM2026 válidos para cualquiera; en su lugar la lista queda vacía.
-- ============================================================

BEGIN;

-- users: equipo del taller (dueño / personal con permisos)
ALTER TABLE users DROP COLUMN IF EXISTS pin_code;
ALTER TABLE users ADD COLUMN IF NOT EXISTS role VARCHAR(20) NOT NULL DEFAULT 'owner';
ALTER TABLE users ADD COLUMN IF NOT EXISTS permissions JSON NULL;

-- orders
ALTER TABLE orders ADD COLUMN IF NOT EXISTS job_name VARCHAR(255) NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS models JSON NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS printing_started_at TIMESTAMP NULL;
ALTER TABLE orders ADD COLUMN IF NOT EXISTS printing_finished_at TIMESTAMP NULL;
ALTER TABLE orders ALTER COLUMN volume_mm3 TYPE DECIMAL(16, 2);
ALTER TABLE orders ALTER COLUMN estimated_weight_g TYPE DECIMAL(14, 2);

-- recurrent_customers: verificación del correo y cupón de bienvenida
ALTER TABLE recurrent_customers ADD COLUMN IF NOT EXISTS email_verified_at TIMESTAMP NULL;
ALTER TABLE recurrent_customers ADD COLUMN IF NOT EXISTS verification_code_hash VARCHAR(255) NULL;
ALTER TABLE recurrent_customers ADD COLUMN IF NOT EXISTS verification_expires_at TIMESTAMP NULL;
ALTER TABLE recurrent_customers ADD COLUMN IF NOT EXISTS verification_attempts SMALLINT NOT NULL DEFAULT 0;
ALTER TABLE recurrent_customers ADD COLUMN IF NOT EXISTS welcome_coupon VARCHAR(32) NULL;
ALTER TABLE recurrent_customers ADD COLUMN IF NOT EXISTS welcome_coupon_used_at TIMESTAMP NULL;
CREATE UNIQUE INDEX IF NOT EXISTS recurrent_customers_welcome_coupon_unique ON recurrent_customers (welcome_coupon);

-- Avisos del taller (no repetir el correo)
ALTER TABLE inventories ADD COLUMN IF NOT EXISTS low_stock_alerted_at TIMESTAMP NULL;
ALTER TABLE printers ADD COLUMN IF NOT EXISTS maintenance_alerted_at TIMESTAMP NULL;

-- Volumen de impresión de cada máquina (mm)
ALTER TABLE printers ADD COLUMN IF NOT EXISTS build_x_mm INTEGER NULL CHECK (build_x_mm >= 0);
ALTER TABLE printers ADD COLUMN IF NOT EXISTS build_y_mm INTEGER NULL CHECK (build_y_mm >= 0);
ALTER TABLE printers ADD COLUMN IF NOT EXISTS build_z_mm INTEGER NULL CHECK (build_z_mm >= 0);

-- Parámetros del cotizador por material (null = valor por defecto)
ALTER TABLE materials ADD COLUMN IF NOT EXISTS speed_factor DECIMAL(6, 3) NULL;
ALTER TABLE materials ADD COLUMN IF NOT EXISTS waste_pct DECIMAL(5, 2) NULL;
ALTER TABLE materials ADD COLUMN IF NOT EXISTS failure_pct DECIMAL(5, 2) NULL;

-- Cotizaciones guardadas
CREATE TABLE IF NOT EXISTS saved_quotes (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES recurrent_customers(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    technology VARCHAR(10) NOT NULL,
    material_id VARCHAR(255) NULL,
    infill SMALLINT NULL,
    layer_height DECIMAL(4, 2) NULL,
    models JSON NOT NULL,
    extras JSON NULL,
    estimated_total INTEGER NULL,
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);
-- RLS sin políticas: la API pública de Supabase no la ve; el backend (postgres) sí
ALTER TABLE saved_quotes ENABLE ROW LEVEL SECURITY;

-- Reseñas
CREATE TABLE IF NOT EXISTS reviews (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES recurrent_customers(id) ON DELETE CASCADE,
    order_id BIGINT NOT NULL UNIQUE REFERENCES orders(id) ON DELETE CASCADE,
    product_ids JSON NULL,
    author_name VARCHAR(255) NOT NULL,
    rating SMALLINT NOT NULL,
    comment TEXT NOT NULL,
    photo_url VARCHAR(255) NULL,
    status VARCHAR(10) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS reviews_status_created_at_index ON reviews (status, created_at);
ALTER TABLE reviews ENABLE ROW LEVEL SECURITY;

-- Cupones: lista vacía (se crean desde el panel → Descuentos)
INSERT INTO settings (key, value, created_at, updated_at)
VALUES ('coupons', '[]', NOW(), NOW())
ON CONFLICT (key) DO NOTHING;

-- La API pública de Supabase (roles anon/authenticated) no se usa: el backend entra
-- como postgres. Sin permisos, ni una tabla nueva sin RLS queda expuesta.
REVOKE ALL ON ALL TABLES IN SCHEMA public FROM anon, authenticated;
REVOKE ALL ON ALL SEQUENCES IN SCHEMA public FROM anon, authenticated;
REVOKE ALL ON ALL FUNCTIONS IN SCHEMA public FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public REVOKE ALL ON TABLES FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public REVOKE ALL ON SEQUENCES FROM anon, authenticated;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public REVOKE ALL ON FUNCTIONS FROM anon, authenticated;

-- Bucket privado para los archivos 3D de los pedidos (50 MB por archivo)
INSERT INTO storage.buckets (id, name, public, file_size_limit)
VALUES ('order-models', 'order-models', false, 52428800)
ON CONFLICT (id) DO UPDATE SET public = false;

-- Registrar como hechas las migraciones de arriba
INSERT INTO migrations (migration, batch)
SELECT m, (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations)
FROM (VALUES
    ('2026_06_02_000001_remove_pin_code_from_users_table'),
    ('2026_06_09_000001_add_job_name_to_orders_table'),
    ('2026_10_24_000001_add_models_to_orders_table'),
    ('2026_10_24_000002_seed_coupons_setting'),
    ('2026_10_26_000002_merge_admin_discounts_into_coupons'),
    ('2026_10_26_000001_add_verification_and_welcome_coupon_to_recurrent_customers'),
    ('2026_10_27_000001_create_saved_quotes_table'),
    ('2026_10_27_000002_create_reviews_table'),
    ('2026_10_28_000001_add_printing_started_at_to_orders_table'),
    ('2026_10_28_000002_add_alert_marks_to_inventories_and_printers'),
    ('2026_10_28_000003_add_role_and_permissions_to_users_table'),
    ('2026_10_28_000004_widen_order_volume_and_weight'),
    ('2026_10_29_000201_add_printing_finished_at_to_orders_table'),
    ('2026_10_29_000301_add_build_volume_to_printers_table'),
    ('2026_10_29_000101_add_quote_params_to_materials_table')
) AS pending(m)
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = pending.m);

COMMIT;
