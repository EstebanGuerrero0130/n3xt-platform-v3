-- ============================================================
-- N3XT 3D INDUSTRIAL PLATFORM — SUPABASE SCHEMA
-- ============================================================
-- PostgreSQL schema consolidado desde 34 migraciones de Laravel.
-- Ejecutar en: Supabase Dashboard → SQL Editor → New Query
-- Fecha de generación: 2026-05-14
-- ============================================================

-- ============================================================
-- EXTENSIONES REQUERIDAS
-- ============================================================
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- ============================================================
-- 1. TABLA: users (Administradores del sistema)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    email_verified_at TIMESTAMP NULL,
    password VARCHAR(255) NOT NULL,
    pin_code VARCHAR(4) NULL,
    remember_token VARCHAR(100) NULL,
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);

-- ============================================================
-- 2. TABLA: password_reset_tokens
-- ============================================================
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email VARCHAR(255) PRIMARY KEY,
    token VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NULL
);

-- ============================================================
-- 3. TABLA: sessions (Sesiones de usuario)
-- ============================================================
CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    payload TEXT NOT NULL,
    last_activity INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_sessions_user_id ON sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_sessions_last_activity ON sessions(last_activity);

-- ============================================================
-- 4. TABLA: materials (Materiales e insumos)
-- ============================================================
CREATE TABLE IF NOT EXISTS materials (
    id VARCHAR(255) PRIMARY KEY,                          -- e.g., FDM-PLA-001
    name VARCHAR(255) NOT NULL,                           -- e.g., PLA, Resina Tough
    category VARCHAR(255) NOT NULL,                       -- e.g., FDM, SLA
    type VARCHAR(255) NOT NULL DEFAULT 'material',        -- material, utility, service
    unit VARCHAR(255) NOT NULL DEFAULT 'g',               -- g, ml, pieza, metro, servicio
    density DECIMAL(8, 4) NULL,                           -- g/cm3
    cost_per_kg DECIMAL(12, 2) NOT NULL DEFAULT 0,        -- Precio por Kg o Litro
    color VARCHAR(255) NULL,
    location VARCHAR(255) NULL,                           -- Ubicación en taller
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);

-- ============================================================
-- 5. TABLA: inventories (Stock de materiales)
-- ============================================================
CREATE TABLE IF NOT EXISTS inventories (
    id BIGSERIAL PRIMARY KEY,
    material_id VARCHAR(255) NOT NULL REFERENCES materials(id) ON DELETE CASCADE,
    stock_available DECIMAL(10, 2) NOT NULL DEFAULT 0,    -- en gramos o ml
    low_stock_threshold DECIMAL(10, 2) NOT NULL DEFAULT 500,
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);

-- ============================================================
-- 6. TABLA: printers (Impresoras 3D)
-- ============================================================
CREATE TABLE IF NOT EXISTS printers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,                           -- e.g., Alpha-1
    model VARCHAR(255) NOT NULL,                          -- e.g., Ender 3 V2
    technology VARCHAR(255) NOT NULL,                     -- FDM o SLA
    watts INTEGER NOT NULL DEFAULT 0,                     -- Consumo eléctrico
    status VARCHAR(255) NOT NULL DEFAULT 'idle',          -- idle, printing, maintenance
    total_hours_run DOUBLE PRECISION NOT NULL DEFAULT 0,
    maintenance_interval_h INTEGER NOT NULL DEFAULT 200,
    last_maintenance DATE NULL,
    next_maintenance DATE NULL,
    maintenance_notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);

