@extends('layouts.app', ['ancha' => true, 'titulo' => 'Calendario'])

@section('contenido')
    <div class="calendario-pagina">
        <div data-calendario>
            @include('calendario.contenido')
        </div>
        <form method="post" action="{{ route('calendario.vacaciones') }}" data-vacaciones-form hidden>
            @csrf
            <input type="hidden" name="desde" data-vacaciones-desde>
            <input type="hidden" name="hasta" data-vacaciones-hasta>
        </form>
        <dialog class="dialogo-hoy vacaciones-dialogo" data-vacaciones-dialogo aria-labelledby="titulo-vacaciones">
            <div data-vacaciones-cuerpo>
                @include('calendario.vacaciones-panel')
            </div>
        </dialog>
    </div>
@endsection
