<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): Response
    {
        return response()
            ->view('auth.login')
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Escribe el correo.',
            'email.email' => 'El correo no es válido.',
            'password.required' => 'Escribe la contraseña.',
        ]);

        $persona = User::query()->where('email', $datos['email'])->first();

        if ($persona && ! $persona->active) {
            return back()->with('aviso', 'Tu acceso está cerrado. Tus registros se conservan.')->onlyInput('email');
        }

        if (! Auth::attempt(['email' => $datos['email'], 'password' => $datos['password']], false)) {
            return back()->with('aviso', 'Esos datos no coinciden.')->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->route('jornada');
    }

    public function pedirClave(): View
    {
        return view('auth.clave');
    }

    public function enviarClave(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Escribe el correo.',
            'email.email' => 'El correo no es válido.',
        ]);

        $persona = User::query()->where('email', $datos['email'])->first();
        if ($persona === null) {
            return back()->with('aviso', 'Ese correo no está registrado.')->onlyInput('email');
        }

        if (! $persona->active) {
            return back()->with('aviso', 'Tu acceso está cerrado. Tus registros se conservan.')->onlyInput('email');
        }

        $estado = Password::sendResetLink($datos);
        if ($estado === Password::ResetThrottled) {
            return back()->with('aviso', 'Espera un minuto y vuelve a pedirlo.')->onlyInput('email');
        }

        return back()->with('ok', 'Te hemos enviado un enlace. Mira el correo.');
    }

    public function editarClave(Request $request, string $token): View
    {
        return view('auth.clave-nueva', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function guardarClave(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ], [
            'email.required' => 'Escribe el correo.',
            'email.email' => 'El correo no es válido.',
            'password.required' => 'Escribe la contraseña nueva.',
            'password.min' => 'La contraseña necesita al menos 8 caracteres.',
            'password.confirmed' => 'Las dos contraseñas no coinciden.',
        ]);

        $persona = User::query()->where('email', $datos['email'])->first();
        if ($persona && ! $persona->active) {
            return back()->with('aviso', 'Tu acceso está cerrado. Tus registros se conservan.')->onlyInput('email');
        }

        $estado = Password::reset(
            $datos,
            function (User $user, string $password) use ($request): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
                Auth::login($user);
                $request->session()->regenerate();
            },
        );

        if ($estado === Password::PasswordReset) {
            return redirect()->route('jornada')->with('ok', 'Contraseña cambiada.');
        }

        return back()->with('aviso', 'Ese enlace ya no vale. Pide otro.')->onlyInput('email');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
