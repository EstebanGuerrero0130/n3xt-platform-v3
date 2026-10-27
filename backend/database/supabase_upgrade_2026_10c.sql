-- ============================================================
-- N3XT 3D — Actualización 2026_10c (después de supabase_upgrade_2026_10b.sql)
--
-- Cómo usarlo:
--   Supabase → SQL Editor → pega este archivo completo → Run.
--   Se puede correr varias veces: todo es IF NOT EXISTS / WHERE NOT EXISTS.
-- ============================================================

BEGIN;

-- Pedido urgente (el cliente pagó el recargo rules.rush_pct): va primero en la cola
ALTER TABLE orders ADD COLUMN IF NOT EXISTS is_urgent BOOLEAN NOT NULL DEFAULT FALSE;

-- Pedidos existentes: el recargo quedó guardado en la copia de costos
UPDATE orders SET is_urgent = TRUE
WHERE is_urgent = FALSE AND cost_snapshot->'quote'->>'urgent' = 'true';

-- Registrar como hecha la migración de arriba
INSERT INTO migrations (migration, batch)
SELECT m, (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations)
FROM (VALUES
    ('2026_10_29_000401_add_is_urgent_to_orders_table')
) AS pending(m)
WHERE NOT EXISTS (SELECT 1 FROM migrations WHERE migration = pending.m);

COMMIT;
