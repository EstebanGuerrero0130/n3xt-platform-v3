<?php

namespace App\Services;

use App\Mail\CustomerMail;
use App\Models\Inventory;
use App\Models\Printer;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos por correo al taller: stock bajo y mantenimiento de máquinas.
 *
 * Cada ítem avisa una sola vez (`*_alerted_at`); la marca se borra cuando se
 * repone el stock o se hace el mantenimiento, y así puede volver a avisar.
 * Se revisa al cerrar la petición que cambió stock u horas, y a diario (cron).
 */
class WorkshopAlerts
{
    private bool $dirty = false;
    private bool $registered = false;

    /** Revisa todo al terminar la petición actual (una vez, aunque se pida varias). */
    public function checkLater(): void
    {
        $this->dirty = true;
        // Un solo callback por instancia: los de terminating no se limpian entre
        // peticiones (tests, Octane), así que solo revisa si algo cambió
        if (!$this->registered) {
            $this->registered = true;
            app()->terminating(function () {
                if ($this->dirty) {
                    $this->dirty = false;
                    $this->check();
                }
            });
        }
    }

    /** @return array{stock: int, maintenance: int} cuántos avisos nuevos se enviaron */
    public function check(): array
    {
        $low = $this->lowStock();
        $due = $this->maintenanceDue();
        if (!$low && !$due) {
            return ['stock' => 0, 'maintenance' => 0];
        }

        $to = $this->recipient();
        if (!$to) {
            Log::warning('Hay avisos del taller pero no hay correo de destino (ALERTS_EMAIL).');
            return ['stock' => 0, 'maintenance' => 0];
        }

        try {
            Mail::to($to)->send(new CustomerMail($this->subject($low, $due), 'mail.workshop-alert', [
                'lowStock' => $low,
                'maintenance' => $due,
                'adminUrl' => config('app.frontend_url') . '/admin',
            ]));
        } catch (\Throwable $e) {
            // Sin marcar: se vuelve a intentar en la próxima revisión
            Log::error('No se pudo enviar el aviso del taller', ['error' => $e->getMessage()]);
            return ['stock' => 0, 'maintenance' => 0];
        }

        Inventory::whereIn('id', array_column($low, 'id'))->update(['low_stock_alerted_at' => now()]);
        Printer::whereIn('id', array_column($due, 'id'))->update(['maintenance_alerted_at' => now()]);

        return ['stock' => count($low), 'maintenance' => count($due)];
    }

    /** Ítems que bajaron del umbral y aún no se avisaron; limpia la marca de los repuestos. */
    private function lowStock(): array
    {
        $items = Inventory::with('material')->get();
        $restocked = $items->filter(fn ($i) => $i->low_stock_alerted_at && $i->stock_available > $i->low_stock_threshold);
        if ($restocked->isNotEmpty()) {
            Inventory::whereIn('id', $restocked->pluck('id'))->update(['low_stock_alerted_at' => null]);
        }

        return $items
            ->filter(fn ($i) => !$i->low_stock_alerted_at && $i->low_stock_threshold > 0 && $i->stock_available <= $i->low_stock_threshold)
            ->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->material?->name ?? $i->material_id,
                'stock' => $i->stock_available,
                'threshold' => $i->low_stock_threshold,
                'unit' => $i->material?->unit ?? '',
            ])->values()->all();
    }

    /** Máquinas que llegaron a sus horas o a su fecha de mantenimiento y aún no se avisaron. */
    private function maintenanceDue(): array
    {
        $printers = Printer::all();
        $isDue = fn (Printer $p) => ($p->maintenance_interval_h > 0 && $p->total_hours_run >= $p->maintenance_interval_h)
            || ($p->next_maintenance && now()->startOfDay()->gte($p->next_maintenance));

        $done = $printers->filter(fn ($p) => $p->maintenance_alerted_at && !$isDue($p));
        if ($done->isNotEmpty()) {
            Printer::whereIn('id', $done->pluck('id'))->update(['maintenance_alerted_at' => null]);
        }

        return $printers
            ->filter(fn ($p) => !$p->maintenance_alerted_at && $isDue($p))
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'hours' => round((float) $p->total_hours_run, 1),
                'interval' => (int) $p->maintenance_interval_h,
                'date' => $p->next_maintenance ? \Illuminate\Support\Carbon::parse($p->next_maintenance)->format('d/m/Y') : null,
            ])->values()->all();
    }

    private function subject(array $low, array $due): string
    {
        $parts = [];
        if ($low) $parts[] = count($low) === 1 ? 'stock bajo de ' . $low[0]['name'] : count($low) . ' ítems con stock bajo';
        if ($due) $parts[] = count($due) === 1 ? 'mantenimiento de ' . $due[0]['name'] : count($due) . ' máquinas por mantenimiento';
        return 'Aviso del taller: ' . implode(' y ', $parts);
    }

    /** ALERTS_EMAIL, si no el correo de la empresa en Parámetros, si no el primer admin. */
    private function recipient(): ?string
    {
        $company = Setting::where('key', 'company')->value('value');
        return config('services.alerts.email')
            ?: (is_array($company) ? ($company['email'] ?? null) : null)
            ?: User::orderBy('id')->value('email');
    }
}
