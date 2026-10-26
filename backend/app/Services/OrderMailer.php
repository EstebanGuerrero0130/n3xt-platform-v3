<?php

namespace App\Services;

use App\Mail\CustomerMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Avisos por correo al cliente: pedido recibido y cada cambio de estado.
 * Se envían después de responder, para que el SMTP no frene al admin.
 */
class OrderMailer
{
    /** @var array<int, array{0: string, 1: CustomerMail}> correos por enviar al cerrar la petición */
    private array $pending = [];
    private bool $flushRegistered = false;

    public const STATUS_LABELS = [
        'pending' => 'Recibido',
        'printing' => 'En impresión',
        'post-processing' => 'En acabado',
        'completed' => 'Listo',
        'shipped' => 'Enviado',
        'cancelled' => 'Cancelado',
    ];

    private const MESSAGES = [
        'pending' => ['Recibimos tu pedido', 'El taller va a revisar tus archivos y te confirmará el precio definitivo antes de imprimir.'],
        'printing' => ['Tu pedido está en impresión', 'Tus piezas ya están en la impresora.'],
        'post-processing' => ['Tu pedido está en acabado', 'Estamos limpiando, curando y dando el acabado a tus piezas.'],
        'completed' => ['Tu pedido está listo', 'Tus piezas están terminadas y listas para entrega o envío.'],
        'shipped' => ['Tu pedido va en camino', 'Despachamos tu pedido. Con la guía puedes seguir la entrega en la página de la transportadora.'],
        'cancelled' => ['Tu pedido fue cancelado', 'Si tienes dudas sobre la cancelación, escríbenos por WhatsApp.'],
    ];

    public function received(Order $order): void
    {
        $this->queue($order, 'pending');
    }

    public function statusChanged(Order $order, ?string $oldStatus): void
    {
        if ($order->status !== $oldStatus) {
            $this->queue($order, $order->status);
        }
    }

    private function queue(Order $order, string $status): void
    {
        if (!$order->customer_email || !isset(self::MESSAGES[$status])) {
            return;
        }

        [$headline, $intro] = self::MESSAGES[$status];
        $trackUrl = config('app.frontend_url') . '/track?' . http_build_query([
            'order_id' => $order->id,
            'email' => $order->customer_email,
        ]);
        $mail = new CustomerMail("{$headline} · Pedido #{$order->id}", 'mail.order-update', [
            'order' => $order,
            'headline' => $headline,
            'intro' => $intro,
            'statusLabel' => self::STATUS_LABELS[$status],
            'trackUrl' => $trackUrl,
        ]);
        $this->pending[] = [$order->customer_email, $mail];

        // Un solo callback por instancia, y la cola se vacía al enviar: los callbacks
        // de terminating no se limpian entre peticiones (tests, Octane)
        if (!$this->flushRegistered) {
            $this->flushRegistered = true;
            app()->terminating(fn () => $this->flush());
        }
    }

    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];
        foreach ($pending as [$to, $mail]) {
            try {
                Mail::to($to)->send($mail);
            } catch (\Throwable $e) {
                Log::error('No se pudo enviar el aviso del pedido', ['subject' => $mail->mailSubject, 'error' => $e->getMessage()]);
            }
        }
    }
}
