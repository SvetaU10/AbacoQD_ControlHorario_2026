<?php

namespace Tests\Feature;

use App\Mail\RecuperarClave;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class RecuperarClaveTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_acceso_ofrece_recuperar_la_contraseña(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('No recuerdo la contraseña', false)
            ->assertSee(route('password.request'), false);
    }

    public function test_pedir_el_enlace_escribe_el_correo_si_el_acceso_existe(): void
    {
        Mail::fake();
        $ana = $this->persona();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $ana->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('ok', 'Te hemos enviado un enlace. Mira el correo.');

        Mail::assertSent(RecuperarClave::class, fn (RecuperarClave $correo) => $correo->hasTo($ana->email)
            && str_contains($correo->render(), 'Elegir una contraseña nueva'));
    }

    public function test_un_correo_desconocido_avisa_que_no_esta_registrado(): void
    {
        Mail::fake();

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'nadie@abaco.test'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('aviso', 'Ese correo no está registrado.')
            ->assertSessionHasInput('email', 'nadie@abaco.test');

        Mail::assertNothingSent();
    }

    public function test_un_acceso_cerrado_no_recibe_el_enlace(): void
    {
        Mail::fake();
        $ana = $this->persona();
        $ana->update(['active' => false]);

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $ana->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('aviso', 'Tu acceso está cerrado. Tus registros se conservan.');

        Mail::assertNothingSent();
    }

    public function test_el_enlace_cambia_la_contraseña_y_entra(): void
    {
        $ana = $this->persona();
        $token = Password::broker()->createToken($ana);

        $this->get(route('password.reset', ['token' => $token, 'email' => $ana->email]))
            ->assertOk()
            ->assertSee('Contraseña nueva', false);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $ana->email,
            'password' => 'NuevaClave1',
            'password_confirmation' => 'NuevaClave1',
        ])->assertRedirect(route('jornada'));

        $this->assertAuthenticatedAs($ana->fresh());
        $this->post(route('logout'));
        $this->post(route('login'), [
            'email' => $ana->email,
            'password' => 'NuevaClave1',
        ])->assertRedirect(route('jornada'));
    }

    public function test_un_enlace_caducado_no_cambia_la_contraseña(): void
    {
        $ana = $this->persona();

        $this->from(route('password.reset', ['token' => 'inventado', 'email' => $ana->email]))
            ->post(route('password.update'), [
                'token' => 'inventado',
                'email' => $ana->email,
                'password' => 'NuevaClave1',
                'password_confirmation' => 'NuevaClave1',
            ])
            ->assertRedirect(route('password.reset', ['token' => 'inventado', 'email' => $ana->email]))
            ->assertSessionHas('aviso', 'Ese enlace ya no vale. Pide otro.');
    }

    private function persona(): User
    {
        return User::create([
            'name' => 'Ana López',
            'email' => 'ana.lopez@abaco.test',
            'password' => 'Jornada2026',
            'role' => 'trabajadora',
            'active' => true,
            'starts_on' => '2026-01-07',
            'municipality' => 'Madrid',
        ]);
    }
}