-- ============================================================
-- 7. TABLA: orders (Pedidos / Órdenes de fabricación)
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id BIGSERIAL PRIMARY KEY,
    project_id BIGINT NULL,                               -- ID de proyecto agrupador

    -- Nombre del proyecto
    job_name VARCHAR(255) NULL,

    -- Datos del cliente
    customer_name VARCHAR(255) NOT NULL,
    customer_company VARCHAR(255) NULL,
    customer_id_document VARCHAR(50) NULL,                 -- NIT / Cédula
    customer_email VARCHAR(255) NULL,
    customer_phone VARCHAR(255) NULL,
    customer_phone_secondary VARCHAR(255) NULL,

    -- Datos de envío
    shipping_address VARCHAR(255) NULL,
    shipping_city VARCHAR(255) NULL,
    shipping_zip VARCHAR(255) NULL,
    shipping_reference VARCHAR(255) NULL,

    -- Datos técnicos del modelo
    volume_mm3 DECIMAL(10, 2) NOT NULL DEFAULT 0,
    dimensions_mm VARCHAR(255) NULL,                      -- e.g., "50x30x20"
    scale_factor DECIMAL(8, 4) NOT NULL DEFAULT 1.0,
    estimated_weight_g DECIMAL(10, 2) NULL,
    qty INTEGER NOT NULL DEFAULT 1,
    technology VARCHAR(255) NOT NULL,                     -- FDM o SLA
    material_id VARCHAR(255) NOT NULL,
    material_name VARCHAR(255) NULL,
    infill INTEGER NULL,                                  -- Porcentaje de relleno (FDM)

    -- Costos
    total_price DECIMAL(12, 2) NOT NULL DEFAULT 0,
    extras_cost DECIMAL(10, 2) NOT NULL DEFAULT 0,
    extra_items JSONB NULL,                               -- Array de extras seleccionados
    estimated_duration_h DOUBLE PRECISION NOT NULL DEFAULT 0,
    cost_snapshot JSONB NULL,                              -- Snapshot del desglose de costos

    -- Metadata
    comments TEXT NULL,
    file_path VARCHAR(255) NULL,                          -- Ruta del archivo STL
    original_filename VARCHAR(255) NULL,

    -- Estado y tracking
    status VARCHAR(255) NOT NULL DEFAULT 'pending',       -- pending, printing, completed, shipped
    stock_deducted BOOLEAN NOT NULL DEFAULT FALSE,
    hours_added_to_printer BOOLEAN NOT NULL DEFAULT FALSE,
    is_paid BOOLEAN NOT NULL DEFAULT FALSE,
    tracking_guide VARCHAR(255) NULL,
    tracking_carrier VARCHAR(255) NULL,

    -- Impresora asignada
    printer_id BIGINT NULL REFERENCES printers(id) ON DELETE SET NULL,

    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(status);
CREATE INDEX IF NOT EXISTS idx_orders_customer_email ON orders(customer_email);
CREATE INDEX IF NOT EXISTS idx_orders_printer_id ON orders(printer_id);
CREATE INDEX IF NOT EXISTS idx_orders_project_id ON orders(project_id);

-- ============================================================
-- 8. TABLA: personal_access_tokens (Laravel Sanctum)
-- ============================================================
CREATE TABLE IF NOT EXISTS personal_access_tokens (
    id BIGSERIAL PRIMARY KEY,
    tokenable_type VARCHAR(255) NOT NULL,
    tokenable_id BIGINT NOT NULL,
    name TEXT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    abilities TEXT NULL,
    last_used_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);
CREATE INDEX IF NOT EXISTS idx_pat_tokenable ON personal_access_tokens(tokenable_type, tokenable_id);
CREATE INDEX IF NOT EXISTS idx_pat_expires_at ON personal_access_tokens(expires_at);

-- ============================================================
-- 9. TABLA: settings (Configuración del sistema)
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    id BIGSERIAL PRIMARY KEY,
    key VARCHAR(255) NOT NULL UNIQUE,
    value JSONB NOT NULL DEFAULT '{}',
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);

-- ============================================================
-- 10. TABLA: purchases (Registro de compras / gastos)
-- ============================================================
CREATE TABLE IF NOT EXISTS purchases (
    id BIGSERIAL PRIMARY KEY,
    item_name VARCHAR(255) NOT NULL,
    category VARCHAR(255) NOT NULL DEFAULT 'maintenance',  -- inventory_restock, maintenance, tools, office
    material_id VARCHAR(255) NULL REFERENCES materials(id) ON DELETE SET NULL,
    units DECIMAL(10, 2) NOT NULL DEFAULT 1,               -- Cantidad de unidades (e.g., 5 spools)
    unit_amount DECIMAL(10, 2) NOT NULL DEFAULT 0,          -- Cantidad por unidad (e.g., 1000g)
    qty DECIMAL(10, 2) NOT NULL DEFAULT 1,                  -- Total calculado
    total_cost DECIMAL(10, 2) NOT NULL DEFAULT 0,
    supplier VARCHAR(255) NULL,
    notes TEXT NULL,
    purchase_date DATE NOT NULL DEFAULT CURRENT_DATE,
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);

