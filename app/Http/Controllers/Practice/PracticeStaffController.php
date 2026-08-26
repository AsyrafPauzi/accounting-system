<?php

namespace App\Http\Controllers\Practice;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFirmStaffRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PracticeStaffController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user && $user->isFirmUser(), 403);
        abort_unless($user->can('practice.staff.manage'), 403);

        $firm = $user->firm;
        abort_unless($firm, 403);

        $staff = User::query()
            ->where('firm_id', $firm->id)
            ->whereNotNull('firm_role')
            ->orderByRaw("CASE WHEN firm_role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'firm_role', 'email_verified_at', 'created_at'])
            ->map(fn (User $member) => [
                'id'                => $member->id,
                'name'              => $member->name,
                'email'             => $member->email,
                'firm_role'         => $member->firm_role,
                'email_verified_at' => $member->email_verified_at?->toIso8601String(),
                'is_self'           => $member->id === $user->id,
                'is_owner'          => $member->firm_role === 'owner',
            ]);

        $cap = $firm->staffSeatCap();

        return Inertia::render('Practice/Team', [
            'firm' => [
                'id'   => $firm->id,
                'name' => $firm->name,
            ],
            'staff' => $staff,
            'seatStatus' => [
                'total_seats' => $cap,
                'used'        => $firm->currentStaffCount(),
                'remaining'   => max(0, $cap - $firm->currentStaffCount()),
                'can_add'     => $firm->canAddStaff(),
                'plan_name'   => $firm->subscription?->plan?->name,
            ],
        ]);
    }

    public function store(StoreFirmStaffRequest $request): RedirectResponse
    {
        $auth = $request->user();
        $firm = $auth->firm;
        abort_unless($firm, 403);

        if (! $firm->canAddStaff()) {
            return back()->with('error', 'Staff seat limit reached on your Practice plan. Upgrade to invite more colleagues.');
        }

        $validated = $request->validated();
        $role = Role::where('name', 'firm-staff')->where('guard_name', 'web')->first();

        $member = User::create([
            'name'      => $validated['name'],
            'email'     => $validated['email'],
            'password'  => Hash::make($validated['password']),
            'firm_id'   => $firm->id,
            'firm_role' => 'staff',
            'role_id'   => $role?->id,
        ]);

        if ($role) {
            $member->assignRole('firm-staff');
        }

        try {
            Password::broker()->sendResetLink(['email' => $member->email]);
        } catch (\Throwable $e) {
            Log::warning('Firm staff invite: password reset email failed', [
                'firm_id' => $firm->id,
                'user_id' => $member->id,
                'err'     => $e->getMessage(),
            ]);
        }

        return redirect()->route('practice.team.index')->with('success', "{$member->name} has been added to your firm.");
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $auth = $request->user();
        abort_unless($auth && $auth->can('practice.staff.manage'), 403);

        $firm = $auth->firm;
        abort_unless($firm && $user->firm_id === $firm->id, 404);
        abort_if($user->firm_role === 'owner', 422, 'The firm owner cannot be removed.');
        abort_if($user->id === $auth->id, 422, 'You cannot remove your own account.');

        $name = $user->name;
        $user->delete();

        return redirect()->route('practice.team.index')->with('success', "{$name} has been removed from your firm.");
    }
}
