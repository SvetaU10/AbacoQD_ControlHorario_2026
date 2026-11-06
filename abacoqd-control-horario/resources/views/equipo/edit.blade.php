@extends('layouts.app', ['ancha' => true, 'titulo' => 'Editar'])

@section('contenido')
    <a class="volver" href="{{ route('equipo.index') }}">Volver al equipo</a>
    <h1>Editar a {{ $persona->name }}</h1>
    <p class="secundario">Corrige los datos del alta. El registro ya guardado se conserva.</p>

    <div class="alta-panel">
        <form id="editar-persona" method="post" action="{{ route('equipo.update', $persona) }}" class="formulario">
            @csrf
            @method('patch')
            <div class="alta-fila">
                <label for="name">Nombre
                    <input id="name" name="name" value="{{ old('name', $persona->name) }}" required maxlength="120">
                </label>
                <label for="email">Correo
                    <input id="email" name="email" type="email" value="{{ old('email', $persona->email) }}" required>
                </label>
                <label for="starts_on">Desde
                    <x-fecha id="starts_on" name="starts_on" :value="old('starts_on', $persona->starts_on?->toDateString())" required />
                </label>
                <label for="centro">Sede municipal
                    <select id="centro" name="centro" required>
                        @foreach ($sedes as $opcion)
                            <option value="{{ $opcion->name }}" @selected(old('centro', $persona->sede?->name) === $opcion->name)>{{ $opcion->etiqueta() }}</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div class="alta-fila">
                <label for="tipo">Tipo de jornada
                    <select id="tipo" name="tipo" required>
                        @foreach ($tipos as $opcion)
                            <option value="{{ $opcion->value }}" @selected(old('tipo', $tipo?->value) === $opcion->value)>{{ $opcion->etiqueta() }}</option>
                        @endforeach
                    </select>
                </label>
                <label for="duracion">Tiempo en la empresa
                    <select id="duracion" name="duracion" required onchange="document.getElementById('ultimo-dia').hidden = this.value !== 'hasta_el_dia'">
                        @foreach ($duraciones as $opcion)
                            <option value="{{ $opcion->value }}" @selected(old('duracion', $duracion->value) === $opcion->value)>{{ $opcion->etiqueta() }}</option>
                        @endforeach
                    </select>
                </label>
                <div id="ultimo-dia" @if (old('duracion', $duracion->value) !== 'hasta_el_dia') hidden @endif>
                    <label for="ends_on">Último día
                        <x-fecha id="ends_on" name="ends_on" :value="old('ends_on', $persona->ends_on?->toDateString())" :min="old('starts_on', $persona->starts_on?->toDateString())" />
                    </label>
                </div>
            </div>
        </form>
        <div class="alta-cierre">
            @if ($persona->active && auth()->id() !== $persona->id)
                <p class="secundario">Se cierra el acceso y se paran los avisos. El registro se conserva.</p>
            @elseif (! $persona->active)
                <p class="secundario">Acceso cerrado. Día de la baja: {{ $persona->baja_on?->format('d/m/Y') ?? 'sin indicar' }}. El contrato y el registro se conservan.</p>
            @endif
            <button class="boton boton-principal" type="submit" form="editar-persona">Guardar cambios</button>
            @if ($persona->active && auth()->id() !== $persona->id)
                <form method="post" action="{{ route('equipo.baja', $persona) }}">
                    @csrf
                    <button class="boton boton-baja" type="submit">Dar de baja</button>
                </form>
            @endif
        </div>
    </div>
@endsection
