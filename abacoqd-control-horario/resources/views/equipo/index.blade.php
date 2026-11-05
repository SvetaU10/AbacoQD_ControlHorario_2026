@extends('layouts.app', ['ancha' => true, 'titulo' => 'Personas'])

@section('contenido')
    <div class="personas-pagina">
        <header class="personas-cabeza">
            <div>
                <h1>Personas</h1>
                <p class="secundario">Gestiona las cuentas, horarios y accesos del equipo.</p>
            </div>
            <x-boton tipo="principal" :href="route('equipo.create')">
                <x-icono nombre="user-plus" />
                Dar de alta
            </x-boton>
        </header>

        <div class="personas-resumen" aria-label="Resumen del equipo">
            <span><strong>{{ $personas->count() }}</strong> {{ $personas->count() === 1 ? 'persona' : 'personas' }}</span>
        </div>

        <section class="personas-grid" aria-label="Personas del equipo">
            @foreach ($personas as $persona)
                @php
                    $partes = preg_split('/\s+/', trim($persona->name)) ?: [];
                    $iniciales = mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[1] ?? '', 0, 1));
                    $papel = $persona->esJefa() ? 'Responsable' : ($persona->esTecnico() ? 'Técnico' : 'Trabajador');
                    $horario = $persona->horarios
                        ->filter(fn ($horario) => $horario->effective_from?->lte(now()))
                        ->sortByDesc('effective_from')
                        ->first();
                @endphp
                <article class="persona-card">
                    <div class="persona-card-cabeza">
                        <span class="persona-card-avatar" aria-hidden="true">{{ $iniciales }}</span>
                        <span class="persona-card-nombre">
                            <strong>{{ $persona->name }}</strong>
                            <small>{{ $persona->sede?->name ?? $persona->municipality }}</small>
                        </span>
                        <span class="persona-card-rol">{{ $papel }}</span>
                    </div>
                    <details class="persona-card-ficha">
                        <summary class="persona-card-enlace">
                            Ver ficha
                            <span aria-hidden="true">›</span>
                        </summary>
                        <div class="persona-card-detalles">
                            <div class="persona-card-detalle">
                                <span>Fecha de inicio</span>
                                <strong>{{ $persona->starts_on?->format('d/m/Y') ?? 'Sin indicar' }}</strong>
                            </div>
                            <div class="persona-card-detalle">
                                <span>Tipo de jornada</span>
                                <strong>{{ $horario?->tipoJornada()?->etiqueta() ?? 'Sin asignar' }}</strong>
                            </div>
                            <div class="persona-card-detalle">
                                <span>Tipo de contrato</span>
                                <strong>{{ $persona->ends_on ? 'Temporal' : 'Indefinido' }}</strong>
                            </div>
                            <div class="persona-card-detalle">
                                <span>Horario de trabajo</span>
                                <strong>{{ $horario?->texto() ?? 'Sin asignar' }}</strong>
                            </div>
                        </div>
                    </details>
                </article>
            @endforeach
        </section>
        @if ($mostrarTodas || $totalPersonas > $personas->count())
        <div class="personas-pie">
            <x-boton
                tipo="principal"
                :href="route('equipo.index', $mostrarTodas ? [] : ['todas_personas' => 1])"
                class="personas-todos"
            >
                {{ $mostrarTodas ? 'Volver' : 'Todos los trabajadores' }}
            </x-boton>
        </div>
        @endif
    </div>
@endsection
