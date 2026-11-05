<?php

namespace App\Services;

use App\Models\Ausencia;
use App\Models\SolicitudVacaciones;
use App\Models\User;
use App\Support\DiaFestivo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Cada año son 22 días. Solo cuentan los laborables que no son festivo.
 * Se pueden coger dentro de los dos meses siguientes; lo no aprobado no pasa de año.
 */
class CupoVacaciones
{
    public const DIAS = 22;

    public function __construct(private NagerFestivos $festivos) {}

    public function restantes(User $user, int $anio): int
    {
        return max(0, self::DIAS - $this->aprobados($user, $anio));
    }

    /**
     * @return Collection<int, string>
     */
    public function queCuentan(User $user, Carbon $desde, Carbon $hasta): Collection
    {
        $festivos = $this->festivosEntre($user, $desde, $hasta);
        $dias = collect();
        $cursor = $desde->copy()->startOfDay();
        $fin = $hasta->copy()->startOfDay();

        while ($cursor->lte($fin)) {
            $clave = $cursor->toDateString();
            if (! $cursor->isWeekend() && ! $festivos->has($clave)) {
                $dias->push($clave);
            }
            $cursor->addDay();
        }

        return $dias;
    }

    public function dentroDelPlazo(Carbon $hasta): bool
    {
        return $hasta->copy()->startOfDay()->lte(now()->startOfDay()->addMonths(2));
    }

    public function cabe(User $user, Carbon $desde, Carbon $hasta, ?int $excepto = null): bool
    {
        $porAnio = $this->queCuentan($user, $desde, $hasta)
            ->groupBy(fn (string $fecha) => substr($fecha, 0, 4));

        foreach ($porAnio as $anio => $dias) {
            $ocupados = $this->aprobados($user, (int) $anio) + $this->pendientes($user, (int) $anio, $excepto);
            if ($ocupados + $dias->count() > self::DIAS) {
                return false;
            }
        }

        return true;
    }

    private function aprobados(User $user, int $anio): int
    {
        $festivos = $this->festivos->dePersona($user, $anio)
            ->map(fn (DiaFestivo $dia) => $dia->holiday_date->toDateString())
            ->flip();

        return $user->ausencias()
            ->where('tipo', 'vacaciones')
            ->whereYear('absence_date', $anio)
            ->get()
            ->filter(function (Ausencia $ausencia) use ($festivos) {
                $fecha = $ausencia->absence_date->copy()->startOfDay();

                return ! $fecha->isWeekend() && ! $festivos->has($fecha->toDateString());
            })
            ->count();
    }

    private function pendientes(User $user, int $anio, ?int $excepto = null): int
    {
        return $user->solicitudesVacaciones()
            ->where('estado', SolicitudVacaciones::PENDIENTE)
            ->when($excepto, fn ($consulta) => $consulta->whereKeyNot($excepto))
            ->get()
            ->sum(function (SolicitudVacaciones $solicitud) use ($user, $anio) {
                $desde = $solicitud->starts_on->copy()->startOfDay();
                $hasta = $solicitud->ends_on->copy()->startOfDay();
                $inicio = Carbon::create($anio, 1, 1)->startOfDay();
                $fin = Carbon::create($anio, 12, 31)->startOfDay();
                if ($hasta->lt($inicio) || $desde->gt($fin)) {
                    return 0;
                }

                return $this->queCuentan(
                    $user,
                    $desde->lt($inicio) ? $inicio : $desde,
                    $hasta->gt($fin) ? $fin : $hasta,
                )->count();
            });
    }

    /**
     * @return Collection<string, true>
     */
    private function festivosEntre(User $user, Carbon $desde, Carbon $hasta): Collection
    {
        $claves = collect();
        for ($anio = $desde->year; $anio <= $hasta->year; $anio++) {
            foreach ($this->festivos->dePersona($user, $anio) as $dia) {
                $claves->put($dia->holiday_date->toDateString(), true);
            }
        }

        return $claves;
    }
}
