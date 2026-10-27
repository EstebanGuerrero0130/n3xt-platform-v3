-- ============================================================
-- N3XT 3D — Actualización 2026_10b (después de supabase_upgrade_2026_10.sql)
--
-- Cómo usarlo:
--   Supabase → SQL Editor → pega este archivo completo → Run.
--   Se puede correr varias veces: todo es IF NOT EXISTS / WHERE NOT EXISTS.
-- ============================================================

BEGIN;

-- Parámetros del cotizador por material (null = valor por defecto):
-- speed_factor × tiempo de impresión, waste_pct % de material extra,
-- failure_pct reemplaza la reserva por fallos global (oper.fallos)
ALTER TABLE materials ADD COLUMN IF NOT EXISTS speed_factor DECIMAL(6, 3) NULL;
ALTER TABLE materials ADD COLUMN IF NOT EXISTS waste_pct DECIMAL(5, 2) NULL;
ALTER TABLE materials ADD COLUMN IF NOT EXISTS failure_pct DECIMAL(5, 2) NULL;

-- Registrar como hecha la migración de arriba
INSERT INTO migrations (migration, batch)
SELECT m, (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations)
FROM (VALUES
    ('2026_10_29_000101_add_quote_params_to_materials_table')
) AS pending(m)
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = pending.m);

COMMIT;
