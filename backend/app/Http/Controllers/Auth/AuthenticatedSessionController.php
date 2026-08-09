<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\CafeUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AuthenticatedSessionController extends Controller
{
    public function create(): InertiaResponse
    {
        return Inertia::render('Auth/Login');
    }

    public function store(LoginRequest $request): JsonResponse|RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = Auth::user();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json([
                'message' => 'Authenticated successfully.',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'status' => $user->status,
                ],
            ]);
        }

        $cafeUser = CafeUser::where('user_id', $user->id)->with('cafe')->first();

        if ($cafeUser && $cafeUser->cafe) {
            return redirect()->route('tenant.dashboard', ['cafe_slug' => $cafeUser->cafe->slug])
                ->with('success', 'Logged in successfully.');
        }

        return redirect()->intended('/')
            ->with('success', 'Logged in successfully.');
    }

    public function destroy(Request $request): JsonResponse|RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => 'Logged out successfully.']);
        }

        return redirect('/login');
    }
}
