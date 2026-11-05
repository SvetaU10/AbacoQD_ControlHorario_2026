<?php

namespace App\Console\Commands;

use App\Models\Centro;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

#[Signature('abaco:crear-administrador {email : Correo del administrador} {nombre : Nombre visible} {--rol=jefa : jefa o tecnico} {--centro= : Id de la sede; obligatorio si hay varias}')]
#[Description('Crea una cuenta administrativa con contraseña oculta elegida por el operador; no modifica cuentas existentes')]
class CrearAdministrador extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $datos = ['email' => strtolower(trim((string) $this->argument('email'))), 'name' => trim((string) $this->argument('nombre')), 'role' => $this->option('rol')];
        $validacion = Validator::make($datos, ['email' => ['required', 'email', 'max:255'], 'name' => ['required', 'string', 'max:255'], 'role' => ['required', 'in:jefa,tecnico']]);
        if ($validacion->fails()) {
            $this->error($validacion->errors()->first());

            return self::FAILURE;
        }
        if (User::query()->where('email', $datos['email'])->exists()) {
            $this->error('Ese correo ya tiene una cuenta. No se ha cambiado su contraseña ni su papel.');

            return self::FAILURE;
        }
        $centros = Centro::query()->when($this->option('centro'), fn ($consulta) => $consulta->whereKey($this->option('centro')))->get();
        if ($centros->count() !== 1) {
            $this->error('Debe existir una única sede o indicar su id mediante --centro. Ejecuta db:seed para el arranque de AbacoQD.');

            return self::FAILURE;
        }
        if (! $this->input->isInteractive()) {
            $this->error('La contraseña debe introducirse de forma interactiva y oculta. No se admiten claves en argumentos.');

            return self::FAILURE;
        }
        $clave = $this->secret('Contraseña (mínimo 12 caracteres)');
        $repetida = $this->secret('Repite la contraseña');
        if (! is_string($clave) || mb_strlen($clave) < 12 || $clave !== $repetida) {
            $this->error('La contraseña necesita al menos 12 caracteres y las dos entradas deben coincidir.');

            return self::FAILURE;
        }
        $centro = $centros->sole();
        DB::transaction(fn () => User::create($datos + [
            'password' => $clave, 'active' => true, 'centro_id' => $centro->id,
            'municipality' => $centro->municipality, 'starts_on' => now()->toDateString(),
        ]));
        $this->info('Cuenta administrativa creada. No se han añadido empleados, horarios ni fichajes de demostración.');

        return self::SUCCESS;
    }
}
