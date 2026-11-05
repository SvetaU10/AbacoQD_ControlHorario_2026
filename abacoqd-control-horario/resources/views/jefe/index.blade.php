@extends('layouts.app', ['titulo' => 'Estado'])

@section('contenido')
    <header class="equipo-cabeza">
        <div>
            <p class="espacio-sobre">Equipo</p>
            <h1>Estado del equipo</h1>
            <p class="secundario">{{ $fecha }} · Vista de seguimiento</p>
        </div>
    </header>

    @php
        $enlaceEquipo = fn (string $estado) => route('jefe.index', $filtroEstado === $estado ? [] : ['estado' => $estado]);
    @endphp

    @if ($conCierresAntiguos > 0)
        @php
            $personasConCierresAntiguos = $equipo
                ->filter(fn (array $fila) => $fila['antiguos'] > 0)
                ->pluck('persona.name')
                ->join(', ');
        @endphp
        <div class="equipo-aviso" role="status">
            <span class="equipo-aviso-icono" aria-hidden="true">!</span>
            <span class="franja-texto">
                <strong>{{ $personasConCierresAntiguos }} · {{ $conCierresAntiguos === 1 ? '1 persona tiene' : $conCierresAntiguos.' personas tienen' }} cierres sin resolver de hace más de {{ $diasHastaAvisar }} días.</strong>
                <span>Abre su registro indicando el motivo de consulta.</span>
            </span>
        </div>
    @endif

    <section class="equipo-contadores" aria-label="Resumen del equipo">
        <a class="equipo-contador equipo-contador-trabajando" href="{{ $enlaceEquipo('trabajando') }}" @if ($filtroEstado === 'trabajando') aria-current="true" @endif><i></i>{{ $cuentas['trabajando'] }} trabajando</a>
        <a class="equipo-contador equipo-contador-pausa" href="{{ $enlaceEquipo('pausa') }}" @if ($filtroEstado === 'pausa') aria-current="true" @endif><i></i>{{ $cuentas['pausa'] }} en pausa</a>
        <a class="equipo-contador equipo-contador-pendiente" href="{{ $enlaceEquipo('pendiente') }}" @if ($filtroEstado === 'pendiente') aria-current="true" @endif><i></i>{{ $cuentas['pendiente'] }} día sin cerrar</a>
        <a class="equipo-contador equipo-contador-ausencia" href="{{ $enlaceEquipo('ausencia') }}" @if ($filtroEstado === 'ausencia') aria-current="true" @endif><i></i>{{ $cuentas['ausencia'] }} ausencia prevista</a>
        <a class="equipo-contador equipo-contador-sin-empezar" href="{{ $enlaceEquipo('sin_empezar_hoy') }}" @if ($filtroEstado === 'sin_empezar_hoy') aria-current="true" @endif><i></i>{{ $cuentas['sin_empezar'] }} sin empezar</a>
    </section>

    @if ($equipo->isEmpty())
        <p>Todavía no hay personas en el equipo.</p>
    @elseif ($personas->isEmpty())
        <p class="registro-vacio">Ninguna persona coincide con el filtro.</p>
    @else
        <section class="equipo-tabla" aria-label="Estado de las personas">
            <div class="equipo-tabla-cabeza">
                <span>Persona</span>
                <span>Horario previsto</span>
                <span>Estado actual</span>
                <span>Desde</span>
                <span></span>
            </div>
            @foreach ($personas as $fila)
                @php
                    $tipoChip = match ($fila['estado']['estado']) {
                        'trabajando' => 'abierta',
                        'pausa' => 'en-pausa',
                        'pendiente' => 'tardia',
                        default => 'fuera',
                    };
                @endphp
                <div class="equipo-fila">
                    <div class="equipo-persona">
                        <span class="equipo-avatar">{{ collect(explode(' ', $fila['persona']->name))->map(fn ($parte) => mb_substr($parte, 0, 1))->take(2)->join('') }}</span>
                        <span>
                            <strong>{{ $fila['persona']->name }}</strong>
                            <small>Equipo AbacoQD</small>
                            @if ($fila['antiguos'] > 0)
                                <small class="equipo-antiguos">{{ $fila['antiguos'] === 1 ? '1 cierre sin resolver · antiguo' : $fila['antiguos'].' cierres sin resolver · antiguos' }}</small>
                            @endif
                        </span>
                    </div>
                    <span class="equipo-horario">{{ $fila['horario'] ? $fila['horario']->texto() : 'Sin horario asignado' }}</span>
                    <span><x-chip :tipo="$tipoChip">{{ $fila['estado']['estado'] === 'trabajando' ? 'Abierta' : $fila['estado']['texto'] }}</x-chip></span>
                    <span class="equipo-desde">{{ $fila['desde'] ?? '—' }}</span>
                    <a class="equipo-ver-registro" href="{{ route('jefe.registro', $fila['persona']) }}"
                        data-consulta-abre
                        data-consulta-action="{{ route('jefe.motivo', $fila['persona']) }}"
                        data-consulta-destino="{{ route('jefe.registro', $fila['persona']) }}">
                        <x-icono nombre="list" />
                        Ver registro
                    </a>
                </div>
            @endforeach
        </section>
    @endif

    <dialog class="dialogo-hoy dialogo-consulta" data-consulta-dialogo aria-labelledby="titulo-consulta">
        <div class="dialogo-consulta-cabeza">
            <h2 id="titulo-consulta">Motivo de la consulta</h2>
            <form method="dialog">
                <button class="dialogo-consulta-cerrar" type="submit" aria-label="Cerrar">×</button>
            </form>
        </div>
        <p class="dialogo-consulta-ayuda">Queda anotado quién abrió el registro, cuándo y por qué.</p>
        <form method="post" class="formulario dialogo-consulta-formulario" data-consulta-form>
            @csrf
            <input type="hidden" name="redirect_to" data-consulta-destino-input>
            <label for="consulta-reason">Motivo</label>
            <textarea id="consulta-reason" name="reason" maxlength="500" required placeholder="Escribe solo lo necesario"></textarea>
            <div class="dialogo-consulta-acciones">
                <button class="boton boton-secundario" type="button" data-consulta-cancelar>Cancelar</button>
                <button class="boton boton-principal" type="submit">Abrir registro</button>
            </div>
        </form>
    </dialog>
@endsection
