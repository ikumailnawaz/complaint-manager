<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            if (!Auth::check() || (!Auth::user()->isSuperAdmin() && !Auth::user()->isAdmin())) {
                abort(403, 'User Management & Role Assignment is restricted to System Administrators.');
            }
            return $next($request);
        });
    }

    /**
     * Display a listing of all system users with role filters.
     */
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('city')) {
            $query->where('base_city', 'like', "%{$request->city}%");
        }

        if ($request->filled('search')) {
            $s = trim($request->search);
            $query->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%")
                  ->orWhere('phone_whatsapp', 'like', "%{$s}%")
                  ->orWhere('base_city', 'like', "%{$s}%")
                  ->orWhere('specialization', 'like', "%{$s}%");
            });
        }

        $users = $query->orderBy('name')->paginate(20)->withQueryString();

        $roleCounts = [
            'total'        => User::count(),
            'super_admin'  => User::where('role', 'super_admin')->count(),
            'admin'        => User::where('role', 'admin')->count(),
            'superior'     => User::where('role', 'superior')->count(),
            'office_staff' => User::whereIn('role', ['office_staff', 'staff'])->count(),
            'engineer'     => User::where('role', 'engineer')->count(),
        ];

        return view('users.index', compact('users', 'roleCounts'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create()
    {
        $availableRoles = $this->getAvailableRoles();
        return view('users.create', compact('availableRoles'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(Request $request)
    {
        $availableRoles = array_keys($this->getAvailableRoles());

        $validated = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|max:255|unique:users,email',
            'password'       => 'required|string|min:6|confirmed',
            'role'           => ['required', Rule::in($availableRoles)],
            'phone_whatsapp'   => 'nullable|string|max:50',
            'base_city'        => 'nullable|string|max:100',
            'home_coordinates' => 'nullable|string|max:100',
            'home_address'     => 'nullable|string|max:255',
            'specialization'   => 'nullable|string|max:150',
            'is_available'     => 'nullable|boolean',
        ]);

        // Hierarchy Protection: only super admin can create a super admin
        if ($validated['role'] === 'super_admin' && !Auth::user()->isSuperAdmin()) {
            abort(403, 'Only Super Administrators can create or assign the Super Admin role.');
        }

        $user = User::create([
            'name'             => $validated['name'],
            'email'            => strtolower(trim($validated['email'])),
            'password'         => Hash::make($validated['password']),
            'role'             => $validated['role'],
            'phone_whatsapp'   => $validated['phone_whatsapp'] ?? null,
            'base_city'        => $validated['base_city'] ?? null,
            'current_city'     => $validated['base_city'] ?? null,
            'home_coordinates' => $validated['home_coordinates'] ?? null,
            'home_address'     => $validated['home_address'] ?? null,
            'specialization'   => $validated['specialization'] ?? null,
            'is_available'     => $request->boolean('is_available', true),
            'is_on_leave'      => false,
        ]);

        return redirect()->route('users.index')
            ->with('success', "User '{$user->name}' created successfully with role '{$user->role}'.");
    }

    /**
     * Show the form for editing an existing user.
     */
    public function edit(User $user)
    {
        $availableRoles = $this->getAvailableRoles();
        return view('users.edit', compact('user', 'availableRoles'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(Request $request, User $user)
    {
        $availableRoles = array_keys($this->getAvailableRoles());

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'email'            => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password'         => 'nullable|string|min:6|confirmed',
            'role'             => ['required', Rule::in($availableRoles)],
            'phone_whatsapp'   => 'nullable|string|max:50',
            'base_city'        => 'nullable|string|max:100',
            'home_coordinates' => 'nullable|string|max:100',
            'home_address'     => 'nullable|string|max:255',
            'specialization'   => 'nullable|string|max:150',
            'is_available'     => 'nullable|boolean',
            'is_on_leave'      => 'nullable|boolean',
        ]);

        // Hierarchy Protection
        if ($validated['role'] === 'super_admin' && !Auth::user()->isSuperAdmin()) {
            abort(403, 'Only Super Administrators can assign the Super Admin role.');
        }

        // Prevent removing the last super admin
        if ($user->isSuperAdmin() && $validated['role'] !== 'super_admin') {
            $superAdminCount = User::where('role', 'super_admin')->count();
            if ($superAdminCount <= 1) {
                return back()->with('error', 'Cannot change the role of the only remaining Super Administrator.');
            }
        }

        $updateData = [
            'name'             => $validated['name'],
            'email'            => strtolower(trim($validated['email'])),
            'role'             => $validated['role'],
            'phone_whatsapp'   => $validated['phone_whatsapp'] ?? null,
            'base_city'        => $validated['base_city'] ?? null,
            'home_coordinates' => $validated['home_coordinates'] ?? null,
            'home_address'     => $validated['home_address'] ?? null,
            'specialization'   => $validated['specialization'] ?? null,
            'is_available'     => $request->boolean('is_available', true),
            'is_on_leave'      => $request->boolean('is_on_leave', false),
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        return redirect()->route('users.index')
            ->with('success', "User '{$user->name}' updated successfully.");
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(User $user)
    {
        if ($user->id === Auth::id()) {
            return back()->with('error', 'Security Policy: You cannot delete your own active account.');
        }

        if ($user->isSuperAdmin()) {
            $superAdminCount = User::where('role', 'super_admin')->count();
            if ($superAdminCount <= 1) {
                return back()->with('error', 'Cannot delete the only remaining Super Administrator.');
            }
        }

        $userName = $user->name;
        $user->delete();

        return redirect()->route('users.index')
            ->with('success', "User '{$userName}' deleted successfully.");
    }

    /**
     * Toggle availability status of a field engineer or staff member.
     */
    public function toggleStatus(Request $request, User $user)
    {
        $user->update([
            'is_available' => !$user->is_available,
        ]);

        $statusText = $user->is_available ? 'Active & Available' : 'Unavailable';
        return back()->with('success', "Status for {$user->name} updated to {$statusText}.");
    }

    /**
     * Get list of assignable roles based on current user privileges.
     */
    protected function getAvailableRoles(): array
    {
        $roles = [
            'admin'        => 'Operations Admin',
            'superior'     => 'Superior Manager',
            'office_staff' => 'Office Staff (Operations & Inventory)',
            'engineer'     => 'Field Service Engineer',
        ];

        if (Auth::user()->isSuperAdmin()) {
            $roles = ['super_admin' => 'Super Administrator'] + $roles;
        }

        return $roles;
    }
}
