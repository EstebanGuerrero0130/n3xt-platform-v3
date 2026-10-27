<?php

namespace App\Services;

use App\Mail\CustomerMail;
use App\Models\RecurrentCustomer;
use App\Models\Setting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Verificación del correo por código y cupón de bienvenida personal.
 *
 * El cupón solo existe tras verificar el correo, es de un solo uso y solo vale
 * en pedidos hechos con el correo de su dueño.
 */
class CustomerAccountService
{
    public const CODE_TTL_MINUTES = 30;
    public const MAX_ATTEMPTS = 5;
    public const DEFAULT_WELCOME_PCT = 10;
    /** Códigos nuevos por correo y por hora (cada código nuevo reinicia los intentos) */
    public const MAX_CODES_PER_HOUR = 5;

    /** Genera un código nuevo (invalida el anterior) y lo envía por correo. */
    public function sendVerificationCode(RecurrentCustomer $customer): bool
    {
        if (!$code = $this->newCode($customer)) {
            return false;
        }
        $this->deliver($customer->email, new CustomerMail('Tu código de verificación N3XT 3D', 'mail.verification-code', [
            'name' => $this->firstName($customer),
            'code' => $code,
            'minutes' => self::CODE_TTL_MINUTES,
        ]));
        return true;
    }

    /** Código para cambiar la contraseña (mismo mecanismo: prueba que el correo es suyo). */
    public function sendPasswordResetCode(RecurrentCustomer $customer): bool
    {
        if (!$code = $this->newCode($customer)) {
            return false;
        }
        $this->deliver($customer->email, new CustomerMail('Cambia tu contraseña de N3XT 3D', 'mail.password-reset-code', [
            'name' => $this->firstName($customer),
            'code' => $code,
            'minutes' => self::CODE_TTL_MINUTES,
        ]));
        return true;
    }

    /**
     * Comprueba el código. Devuelve null si quedó verificado, o el motivo del rechazo.
     */
    public function verify(RecurrentCustomer $customer, string $code): ?string
    {
        if ($customer->isVerified()) {
            return null;
        }
        if ($error = $this->checkCode($customer, $code)) {
            return $error;
        }
        $this->markVerified($customer);

        return null;
    }

    /** Cambia la contraseña con el código del correo; también deja la cuenta verificada. */
    public function resetPassword(RecurrentCustomer $customer, string $code, string $password): ?string
    {
        if ($error = $this->checkCode($customer, $code)) {
            return $error;
        }
        $customer->forceFill(['password' => Hash::make($password)])->save();
        $this->markVerified($customer);

        return null;
    }

    /** Código nuevo, o null si este correo ya pidió demasiados en la última hora. */
    private function newCode(RecurrentCustomer $customer): ?string
    {
        // Por correo (no por IP): sin esto, pedir códigos desde varias IP daría intentos sin fin
        $key = 'account-code:' . strtolower($customer->email);
        if (RateLimiter::tooManyAttempts($key, self::MAX_CODES_PER_HOUR)) {
            return null;
        }
        RateLimiter::hit($key, 3600);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $customer->forceFill([
            'verification_code_hash' => Hash::make($code),
            'verification_expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES),
            'verification_attempts' => 0,
        ])->save();

        return $code;
    }

    private function checkCode(RecurrentCustomer $customer, string $code): ?string
    {
        if (!$customer->verification_code_hash || !$customer->verification_expires_at || $customer->verification_expires_at->isPast()) {
            return 'El código venció. Pide uno nuevo.';
        }
        // El intento se reserva en la base antes de comparar: peticiones en paralelo
        // no pueden pasar del límite con el valor viejo
        $reserved = RecurrentCustomer::whereKey($customer->getKey())
            ->where('verification_attempts', '<', self::MAX_ATTEMPTS)
            ->increment('verification_attempts');
        if (!$reserved) {
            return 'Demasiados intentos. Pide un código nuevo.';
        }
        if (!Hash::check($code, $customer->verification_code_hash)) {
            return 'El código no es correcto.';
        }

        return null;
    }

    private function markVerified(RecurrentCustomer $customer): void
    {
        $wasVerified = $customer->isVerified();
        $customer->forceFill([
            'email_verified_at' => $customer->email_verified_at ?? now(),
            'verification_code_hash' => null,
            'verification_expires_at' => null,
            'verification_attempts' => 0,
        ])->save();
        if (!$wasVerified) {
            $this->issueWelcomeCoupon($customer);
        }
    }

    /** Crea el cupón de bienvenida (una sola vez por cliente) y lo envía. */
    public function issueWelcomeCoupon(RecurrentCustomer $customer): void
    {
        if ($customer->welcome_coupon) {
            return;
        }

        do {
            $code = 'BIENVENIDA' . strtoupper(Str::random(6));
        } while (RecurrentCustomer::where('welcome_coupon', $code)->exists());

        $customer->forceFill(['welcome_coupon' => $code])->save();

        $this->deliver($customer->email, new CustomerMail('Tu cupón de bienvenida N3XT 3D', 'mail.welcome-coupon', [
            'name' => $this->firstName($customer),
            'code' => $code,
            'pct' => $this->welcomePct(),
            'quoteUrl' => config('app.frontend_url') . '/quote',
        ]));
    }

    /** Dueño de un cupón de bienvenida aún sin usar, o null. */
    public function welcomeCouponOwner(?string $code): ?RecurrentCustomer
    {
        $code = strtoupper(trim((string) $code));
        if (!str_starts_with($code, 'BIENVENIDA')) {
            return null;
        }

        return RecurrentCustomer::where('welcome_coupon', $code)
            ->whereNull('welcome_coupon_used_at')
            ->whereNotNull('email_verified_at')
            ->first();
    }

    /** Cupón en el formato de QuotePricingService::findCoupon. */
    public function welcomeCoupon(RecurrentCustomer $owner): array
    {
        return [
            'code' => $owner->welcome_coupon,
            'discount_pct' => $this->welcomePct(),
            'label' => 'Cupón de bienvenida',
        ];
    }

    /** Marca el cupón como usado; false si otro pedido lo usó antes. */
    public function consumeWelcomeCoupon(RecurrentCustomer $owner): bool
    {
        return RecurrentCustomer::whereKey($owner->id)
            ->whereNull('welcome_coupon_used_at')
            ->update(['welcome_coupon_used_at' => now()]) === 1;
    }

    public function welcomePct(): float
    {
        $pct = (float) (Setting::where('key', 'welcome_coupon_pct')->value('value') ?? self::DEFAULT_WELCOME_PCT);
        return $pct > 0 && $pct <= 50 ? $pct : self::DEFAULT_WELCOME_PCT;
    }

    private function firstName(RecurrentCustomer $customer): string
    {
        return Str::before(trim((string) $customer->name), ' ') ?: 'maker';
    }

    /** Un fallo del servidor de correo no debe romper el registro ni el pedido. */
    private function deliver(?string $to, CustomerMail $mail): void
    {
        if (!$to) {
            return;
        }
        try {
            Mail::to($to)->send($mail);
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo al cliente', ['subject' => $mail->mailSubject, 'error' => $e->getMessage()]);
        }
    }
}
