-- ============================================================
-- N3XT 3D — DATOS INICIALES (SEED)
-- ============================================================
-- Ejecutar DESPUÉS del schema principal.
-- Supabase Dashboard → SQL Editor → New Query
-- ============================================================

-- ============================================================
-- USUARIO ADMINISTRADOR
-- Password: el hash que necesites generar desde Laravel
-- Puedes generar uno con: php artisan tinker → bcrypt('tu_password')
-- El siguiente hash corresponde a la contraseña: "administrador@12-@"
-- ============================================================
INSERT INTO users (name, email, password, created_at, updated_at)
VALUES (
    'N3XT Admin',
    'admin@n3xt3d.com',
    '$2y$10$7hQSgyEy1qSZW44A6Scri.rcwKkhNXNleP1LyeDVL4gG8Y7YS230q',
    NOW(),
    NOW()
) ON CONFLICT (email) DO NOTHING;

-- ============================================================
-- MATERIALES BASE (FDM)
-- ============================================================
INSERT INTO materials (id, name, category, type, unit, density, cost_per_kg, color, is_active, created_at, updated_at) VALUES
('FDM_PLA_01',    'PLA Estándar',       'FDM', 'material', 'g', 1.2400, 45000,  'Blanco',    TRUE, NOW(), NOW()),
('FDM_PLA_02',    'PLA Matte',          'FDM', 'material', 'g', 1.2400, 55000,  'Negro',     TRUE, NOW(), NOW()),
('FDM_PETG_01',   'PETG Industrial',    'FDM', 'material', 'g', 1.2700, 65000,  'Natural',   TRUE, NOW(), NOW()),
('FDM_ABS_01',    'ABS Técnico',        'FDM', 'material', 'g', 1.0400, 55000,  'Gris',      TRUE, NOW(), NOW()),
('FDM_TPU_01',    'TPU Flexible 95A',   'FDM', 'material', 'g', 1.2100, 85000,  'Negro',     TRUE, NOW(), NOW()),
('FDM_CF_01',     'PLA Fibra Carbono',  'FDM', 'material', 'g', 1.2900, 120000, 'Negro',     TRUE, NOW(), NOW())
ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- MATERIALES BASE (SLA / Resina)
-- ============================================================
INSERT INTO materials (id, name, category, type, unit, density, cost_per_kg, color, is_active, created_at, updated_at) VALUES
('SLA_STD_01',    'Resina Estándar',     'SLA', 'material', 'ml', 1.1000, 95000,  'Gris',     TRUE, NOW(), NOW()),
('SLA_TGH_01',    'Resina Tough',        'SLA', 'material', 'ml', 1.1300, 140000, 'Negro',    TRUE, NOW(), NOW()),
('SLA_FLX_01',    'Resina Flexible',     'SLA', 'material', 'ml', 1.0500, 160000, 'Natural',  TRUE, NOW(), NOW()),
('SLA_CAST_01',   'Resina Casteable',    'SLA', 'material', 'ml', 1.1800, 180000, 'Verde',    TRUE, NOW(), NOW())
ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- CONSUMIBLES / SERVICIOS
-- ============================================================
INSERT INTO materials (id, name, category, type, unit, density, cost_per_kg, color, is_active, created_at, updated_at) VALUES
('Alco_ML_05',    'Alcohol Isopropílico (por ml)',  'SLA', 'utility',  'ml',    NULL, 15,    NULL, TRUE, NOW(), NOW()),
('Cicl_Serv_06',  'Ciclo de Curado UV',            'SLA', 'service',  'pieza', NULL, 3000,  NULL, TRUE, NOW(), NOW()),
('LIJA_SET_07',   'Kit Lijado (400-2000)',          'FDM', 'utility',  'pieza', NULL, 8000,  NULL, TRUE, NOW(), NOW()),
('PINT_PRIM_08',  'Primer Gris Spray',             'FDM', 'utility',  'pieza', NULL, 15000, NULL, TRUE, NOW(), NOW())
ON CONFLICT (id) DO NOTHING;

-- ============================================================
-- INVENTARIO INICIAL (Stock base)
-- ============================================================
INSERT INTO inventories (material_id, stock_available, low_stock_threshold, created_at, updated_at) VALUES
('FDM_PLA_01',   3000, 500, NOW(), NOW()),
('FDM_PLA_02',   2000, 500, NOW(), NOW()),
('FDM_PETG_01',  2500, 500, NOW(), NOW()),
('FDM_ABS_01',   1500, 500, NOW(), NOW()),
('FDM_TPU_01',   1000, 300, NOW(), NOW()),
('FDM_CF_01',     800, 300, NOW(), NOW()),
('SLA_STD_01',   1000, 200, NOW(), NOW()),
('SLA_TGH_01',    500, 200, NOW(), NOW()),
('SLA_FLX_01',    500, 200, NOW(), NOW()),
('SLA_CAST_01',   300, 150, NOW(), NOW())
ON CONFLICT DO NOTHING;

-- ============================================================
-- CONFIGURACIÓN INICIAL DEL SISTEMA
-- ============================================================
INSERT INTO settings (key, value, created_at, updated_at) VALUES
('infra', '{"luz_hr": 926, "depr_hr": 400, "mant_hr": 700, "etiquetas": 500, "load_factor": 0.4}', NOW(), NOW()),
('prep',  '{"mano_obra_hr": 1000, "prep_time_pct": 10}', NOW(), NOW()),
('oper',  '{"transporte": 50, "ganancia": 50, "marketing": 50, "fallos": 30}', NOW(), NOW()),
('margin','{"iva": 19}', NOW(), NOW()),
('web',   '{"workshop_status": "Operativo 24/7", "social": {"tiktok": "https://www.tiktok.com/@n3xt3d", "instagram": "", "youtube": "", "whatsapp": "https://wa.me/573118796416"}, "catalog": [], "pdf_catalog_url": "", "pdf_catalog_desc": ""}', NOW(), NOW()),
('company', '{"name": "N3XT 3D", "email": "servicion3xt@gmail.com", "phone": "+57 311 879 6416", "address": "Colombia"}', NOW(), NOW())
ON CONFLICT (key) DO NOTHING;

-- ============================================================
-- IMPRESORA DE EJEMPLO
-- ============================================================
INSERT INTO printers (name, model, technology, watts, status, total_hours_run, maintenance_interval_h, created_at, updated_at) VALUES
('Alpha-1', 'Ender 3 V2 Neo', 'FDM', 350, 'idle', 0, 200, NOW(), NOW()),
('Beta-1',  'Elegoo Saturn 4', 'SLA', 60,  'idle', 0, 100, NOW(), NOW())
ON CONFLICT DO NOTHING;

-- ============================================================
-- ✅ SEED COMPLETADO
-- El sistema N3XT está listo para operar.
-- ============================================================
