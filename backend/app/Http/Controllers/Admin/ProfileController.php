<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ProfileController extends Controller
{
    public function __construct(
        protected AuditLogger $auditLogger
    ) {}

    public function show(Request $request): JsonResponse|InertiaResponse
    {
        $user = $request->user();

        $profileData = [
            'id'             => $user->id,
            'name'           => $user->name,
            'email'          => $user->email,
            'phone'          => $user->phone,
            'status'         => $user->status,
            'is_super_admin' => $user->isSuperAdmin(),
            'created_at'     => $user->created_at?->toIso8601String(),
        ];

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['user' => $profileData]);
        }

        return Inertia::render('Admin/Profile', [
            'user' => $profileData,
        ]);
    }

    public function updatePassword(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($request->input('current_password'), $user->password)) {
            if ($request->wantsJson() && ! $request->header('X-Inertia')) {
                return response()->json(['message' => 'The current password is incorrect.'], 422);
            }
            return redirect()->back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $user->update([
            'password' => Hash::make($request->input('new_password')),
        ]);

        $this->auditLogger->log(
            action: 'admin.password_updated',
            entityType: 'user',
            entityId: $user->id,
            cafeId: null,
            oldValues: null,
            newValues: ['user_id' => $user->id]
        );

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Password updated successfully.']);
        }

        return redirect()->back()->with('success', 'Password updated successfully.');
    }
}
