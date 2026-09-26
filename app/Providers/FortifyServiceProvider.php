<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'loginThrottle' => $request->session()->get('login_throttle'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $username = (string) $request->string(Fortify::username());

            $throttleKey = Str::transliterate(
                Str::lower($username).'|'.$request->ip()
            );

            return Limit::perMinute(5)
                ->by($throttleKey)
                ->response($this->throttledLoginResponse(...));
        });
    }

    /**
     * Presenta el bloqueo sin reemplazar el formulario por una página de error.
     *
     * @param  array<string, int|string>  $headers
     */
    private function throttledLoginResponse(
        Request $request,
        array $headers,
    ): Response {
        $seconds = max(1, (int) ($headers['Retry-After'] ?? 60));

        $message = (string) __('auth.throttle', [
            'seconds' => $seconds,
            'minutes' => (int) ceil($seconds / 60),
        ]);

        if ($request->expectsJson() && ! $request->hasHeader('X-Inertia')) {
            return response()->json([
                'message' => $message,
                'errors' => [
                    Fortify::username() => [$message],
                ],
            ], 429, $headers);
        }

        return redirect()
            ->route('login', [], 303)
            ->with('login_throttle', [
                'retryAt' => (now()->getTimestamp() + $seconds) * 1000,
                'retryAfter' => $seconds,
            ])
            ->withErrors([Fortify::username() => $message])
            ->withInput($request->only(Fortify::username(), 'remember'));
    }
}
