@extends('layouts.app', ['ancha' => true, 'titulo' => 'Calendario'])

@section('contenido')
    <div class="calendario-pagina">
        <div data-calendario>
            @include('calendario.contenido')
        </div>

        <div class="vacaciones-pedir">
            <div>
                <strong>Vacaciones disponibles</strong>
                <span class="secundario"><span class="vacaciones-total">{{ $diasVacaciones }}</span> días laborables</span>
            </div>
            <button class="vacaciones-abre" type="button" data-vacaciones-pedir aria-label="Solicitar vacaciones" title="Solicitar vacaciones">
                <x-icono nombre="plus" />
            </button>
            <p class="vacaciones-aviso" data-vacaciones-texto hidden>Elige un rango de fechas</p>
            <div class="vacaciones-acciones" data-vacaciones-acciones hidden>
                <button class="vacaciones-tick" type="button" data-vacaciones-aceptar aria-label="Aceptar">✓</button>
                <button class="vacaciones-equis" type="button" data-vacaciones-cancelar aria-label="Cancelar">✕</button>
            </div>
            <form method="post" action="{{ route('calendario.vacaciones') }}" data-vacaciones-form hidden>
                @csrf
                <input type="hidden" name="desde" data-vacaciones-desde>
                <input type="hidden" name="hasta" data-vacaciones-hasta>
            </form>
        </div>
    </div>
@endsection
