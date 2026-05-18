<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\UserSetupService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * @bodyParam name string required User name. Example: Fouad Saoudi
     * @bodyParam email string required User email. Example: fouad.saoudi94@gmail.com
     * @bodyParam password string required Account password. Example: Google_2
     * @bodyParam password_confirmation string required Password confirmation. Example: Google_2
     * @bodyParam device_name string Device name. Example: mobile-app
     */
    public function register(Request $request, UserSetupService $userSetupService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
        ]);

        $userSetupService->initialize($user);
        $user->load('reportingCurrency');

        $token = $user->createToken($validated['device_name'] ?? 'mobile-app')->plainTextToken;

        return response()->json([
            'message' => 'Registered successfully.',
            'token_type' => 'Bearer',
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    /**
     * @bodyParam email string required User email. Example: fouad.saoudi94@gmail.com
     * @bodyParam password string required Account password. Example: Google_2
     * @bodyParam device_name string Device name. Example: mobile-app
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken($validated['device_name'] ?? 'mobile-app')->plainTextToken;
        $user->load('reportingCurrency');

        return response()->json([
            'message' => 'Logged in successfully.',
            'token_type' => 'Bearer',
            'token' => $token,
            'user' => $user,
        ]);
    }

    /**
     * @bodyParam email string required User email. Example: fouad.saoudi94@gmail.com
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink([
            'email' => $validated['email'],
        ]);

        if ($status === Password::RESET_THROTTLED) {
            return response()->json([
                'message' => __($status),
            ], 429);
        }

        return response()->json([
            'message' => __('If an account with that email exists, we have emailed a password reset link.'),
        ]);
    }

    /**
     * @bodyParam token string required Password reset token from the email link. Example: 4c0f6deecf4f2474f0f66db0a9f6a2924d2e5d11
     * @bodyParam email string required User email. Example: fouad.saoudi94@gmail.com
     * @bodyParam password string required New account password. Example: Google_2New
     * @bodyParam password_confirmation string required New password confirmation. Example: Google_2New
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'password_confirmation' => ['required', 'string'],
        ]);

        $status = $this->resetUserPassword($validated);

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => [__($status)],
            ]);
        }

        return response()->json([
            'message' => __($status),
        ]);
    }

    public function showResetPasswordForm(Request $request): View
    {
        return view('auth.reset-password', [
            'email' => (string) $request->query('email', ''),
            'token' => (string) $request->query('token', ''),
        ]);
    }

    public function resetPasswordFromWeb(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
            'password_confirmation' => ['required', 'string'],
        ]);

        $status = $this->resetUserPassword($validated);

        if ($status !== Password::PASSWORD_RESET) {
            return back()
                ->withInput($request->only('email', 'token'))
                ->withErrors([
                    'email' => __($status),
                ]);
        }

        return redirect()
            ->route('password.reset', [
                'email' => $validated['email'],
                'token' => $validated['token'],
            ])
            ->with('status', __($status));
    }

    /**
     * @param array{token:string,email:string,password:string,password_confirmation:string} $validated
     */
    private function resetUserPassword(array $validated): string
    {
        return Password::reset(
            [
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password_confirmation'],
                'token' => $validated['token'],
            ],
            function (User $user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                $user->tokens()->delete();

                event(new PasswordReset($user));
            }
        );
    }
}
