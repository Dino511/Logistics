<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Users & Roles. A Super Admin manages every account. Managers and Logistics Coordinators
 * manage every account except Super Admins: they don't see them, can't change them, and
 * can't make anyone a Super Admin.
 */
class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('manage-users');
        $roles = $this->assignableRoles($request->user());

        // Anything not recognised falls back to "no filter", so a bad link still shows the list.
        $sort = $request->query('sort') === 'za' ? 'za' : 'az';
        $role = Role::tryFrom((string) $request->query('role'));
        $role = in_array($role, $roles, true) ? $role : null;
        $status = in_array($request->query('status'), ['active', 'inactive'], true) ? $request->query('status') : null;

        return view('users.index', [
            'users' => User::whereIn('role', array_column($roles, 'value'))
                ->when($role, fn ($q) => $q->where('role', $role->value))
                ->when($status, fn ($q) => $q->where('is_active', $status === 'active'))
                ->orderBy('name', $sort === 'za' ? 'desc' : 'asc')
                ->get(),
            'total' => User::whereIn('role', array_column($roles, 'value'))->count(),
            'roles' => $roles,
            'sort' => $sort,
            'filterRole' => $role,
            'status' => $status,
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        $this->authorizeManaging($request->user(), $user);

        $data = $request->validate(['role' => ['required', Rule::in(array_column($this->assignableRoles($request->user()), 'value'))]]);
        $newRole = Role::from($data['role']);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot change your own role.');
        }
        if ($user->isSuperAdmin() && $newRole !== Role::SuperAdmin && $this->isLastActiveSuperAdmin($user)) {
            return back()->with('error', 'At least one active Super Admin must remain.');
        }

        $oldRole = $user->roleLabel();
        // Driver or helper only applies to Field Personnel.
        $user->forceFill([
            'role' => $newRole,
            'field_position' => $newRole === Role::FieldPersonnel ? $user->field_position : null,
        ])->save();
        ActivityLog::record('role_changed', "Changed {$user->name}'s role from {$oldRole} to {$user->roleLabel()}", $user);

        $message = "{$user->name} is now {$user->roleLabel()}.";
        if ($newRole === Role::FieldPersonnel && ! $user->field_position) {
            $message .= ' Choose whether they are a driver or a helper.';
        }

        return back()->with('status', $message);
    }

    /** Driver or Truck / Cargo Helper, for a Field Personnel account. */
    public function updatePosition(Request $request, User $user)
    {
        $this->authorizeManaging($request->user(), $user);
        abort_unless($user->role === Role::FieldPersonnel, 422, 'Only Field Personnel have a driver or helper position.');

        $data = $request->validate(['field_position' => ['required', Rule::in(array_keys(User::FIELD_POSITIONS))]]);

        if ($data['field_position'] === 'helper' && $user->driver) {
            return back()->with('error', "{$user->name} is linked to a driver record. Unlink them on the Drivers page before making them a helper.");
        }

        $old = $user->roleLabel();
        $user->forceFill(['field_position' => $data['field_position']])->save();
        ActivityLog::record('role_changed', "Changed {$user->name} from {$old} to {$user->roleLabel()}", $user);

        return back()->with('status', "{$user->name} is now {$user->roleLabel()}.");
    }

    public function create(Request $request)
    {
        Gate::authorize('manage-users');

        return view('users.edit', ['user' => new User, 'roles' => $this->assignableRoles($request->user())]);
    }

    public function store(Request $request)
    {
        Gate::authorize('manage-users');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'role' => ['required', Rule::in(array_column($this->assignableRoles($request->user()), 'value'))],
            'field_position' => ['required_if:role,'.Role::FieldPersonnel->value, 'nullable', Rule::in(array_keys(User::FIELD_POSITIONS))],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'field_position.required_if' => 'Choose whether this Field Personnel is a driver or a helper.',
        ]);

        $role = Role::from($data['role']);
        $user = new User;
        $user->forceFill([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'], // hashed by the model's cast
            'role' => $role,
            'field_position' => $role === Role::FieldPersonnel ? $data['field_position'] : null,
            'is_active' => true,
        ])->save();

        // Never log the password itself.
        ActivityLog::record('user_created', "Added user {$user->name} ({$user->roleLabel()})", $user);

        return redirect()->route('users.index')->with('status', "{$user->name} added as {$user->roleLabel()}.");
    }

    public function edit(Request $request, User $user)
    {
        $this->authorizeManaging($request->user(), $user);

        return view('users.edit', ['user' => $user]);
    }

    /** Name, email and (optionally) a new password for any user. Blank password = unchanged. */
    public function update(Request $request, User $user)
    {
        $this->authorizeManaging($request->user(), $user);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'field_position' => [Rule::requiredIf($user->role === Role::FieldPersonnel), 'nullable', Rule::in(array_keys(User::FIELD_POSITIONS))],
            'password' => ['nullable', 'confirmed', Password::min(8)->letters()->numbers()],
        ], [
            'field_position.required' => 'Choose whether this Field Personnel is a driver or a helper.',
        ]);

        $position = $user->role === Role::FieldPersonnel ? $data['field_position'] : null;
        if ($position === 'helper' && $user->driver) {
            return back()->withInput()->with('error', "{$user->name} is linked to a driver record. Unlink them on the Drivers page before making them a helper.");
        }

        $changes = array_keys(array_filter([
            'name' => $data['name'] !== $user->name,
            'email' => $data['email'] !== $user->email,
            'position' => $position !== $user->field_position,
            'password' => filled($data['password']),
        ]));

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->field_position = $position;
        if (filled($data['password'])) {
            $user->password = $data['password']; // hashed by the model's cast
            // Ends any "remember me" logins that were using the old password.
            $user->setRememberToken(Str::random(60));
        }
        $user->save();

        // Editing your own password: keep this session signed in. AuthenticateSession saves the
        // signed-in user's password hash after the request, so it must see the new one.
        if (filled($data['password']) && $user->is($request->user())) {
            Auth::setUser($user);
        }

        if ($changes) {
            // Never log the password itself, only that it changed.
            ActivityLog::record('user_updated', "Updated {$user->name}'s account (".implode(', ', $changes).')', $user);
        }

        return redirect()->route('users.index')->with('status', $changes ? "{$user->name}'s account updated." : 'No changes made.');
    }

    public function toggleActive(Request $request, User $user)
    {
        $this->authorizeManaging($request->user(), $user);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }
        if ($user->is_active && $user->isSuperAdmin() && $this->isLastActiveSuperAdmin($user)) {
            return back()->with('error', 'At least one active Super Admin must remain.');
        }

        $user->forceFill(['is_active' => ! $user->is_active])->save();
        ActivityLog::record($user->is_active ? 'activated' : 'deactivated', ($user->is_active ? 'Activated' : 'Deactivated')." the account of {$user->name}", $user);

        return back()->with('status', $user->name.($user->is_active ? ' activated.' : ' deactivated.'));
    }

    /**
     * The roles this person may give to an account, which are also the roles they may manage.
     *
     * @return list<Role>
     */
    private function assignableRoles(User $actor): array
    {
        return array_values(array_filter(
            Role::cases(),
            fn (Role $role) => $actor->isSuperAdmin() || $role !== Role::SuperAdmin,
        ));
    }

    /** Stops anyone who isn't a Super Admin from opening or changing a Super Admin's account. */
    private function authorizeManaging(User $actor, User $target): void
    {
        Gate::authorize('manage-users');
        abort_unless($actor->isSuperAdmin() || ! $target->isSuperAdmin(), 403);
    }

    private function isLastActiveSuperAdmin(User $user): bool
    {
        return ! User::where('role', Role::SuperAdmin->value)->where('is_active', true)->where('id', '!=', $user->id)->exists();
    }
}
