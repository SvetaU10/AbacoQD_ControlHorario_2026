<?php

namespace App\Http\Controllers;

use App\Models\Centro;
use App\Models\Festivo;
use App\Models\SolicitudVacaciones;
use App\Services\CatalogoMunicipios;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GestionController extends Controller
{
    public function index(Request $request, CatalogoMunicipios $catalogo): View
    {
        $mostrarTodasVacaciones = $request->boolean('todas_vacaciones');
        $solicitudesVacacionesPendientes = SolicitudVacaciones::query()
            ->where('estado', SolicitudVacaciones::PENDIENTE)
            ->count();
        $consultaSolicitudes = SolicitudVacaciones::query()->with('autor')
            ->where('estado', SolicitudVacaciones::PENDIENTE)->latest('id');
        $solicitudes = $mostrarTodasVacaciones
            ? $consultaSolicitudes->paginate(10, ['*'], 'pagina_vacaciones')->withQueryString()
            : $consultaSolicitudes->limit(3)->get();

        return view('gestion.index', [
            'solicitudesVacaciones' => $solicitudes,
            'solicitudesVacacionesPendientes' => $solicitudesVacacionesPendientes,
            'mostrarTodasVacaciones' => $mostrarTodasVacaciones,
            'hayMasSolicitudesVacaciones' => $mostrarTodasVacaciones || $solicitudesVacacionesPendientes > 3,
            'centros' => Centro::query()->orderBy('name')->get(),
            'estadoCatalogo' => $catalogo->estado(),
            'festivosGestion' => Festivo::query()->with('centro')->orderByDesc('holiday_date')->get(),
        ]);
    }
}
