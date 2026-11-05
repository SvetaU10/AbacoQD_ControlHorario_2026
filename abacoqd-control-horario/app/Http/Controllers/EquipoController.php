<?php

namespace App\Http\Controllers;

use App\DuracionEstancia;
use App\Exceptions\ReglaJornada;
use App\Models\Ausencia;
use App\Models\Centro;
use App\Models\Consulta;
use App\Models\Horario;
use App\Models\User;
use App\Services\AsignacionesCentro;
use App\Services\CalendarioMunicipal;
use App\Services\CatalogoMunicipios;
use App\Services\JornadaService;
use App\Services\NagerFestivos;
use App\Services\VigenciasCalendario;
use App\Support\RejillaCalendario;
use App\TipoJornada;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class EquipoController extends Controller
{
    public function __construct(
        private JornadaService $jornada,
        private NagerFestivos $festivos,
    ) {}

    public function index(Request $request): View
    {
        $mostrarTodas = $request->boolean('todas_personas');
        $personasQuery = User::query()
            ->with(['horarios', 'sede'])
            ->orderBy('name');

        return view('equipo.index', [
            'personas' => (clone $personasQuery)
                ->when(! $mostrarTodas, fn ($query) => $query->limit(8))
                ->get(),
            'mostrarTodas' => $mostrarTodas,
            'totalPersonas' => (clone $personasQuery)->count(),
            'esJefa' => true,
        ]);
    }

    public function crearCentro(Request $request, CatalogoMunicipios $catalogo): RedirectResponse
    {
        $datos = $this->datosCentro($request, $catalogo);

        Centro::create($datos);

        return back()->with('ok', 'Sede guardada. Ya puedes asignarla a una persona.');
    }

    public function actualizarCentro(Request $request, Centro $centro, CalendarioMunicipal $calendario, CatalogoMunicipios $catalogo, VigenciasCalendario $vigencias): RedirectResponse
    {
        $datos = $this->datosCentro($request, $catalogo, $centro);

        $cambiaTerritorio = mb_strtolower(trim($centro->municipality)) !== mb_strtolower(trim($datos['municipality']))
            || mb_strtolower(trim($centro->community)) !== mb_strtolower(trim($datos['community']))
            || (array_key_exists('municipio_ine', $datos) && $centro->municipio_ine !== $datos['municipio_ine']);

        DB::transaction(function () use ($centro, $datos, $calendario, $request, $cambiaTerritorio, $vigencias): void {
            if ($cambiaTerritorio) {
                $vigencias->conservarManuales($centro);
            }
            $centro->update($datos);
            $centro->users()->update(['municipality' => $datos['municipality']]);
            if ($cambiaTerritorio) {
                $calendario->invalidarCentro($centro, $request->user());
            }
        });

        return back()->with('ok', $cambiaTerritorio
            ? 'Sede actualizada. Carga de nuevo los calendarios del municipio para confirmar sus festivos.'
            : 'Sede actualizada. Las personas vinculadas usarán el nuevo municipio.');
    }

    /** @return array<string,mixed> */
    private function datosCentro(Request $request, CatalogoMunicipios $catalogo, ?Centro $centro = null): array
    {
        $datos = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('centros', 'name')->ignore($centro)],
            'municipio_ine' => ['nullable', 'string', 'regex:/^\d{5}$/D', 'required_without:municipality'],
            'municipality' => ['nullable', 'required_without:municipio_ine', 'string', 'max:160'],
            'community' => ['nullable', 'required_without:municipio_ine', 'string', 'max:120'],
        ], [
            'name.required' => 'Escribe el nombre de la sede.',
            'name.unique' => 'Ese nombre de sede ya existe.',
            'municipio_ine.required_without' => 'Selecciona un municipio del catálogo.',
            'municipio_ine.regex' => 'Selecciona un municipio válido del catálogo.',
            'municipality.required_without' => 'Escribe el municipio.',
            'community.required_without' => 'Escribe la autonomía.',
        ]);
        if (! empty($datos['municipio_ine'])) {
            try {
                $municipio = $catalogo->resolver($datos['municipio_ine']);
            } catch (InvalidArgumentException $error) {
                throw ValidationException::withMessages(['municipio_ine' => $error->getMessage()]);
            }

            return [
                'name' => $datos['name'], 'municipio_ine' => $municipio['ine'],
                'municipality' => $municipio['nombre'], 'province' => $municipio['provincia'],
                'province_code' => $municipio['provincia_codigo'], 'community' => $municipio['comunidad'],
                'comunidad_codigo' => $municipio['comunidad_codigo'],
            ];
        }
        unset($datos['municipio_ine']);
        if ($centro === null || $centro->municipality !== $datos['municipality'] || $centro->community !== $datos['community']) {
            $datos += ['municipio_ine' => null, 'province' => null, 'province_code' => null, 'comunidad_codigo' => null];
        }

        return $datos;
    }

    public function create(Request $request): View
    {
        $tipo = TipoJornada::tryFrom((string) $request->old('tipo', $request->query('tipo', '')));
        $startsOn = $this->fechaConsulta($request, 'starts_on') ?? now()->toDateString();
        $duracion = DuracionEstancia::tryFrom((string) $request->old('duracion', $request->query('duracion', '')));
        $endsOnInput = $this->fechaConsulta($request, 'ends_on');
        $endsOn = $duracion?->fechaFin(Carbon::parse($startsOn), $endsOnInput);
        $periodoListo = $duracion !== null
            && ($duracion !== DuracionEstancia::HastaElDia || $endsOn !== null);

        $anioInicio = Carbon::parse($startsOn)->year;
        $anioFin = $endsOn ? Carbon::parse($endsOn)->year : max(now()->year + 1, $anioInicio);
        $navegaAnios = ! $periodoListo || $duracion === DuracionEstancia::Indefinido;
        $anio = (int) $request->query('anio', $periodoListo ? $anioInicio : now()->year);
        if ($periodoListo && $navegaAnios) {
            $anio = max($anioInicio, min($anio, $anioFin));
        } elseif ($periodoListo) {
            $anio = $anioInicio;
        } elseif ($anio < 2020 || $anio > now()->year + 1) {
            $anio = now()->year;
        }

        $nombreSede = $request->old('centro', $request->query('centro'));
        $sede = Centro::query()
            ->when($nombreSede, fn ($query) => $query->where('name', $nombreSede))
            ->first() ?? Centro::query()->orderBy('name')->firstOrFail();
        $aniosFestivos = $periodoListo && $endsOn
            ? range($anioInicio, $anioFin)
            : [$anio];
        $festivos = collect();
        foreach (array_unique($aniosFestivos) as $anioFestivo) {
            $festivos = $festivos->concat($this->festivos->delAnio($sede, $anioFestivo));
        }
        $consulta = array_filter([
            'tipo' => $tipo?->value,
            'starts_on' => $startsOn,
            'duracion' => $duracion?->value,
            'ends_on' => $duracion === DuracionEstancia::HastaElDia ? $endsOnInput : null,
            'centro' => $sede->name,
        ]);
        $meses = RejillaCalendario::meses($anio, $festivos, $periodoListo ? $startsOn : null, $periodoListo ? $endsOn : null, $periodoListo);
        $aniosMeses = collect($meses)->pluck('anio')->unique();

        return view('equipo.create', [
            'esJefa' => true,
            'anio' => $anio,
            'anioSiguiente' => $anio + 1,
            'puedeAvanzar' => $navegaAnios && $anio < ($periodoListo ? $anioFin : now()->year + 1),
            'puedeRetroceder' => $navegaAnios && $anio > ($periodoListo ? $anioInicio : 2020),
            'navegaAnios' => $navegaAnios,
            'sede' => $sede,
            'sedes' => Centro::query()->orderBy('name')->get(),
            'centro' => $sede->municipio(),
            'festivos' => $festivos,
            'sinCalendario' => $festivos->isEmpty(),
            'meses' => $meses,
            'muestraAnioMes' => $aniosMeses->count() > 1,
            'tipo' => $tipo,
            'tipos' => TipoJornada::cases(),
            'duracion' => $duracion,
            'duraciones' => DuracionEstancia::cases(),
            'startsOn' => $startsOn,
            'endsOn' => $endsOnInput,
            'finPeriodo' => $endsOn,
            'periodoListo' => $periodoListo,
            'consulta' => $consulta,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validarPersona($request, true);
        $tipo = TipoJornada::from($datos['tipo']);
        $sede = Centro::query()->where('name', $datos['centro'])->firstOrFail();
        $duracion = DuracionEstancia::from($datos['duracion']);
        $endsOn = $duracion->fechaFin(Carbon::parse($datos['starts_on']), $datos['ends_on'] ?? null);
        $datos = array_merge($datos, $tipo->horas());

        try {
            $persona = DB::transaction(function () use ($datos, $tipo, $sede, $endsOn) {
                $persona = User::create([
                    'name' => $datos['name'],
                    'email' => $datos['email'],
                    'password' => $datos['password'],
                    'role' => 'trabajadora',
                    'active' => true,
                    'starts_on' => $datos['starts_on'],
                    'ends_on' => $endsOn,
                    'municipality' => $sede->municipio(),
                    'centro_id' => $sede->id,
                ]);
                $this->guardarHorario($persona, $datos, $datos['starts_on'], $tipo);

                return $persona;
            });
        } catch (ReglaJornada $e) {
            return back()->with('aviso', $e->getMessage())->withInput();
        }

        return redirect()->route('equipo.index')->with('ok', 'Guardado. '.$datos['name'].' ya puede entrar.');
    }

    public function show(User $user): View
    {
        return view('equipo.show', [
            'persona' => $user,
            'horarios' => $user->horarios()->orderByDesc('effective_from')->get(),
            'ausencias' => $user->ausencias()->orderByDesc('absence_date')->get(),
            'consultas' => Consulta::query()->with('viewer')->where('subject_id', $user->id)->latest()->limit(8)->get(),
            'centros' => Centro::query()->orderBy('name')->get(),
            'esJefa' => true,
        ]);
    }

    public function horario(Request $request, User $user): RedirectResponse
    {
        $datos = $this->validarHorario($request);
        $datos['effective_from'] = $request->validate([
            'effective_from' => ['required', 'date', 'after_or_equal:today'],
        ], [
            'effective_from.required' => 'Elige desde qué día vale el horario.',
            'effective_from.after_or_equal' => 'El horario nuevo vale desde hoy o un día posterior. Los días anteriores se conservan.',
        ])['effective_from'];

        try {
            $this->guardarHorario($user, $datos, $datos['effective_from'], null);
            $this->jornada->excluirAvisosDeHorario($user, $datos['effective_from']);
        } catch (ReglaJornada $e) {
            return back()->with('aviso', $e->getMessage())->withInput();
        }

        return back()->with('ok', 'Guardado. El horario vale desde el '.$datos['effective_from'].'. Los avisos desde ese día no cuentan. Los anteriores se conservan.');
    }

    public function centro(Request $request, User $user, AsignacionesCentro $asignaciones): RedirectResponse
    {
        $datos = $request->validate([
            'centro' => ['required', 'string', 'exists:centros,name'],
            'effective_from' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:today'],
        ], [
            'centro.required' => 'Elige una sede.',
            'centro.exists' => 'Elige una sede municipal válida.',
            'effective_from.date_format' => 'Elige una fecha válida.',
            'effective_from.after_or_equal' => 'El cambio de sede se aplica desde hoy. Los días anteriores se conservan.',
            'effective_from.before_or_equal' => 'El cambio de sede se aplica desde hoy. Todavía no se puede programar para una fecha futura.',
        ]);
        $centro = Centro::query()->where('name', $datos['centro'])->firstOrFail();
        $asignaciones->cambiar($user, $centro, $request->user(), Carbon::parse($datos['effective_from'] ?? now()->toDateString()));

        return back()->with('ok', 'Guardado. La sede de '.$user->name.' es '.$centro->municipality.' desde hoy. Los días anteriores conservan su sede.');
    }

    public function ausencia(Request $request, User $user): RedirectResponse
    {
        $datos = $request->validate([
            'absence_date' => ['required', 'date'],
            'tipo' => ['required', 'in:vacaciones,permiso'],
        ], [
            'absence_date.required' => 'Elige el día.',
            'tipo.required' => 'Elige vacaciones o permiso.',
        ]);

        try {
            Ausencia::create([
                'user_id' => $user->id,
                'absence_date' => $datos['absence_date'],
                'tipo' => $datos['tipo'],
            ]);
        } catch (UniqueConstraintViolationException) {
            return back()->with('aviso', 'Ese día ya tiene una ausencia.');
        }

        return back()->with('ok', 'Guardado. Ese día no se espera fichaje.');
    }

    public function baja(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return back()->with('aviso', 'No puedes cerrar tu propio acceso.');
        }

        $user->update(['active' => false]);

        return back()->with('ok', 'Acceso cerrado. Los registros de '.$user->name.' se conservan.');
    }

    private function validarPersona(Request $request, bool $conClave): array
    {
        $reglas = [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'starts_on' => ['required', 'date'],
            'duracion' => ['required', Rule::enum(DuracionEstancia::class)],
            'ends_on' => ['nullable', 'required_if:duracion,'.DuracionEstancia::HastaElDia->value, 'date', 'after_or_equal:starts_on'],
            'tipo' => ['required', Rule::enum(TipoJornada::class)],
            'centro' => ['required', 'string', 'exists:centros,name'],
        ];
        if ($conClave) {
            $reglas['password'] = ['required', 'string', 'min:8'];
        }

        return $request->validate($reglas, [
            'name.required' => 'Escribe el nombre.',
            'email.required' => 'Escribe el correo.',
            'email.unique' => 'Ese correo ya está en uso.',
            'password.min' => 'La contraseña necesita al menos 8 caracteres.',
            'starts_on.required' => 'Elige desde qué día cuenta el registro.',
            'duracion.required' => 'Elige cuánto tiempo estará en la empresa.',
            'duracion.enum' => 'Elige cuánto tiempo estará en la empresa.',
            'ends_on.required_if' => 'Elige el último día en la empresa.',
            'ends_on.after_or_equal' => 'El último día no puede ser anterior al inicio.',
            'tipo.required' => 'Elige el tipo de jornada.',
            'tipo.enum' => 'Elige un tipo de jornada válido.',
            'centro.required' => 'Elige la sede municipal.',
            'centro.exists' => 'Elige una sede municipal válida.',
        ]);
    }

    private function validarHorario(Request $request): array
    {
        foreach (['morning_start', 'morning_end', 'afternoon_start', 'afternoon_end'] as $campo) {
            $valor = $request->input($campo);
            if (is_string($valor) && strlen($valor) >= 5) {
                $request->merge([$campo => substr($valor, 0, 5)]);
            }
        }

        return $request->validate([
            'morning_start' => ['required', 'date_format:H:i'],
            'morning_end' => ['required', 'date_format:H:i'],
            'afternoon_start' => ['required', 'date_format:H:i'],
            'afternoon_end' => ['required', 'date_format:H:i'],
        ], [
            'morning_start.required' => 'Elige la hora de inicio.',
            'morning_end.required' => 'Elige el fin de la mañana.',
            'afternoon_start.required' => 'Elige el inicio de la tarde.',
            'afternoon_end.required' => 'Elige el fin de la jornada.',
            '*.date_format' => 'Elige una hora.',
        ]);
    }

    private function guardarHorario(User $persona, array $datos, string $desde, ?TipoJornada $tipo): void
    {
        $tardeIgual = $datos['afternoon_start'] === $datos['afternoon_end'];
        $tardeValida = $tardeIgual
            ? $datos['morning_end'] === $datos['afternoon_start']
            : $datos['afternoon_start'] < $datos['afternoon_end'];

        if (! ($datos['morning_start'] < $datos['morning_end']
            && $datos['morning_end'] <= $datos['afternoon_start']
            && $tardeValida)) {
            throw new ReglaJornada('Revisa el horario: la mañana va antes que la tarde, y cada tramo termina después de empezar.');
        }

        Horario::create([
            'user_id' => $persona->id,
            'tipo' => $tipo?->value,
            'morning_start' => $datos['morning_start'],
            'morning_end' => $datos['morning_end'],
            'afternoon_start' => $datos['afternoon_start'],
            'afternoon_end' => $datos['afternoon_end'],
            'effective_from' => $desde,
        ]);
    }

    private function fechaConsulta(Request $request, string $campo): ?string
    {
        $valor = $request->old($campo, $request->query($campo));
        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1
            || ! checkdate((int) substr($valor, 5, 2), (int) substr($valor, 8, 2), (int) substr($valor, 0, 4))) {
            return null;
        }

        return $valor;
    }
}
