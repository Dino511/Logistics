<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        Gate::authorize('manage-users');

        return view('users.index', [
            'users' => User::orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }

    public function updateRole(Request $request, User $user)
    {
        Gate::authorize('manage-users');

        $data = $request->validate(['role' => ['required', Rule::enum(Role::class)]]);
        $newRole = Role::from($data['role']);

        if ($user->is($request->user())) {
            return back()->with('error', 'You cannot change your own role.');
        }
        if ($user->isSuperAdmin() && $newRole !== Role::SuperAdmin && $this->isLastActiveSuperAdmin($user)) {
            return back()->with('error', 'At least one active Super Admin must remain.');
        }

        $oldRole = $user->role->label();
        $user->forceFill(['role' => $newRole])->save();
        ActivityLog::record('role_changed', "Changed {$user->name}'s role from {$oldRole} to {$newRole->label()}", $user);

        return back()->with('status', "{$user->name} is now {$newRole->label()}.");
    }

    public function toggleActive(Request $request, User $user)
    {
        Gate::authorize('manage-users');

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

    private function isLastActiveSuperAdmin(User $user): bool
    {
        return ! User::where('role', Role::SuperAdmin->value)->where('is_active', true)->where('id', '!=', $user->id)->exists();
    }
}
