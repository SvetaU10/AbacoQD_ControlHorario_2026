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
            <span><strong>{{ $personas->count() }}</strong> {{ $verBajas ? ($personas->count() === 1 ? 'baja' : 'bajas') : ($personas->count() === 1 ? 'persona' : 'personas') }}</span>
        </div>

        <section class="personas-grid" aria-label="{{ $verBajas ? 'Personas de baja' : 'Personas del equipo' }}">
            @forelse ($personas as $persona)
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
                        <span class="persona-card-lado">
                            <span class="persona-card-rol">{{ $papel }}</span>
                            <a class="persona-card-editar" href="{{ route('equipo.edit', $persona) }}" aria-label="Editar">
                                <x-icono nombre="pencil" />
                            </a>
                        </span>
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
                            @unless ($persona->active)
                                <div class="persona-card-detalle">
                                    <span>Día de la baja</span>
                                    <strong>{{ $persona->baja_on?->format('d/m/Y') ?? 'Sin indicar' }}</strong>
                                </div>
                            @endunless
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
                    @unless ($persona->active)
                        <div class="persona-reactivar">
                            <button class="boton boton-principal" type="button" data-reactivar-abre="{{ $persona->id }}">
                                <x-icono nombre="user-plus" />
                                Reactivar
                            </button>
                            <dialog class="reactivar-dialogo" data-reactivar-dialogo="{{ $persona->id }}">
                                <form method="dialog" class="reactivar-cerrar-form">
                                    <button type="submit" aria-label="Cerrar">×</button>
                                </form>
                                <p class="secundario">Bajas</p>
                                <h2>Reactivar a {{ $persona->name }}</h2>
                                <p>Volverá a Personas y podrá entrar otra vez. El contrato, los fichajes, el horario y las ausencias se quedan como estaban.</p>
                                <form method="post" action="{{ route('equipo.reactivar', $persona) }}">
                                    @csrf
                                    <button class="boton boton-principal" type="submit">Reactivar</button>
                                </form>
                            </dialog>
                        </div>
                    @endunless
                </article>
            @empty
                @if ($verBajas)
                    <p class="secundario">No hay personas de baja.</p>
                @endif
            @endforelse
        </section>
        <div class="personas-pie">
            @if (! $verBajas && ($mostrarTodas || $totalPersonas > $personas->count()))
                <x-boton
                    tipo="principal"
                    :href="route('equipo.index', $mostrarTodas ? [] : ['todas_personas' => 1])"
                    class="personas-todos"
                >
                    {{ $mostrarTodas ? 'Volver' : 'Todos los trabajadores' }}
                </x-boton>
            @endif
            <x-boton
                tipo="principal"
                :href="route('equipo.index', $verBajas ? [] : ['bajas' => 1])"
                class="personas-bajas"
            >
                @if ($verBajas)
                    <x-icono nombre="arrow-left" />
                    Volver
                @else
                    <x-icono nombre="user-minus" />
                    Bajas
                @endif
            </x-boton>
        </div>
    </div>
    <script>
        document.querySelectorAll('[data-reactivar-abre]').forEach(function (boton) {
            boton.addEventListener('click', function () {
                var dialogo = document.querySelector('[data-reactivar-dialogo="' + boton.getAttribute('data-reactivar-abre') + '"]');
                if (dialogo) {
                    dialogo.showModal();
                }
            });
        });
    </script>
@endsection
