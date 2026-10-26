<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'permissions'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /** Secciones del panel que se pueden dar al personal (mismos ids que el menú). */
    public const SECTIONS = ['kanban', 'inventory', 'accounting', 'machines', 'history', 'purchases', 'contacts', 'discounts', 'settings', 'web'];

    public function isOwner(): bool
    {
        return $this->role !== 'staff';
    }

    /** El dueño entra a todo; el personal, si tiene alguna de las secciones. */
    public function canAccess(string ...$sections): bool
    {
        if ($this->isOwner()) {
            return true;
        }
        return (bool) array_intersect($sections, $this->permissions ?? []);
    }

    /** Secciones visibles en el panel. */
    public function sections(): array
    {
        return $this->isOwner() ? [...self::SECTIONS, 'team'] : array_values(array_intersect(self::SECTIONS, $this->permissions ?? []));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }
}
