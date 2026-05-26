<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\StoreAdminUserRequest;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AdminUserController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        $users = User::with('roles')
            ->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (User $u) => [
                'id'         => $u->id,
                'name'       => $u->name,
                'email'      => $u->email,
                'role'       => $u->role_name,
                'tenant_id'  => $u->tenant_id,
                'is_active'  => $u->is_active ?? true,
                'created_at' => $u->created_at?->toDateString(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    public function store(StoreAdminUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $role = $validated['role'];

        $user = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'tenant_id' => null,
        ]);

        $user->assignRole($role);

        return redirect()->route('admin.users.index')
            ->with('success', "User \"{$user->name}\" created with role {$role}.");
    }

    /**
     * Promote or demote a user's platform role.
     */
    public function updateRole(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        $validated = $request->validate([
            'role' => ['required', Rule::in(['super-admin', 'admin', 'accountant', 'sales', 'viewer'])],
        ]);

        $newRole = $validated['role'];

        // Safety: cannot demote the last super-admin.
        if ($user->hasRole('super-admin') && $newRole !== 'super-admin') {
            $superAdminCount = User::role('super-admin')->count();
            if ($superAdminCount <= 1) {
                return redirect()->back()
                    ->with('error', 'Cannot demote the last platform super-admin.');
            }
        }

        $user->syncRoles([$newRole]);

        return redirect()->route('admin.users.index')
            ->with('success', "Role updated to \"{$newRole}\" for {$user->name}.");
    }

    /**
     * Send a password reset link to the user.
     */
    public function sendPasswordReset(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        Password::sendResetLink(['email' => $user->email]);

        return redirect()->route('admin.users.index')
            ->with('success', "Password reset link sent to {$user->email}.");
    }

    /**
     * Toggle a user's active status (suspend / restore).
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()->hasRole('super-admin'), 403);

        // Cannot suspend yourself.
        if ($user->id === $request->user()->id) {
            return redirect()->back()
                ->with('error', 'You cannot suspend your own account.');
        }

        // Cannot suspend the last super-admin.
        if ($user->hasRole('super-admin') && ($user->is_active ?? true)) {
            $activeSuperAdmins = User::role('super-admin')
                ->where(fn ($q) => $q->whereNull('is_active')->orWhere('is_active', true))
                ->count();
            if ($activeSuperAdmins <= 1) {
                return redirect()->back()
                    ->with('error', 'Cannot suspend the last active platform super-admin.');
            }
        }

        $user->update(['is_active' => ! ($user->is_active ?? true)]);
        $status = $user->is_active ? 'restored' : 'suspended';

        return redirect()->route('admin.users.index')
            ->with('success', "User \"{$user->name}\" has been {$status}.");
    }
}
