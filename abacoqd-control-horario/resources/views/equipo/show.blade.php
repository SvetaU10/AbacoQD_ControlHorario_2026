@extends('layouts.app', ['ancha' => true, 'titulo' => $persona->name])

@section('contenido')
    <a class="volver" href="{{ route('equipo.index') }}">Volver al equipo</a>
    <h1>{{ $persona->name }}</h1>
    <p>{{ $persona->email }}</p>
    <p class="secundario">Sede {{ $persona->sede?->name ?? 'sin asignar' }} en {{ $persona->sede?->municipality ?? $persona->municipality }}. El registro cuenta desde {{ $persona->starts_on ? \App\Support\Tiempo::fecha($persona->starts_on) : 'sin fecha' }}{{ $persona->ends_on ? ', hasta '.\App\Support\Tiempo::fecha($persona->ends_on) : ', sin fecha de fin' }}.</p>
    <p>
        <a class="boton boton-secundario" href="{{ route('equipo.registro', $persona) }}">Ver registro</a>
    </p>

    <h2>Sede</h2>
    <p class="secundario">El cambio se aplica desde hoy. Los días anteriores conservan la sede y el calendario que tenían.</p>
    <form method="post" action="{{ route('equipo.centro', $persona) }}" class="formulario">
        @csrf
        <label for="centro">Centro de trabajo</label>
        <select id="centro" name="centro" required>
            @foreach ($centros as $centro)
                <option value="{{ $centro->name }}" @selected($persona->sede?->id === $centro->id)>{{ $centro->etiqueta() }}</option>
            @endforeach
        </select>
        <button class="boton boton-secundario" type="submit">Guardar sede</button>
    </form>

    <h2>Horario</h2>
    <ul class="lista">
        @forelse ($horarios as $horario)
            <li>
                <span>Desde {{ \App\Support\Tiempo::fecha($horario->effective_from) }}@if ($horario->tipoJornada()) · {{ $horario->tipoJornada()->etiqueta() }}@endif</span>
                <strong>{{ $horario->texto() }}</strong>
            </li>
        @empty
            <li>Sin horario.</li>
        @endforelse
    </ul>

    <form method="post" action="{{ route('equipo.horario', $persona) }}" class="formulario">
        @csrf
        <label for="effective_from">El horario nuevo vale desde</label>
        <x-fecha id="effective_from" name="effective_from" :min="now()->toDateString()" required />
        <p class="secundario">Los días anteriores conservan su horario.</p>
        <label for="morning_start">Empieza</label>
        <x-hora id="morning_start" name="morning_start" valor="09:00" />
        <label for="morning_end">Fin de la mañana</label>
        <x-hora id="morning_end" name="morning_end" valor="14:00" />
        <label for="afternoon_start">Vuelta</label>
        <x-hora id="afternoon_start" name="afternoon_start" valor="15:00" />
        <label for="afternoon_end">Fin de la jornada</label>
        <x-hora id="afternoon_end" name="afternoon_end" valor="18:00" />
        <button class="boton boton-secundario" type="submit">Guardar horario</button>
    </form>

    <h2>Ausencias</h2>
    <ul class="lista">
        @forelse ($ausencias as $ausencia)
            <li>
                <span>{{ \App\Support\Tiempo::fecha($ausencia->absence_date) }}</span>
                <strong>{{ $ausencia->tipoTexto() }}</strong>
            </li>
        @empty
            <li>Sin ausencias previstas.</li>
        @endforelse
    </ul>
    <form method="post" action="{{ route('equipo.ausencia', $persona) }}" class="formulario">
        @csrf
        <label for="absence_date">Día</label>
        <x-fecha id="absence_date" name="absence_date" required />
        <label for="tipo">Tipo</label>
        <select id="tipo" name="tipo" required>
            <option value="vacaciones">Vacaciones</option>
            <option value="permiso">Permiso</option>
        </select>
        <button class="boton boton-secundario" type="submit">Guardar ausencia</button>
    </form>

    @if ($persona->active && auth()->id() !== $persona->id)
        <form method="post" action="{{ route('equipo.baja', $persona) }}">
            @csrf
            <button class="boton boton-secundario" type="submit">Cerrar acceso</button>
        </form>
        <p class="secundario">Se cierra el acceso y se paran los avisos. El registro se conserva.</p>
    @elseif (! $persona->active)
        <p>Acceso cerrado. El registro se conserva.</p>
    @endif

    @if ($consultas->isNotEmpty())
        <h2>Consultas</h2>
        <ul class="lista">
            @foreach ($consultas as $visita)
                <li>
                    <span>{{ $visita->viewer->name }}</span>
                    <span class="secundario">{{ \App\Support\Tiempo::fecha($visita->created_at) }} {{ $visita->created_at->format('H:i') }}</span>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
