<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RecurrentCustomer;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Equipo del taller (solo el dueño). El personal entra con su correo y ve solo
 * las secciones que se le den.
 */
class TeamController extends Controller
{
    use ApiResponse;

    public function index(Request $request)
    {
        return $this->success(User::orderBy('id')->get()->map(fn (User $u) => $this->present($u, $request->user())));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:10|max:255',
            'role' => ['required', Rule::in(['owner', 'staff'])],
            'permissions' => 'array',
            'permissions.*' => ['string', Rule::in(User::SECTIONS)],
        ]);
        $data['email'] = strtolower($data['email']);
        // Un correo de cliente no puede ser del taller: el login elegiría la cuenta del taller
        if (RecurrentCustomer::whereRaw('LOWER(email) = ?', [$data['email']])->exists()) {
            return $this->error('Ese correo ya es de una cuenta de cliente.', 422);
        }

        $user = User::create([...$data, 'permissions' => $data['role'] === 'staff' ? array_values(array_unique($data['permissions'] ?? [])) : null]);

        return $this->success($this->present($user, $request->user()), 'Usuario creado.', 201);
    }

    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'password' => 'nullable|string|min:10|max:255',
            'role' => ['sometimes', Rule::in(['owner', 'staff'])],
            'permissions' => 'sometimes|array',
            'permissions.*' => ['string', Rule::in(User::SECTIONS)],
        ]);

        if (($data['role'] ?? $user->role) === 'staff' && $user->isOwner() && $this->isLastOwner($user)) {
            return $this->error('Debe quedar al menos un dueño.', 422);
        }
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (isset($data['permissions'])) {
            $data['permissions'] = array_values(array_unique($data['permissions']));
        }
        $user->fill($data);
        if ($user->isOwner()) {
            $user->permissions = null;
        }
        $user->save();

        return $this->success($this->present($user, $request->user()), 'Usuario actualizado.');
    }

    public function destroy(Request $request, int $id)
    {
        $user = User::findOrFail($id);
        if ($user->is($request->user())) {
            return $this->error('No puedes eliminar tu propio usuario.', 422);
        }
        if ($user->isOwner() && $this->isLastOwner($user)) {
            return $this->error('Debe quedar al menos un dueño.', 422);
        }
        $user->tokens()->delete();
        $user->delete();

        return $this->success(null, 'Usuario eliminado.');
    }

    private function isLastOwner(User $user): bool
    {
        return !User::where('id', '!=', $user->id)->where(fn ($q) => $q->where('role', '!=', 'staff')->orWhereNull('role'))->exists();
    }

    private function present(User $u, ?User $me): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->isOwner() ? 'owner' : 'staff',
            'permissions' => $u->permissions ?? [],
            'is_me' => $me !== null && $u->is($me),
        ];
    }
}