-- ============================================================
-- 11. TABLA: recurrent_customers (Clientes recurrentes)
-- ============================================================
CREATE TABLE IF NOT EXISTS recurrent_customers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    company VARCHAR(255) NULL,
    customer_id_document VARCHAR(50) NULL,                  -- NIT / Cédula
    email VARCHAR(255) NULL,
    password VARCHAR(255) NULL,                             -- Para portal de clientes
    remember_token VARCHAR(100) NULL,
    phone VARCHAR(255) NULL,
    phone_secondary VARCHAR(255) NULL,
    location VARCHAR(255) NULL,
    address_full VARCHAR(255) NULL,
    city_dept_country VARCHAR(255) NULL,
    zip_code VARCHAR(255) NULL,
    location_reference VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);

-- ============================================================
-- 12. TABLA: recurrent_suppliers (Proveedores recurrentes)
-- ============================================================
CREATE TABLE IF NOT EXISTS recurrent_suppliers (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255) NULL,
    phone VARCHAR(255) NULL,
    specialty VARCHAR(255) NULL,
    created_at TIMESTAMP NULL DEFAULT NOW(),
    updated_at TIMESTAMP NULL DEFAULT NOW()
);

-- ============================================================
-- 13. TABLA: inventario_3d (Datos JSON del inventario 3D)
-- ============================================================
CREATE TABLE IF NOT EXISTS inventario_3d (
    id VARCHAR(255) PRIMARY KEY,
    datos_json TEXT NULL,
    ultima_modificacion TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ============================================================
-- 14. TABLAS DE CACHE (Laravel Cache Driver)
-- ============================================================
CREATE TABLE IF NOT EXISTS cache (
    key VARCHAR(255) PRIMARY KEY,
    value TEXT NOT NULL,
    expiration BIGINT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_cache_expiration ON cache(expiration);

CREATE TABLE IF NOT EXISTS cache_locks (
    key VARCHAR(255) PRIMARY KEY,
    owner VARCHAR(255) NOT NULL,
    expiration BIGINT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_cache_locks_expiration ON cache_locks(expiration);

-- ============================================================
-- 15. TABLAS DE JOBS (Laravel Queue System)
-- ============================================================
CREATE TABLE IF NOT EXISTS jobs (
    id BIGSERIAL PRIMARY KEY,
    queue VARCHAR(255) NOT NULL,
    payload TEXT NOT NULL,
    attempts SMALLINT NOT NULL DEFAULT 0,
    reserved_at INTEGER NULL,
    available_at INTEGER NOT NULL,
    created_at INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_jobs_queue ON jobs(queue);

CREATE TABLE IF NOT EXISTS job_batches (
    id VARCHAR(255) PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    total_jobs INTEGER NOT NULL DEFAULT 0,
    pending_jobs INTEGER NOT NULL DEFAULT 0,
    failed_jobs INTEGER NOT NULL DEFAULT 0,
    failed_job_ids TEXT NOT NULL DEFAULT '',
    options TEXT NULL,
    cancelled_at INTEGER NULL,
    created_at INTEGER NOT NULL,
    finished_at INTEGER NULL
);

CREATE TABLE IF NOT EXISTS failed_jobs (
    id BIGSERIAL PRIMARY KEY,
    uuid VARCHAR(255) NOT NULL UNIQUE,
    connection TEXT NOT NULL,
    queue TEXT NOT NULL,
    payload TEXT NOT NULL,
    exception TEXT NOT NULL,
    failed_at TIMESTAMP NOT NULL DEFAULT NOW()
);

-- ============================================================
-- 16. TABLA DE MIGRACIONES (Laravel Migration Tracker)
-- ============================================================
CREATE TABLE IF NOT EXISTS migrations (
    id SERIAL PRIMARY KEY,
    migration VARCHAR(255) NOT NULL,
    batch INTEGER NOT NULL
);

-- ============================================================
-- INSERTAR REGISTRO DE MIGRACIONES (para que Laravel no
-- intente re-ejecutar las migraciones ya aplicadas)
-- ============================================================
INSERT INTO migrations (migration, batch) VALUES
('0001_01_01_000000_create_users_table', 1),
('0001_01_01_000001_create_cache_table', 1),
('0001_01_01_000002_create_jobs_table', 1),
('2026_04_28_195826_create_materials_table', 1),
('2026_04_28_195827_create_inventories_table', 1),
('2026_04_28_203616_create_customers_and_orders_tables', 1),
('2026_04_28_204007_create_personal_access_tokens_table', 1),
('2026_04_29_151035_create_settings_table', 1),
('2026_05_04_161830_add_manufacturing_metrics_to_orders_table', 1),
('2026_05_04_220000_create_printers_table', 1),
('2026_05_04_233111_add_maintenance_fields_to_printers_table', 1),
('2026_05_05_014837_make_file_path_nullable_on_orders_table', 1),
('2026_05_05_021727_add_watts_to_printers_table', 1),
('2026_05_05_033940_add_type_and_unit_to_materials_table', 1),
('2026_05_05_110000_add_advanced_maintenance_to_printers', 1),
('2026_05_05_110500_add_duration_to_orders', 1),
('2026_05_05_204041_add_extras_to_orders_table', 1),
('2026_05_05_210920_create_purchases_table', 1),
('2026_05_05_222129_create_contacts_tables', 1),
('2026_05_05_224047_add_cost_snapshot_to_orders_table', 1),
('2026_05_06_172926_add_stock_deducted_to_orders_table', 1),
('2026_05_06_174937_add_is_paid_to_orders_table', 1),
('2026_05_06_220641_add_location_to_materials_table', 1),
('2026_05_06_231445_add_shipping_fields_to_recurrent_customers_table', 1),
('2026_05_06_231813_add_shipping_fields_to_orders_table', 1),
('2026_05_07_152759_add_specialty_to_recurrent_suppliers_table', 1),
('2026_05_07_162929_add_customer_id_document_to_contacts_and_orders', 1),
('2026_05_09_164800_add_hours_added_to_printer_to_orders_table', 1),
('2026_05_09_172053_add_pin_code_to_users_table', 1),
('2026_05_09_230148_fix_project_id_on_orders_table', 1),
('2026_05_09_230306_make_customer_fields_nullable_on_orders_table', 1),
('2026_05_09_232002_create_inventario_3d_table', 1),
('2026_05_11_174837_add_password_to_recurrent_customers_table', 1),
('2026_05_12_003052_add_tracking_carrier_to_orders_table', 1);

-- ============================================================
-- POLÍTICAS RLS (Row Level Security) - RECOMENDADAS
-- ============================================================
-- Supabase habilita RLS por defecto. Como usamos Laravel como
-- backend (no acceso directo desde el frontend), desactivamos
-- RLS para que el backend pueda operar sin restricciones.
-- Si en el futuro usas Supabase Auth directamente, activa RLS.
-- ============================================================

ALTER TABLE users DISABLE ROW LEVEL SECURITY;
ALTER TABLE password_reset_tokens DISABLE ROW LEVEL SECURITY;
ALTER TABLE sessions DISABLE ROW LEVEL SECURITY;
ALTER TABLE materials DISABLE ROW LEVEL SECURITY;
ALTER TABLE inventories DISABLE ROW LEVEL SECURITY;
ALTER TABLE printers DISABLE ROW LEVEL SECURITY;
ALTER TABLE orders DISABLE ROW LEVEL SECURITY;
ALTER TABLE personal_access_tokens DISABLE ROW LEVEL SECURITY;
ALTER TABLE settings DISABLE ROW LEVEL SECURITY;
ALTER TABLE purchases DISABLE ROW LEVEL SECURITY;
ALTER TABLE recurrent_customers DISABLE ROW LEVEL SECURITY;
ALTER TABLE recurrent_suppliers DISABLE ROW LEVEL SECURITY;
ALTER TABLE inventario_3d DISABLE ROW LEVEL SECURITY;
ALTER TABLE cache DISABLE ROW LEVEL SECURITY;
ALTER TABLE cache_locks DISABLE ROW LEVEL SECURITY;
ALTER TABLE jobs DISABLE ROW LEVEL SECURITY;
ALTER TABLE job_batches DISABLE ROW LEVEL SECURITY;
ALTER TABLE failed_jobs DISABLE ROW LEVEL SECURITY;
ALTER TABLE migrations DISABLE ROW LEVEL SECURITY;

-- ============================================================
-- ✅ ESQUEMA N3XT 3D COMPLETADO
-- Total: 19 tablas, 34 migraciones consolidadas
-- Compatible con: Supabase PostgreSQL + Laravel 12
-- ============================================================
