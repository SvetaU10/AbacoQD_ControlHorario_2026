@extends('layouts.app', ['ancha' => true, 'titulo' => 'Alta'])

@section('contenido')
    <a class="volver" href="{{ route('equipo.index') }}">Volver al equipo</a>
    <h1>Dar de alta</h1>
    <p class="secundario">Elige el horario, el tiempo y la sede. El calendario usa los festivos de esa sede.</p>

    <form method="get" action="{{ route('equipo.create') }}#alta-calendario" class="alta-panel">
        <input type="hidden" name="anio" value="{{ $anio }}">
        <div class="alta-fila">
            <label for="tipo">Tipo de jornada
                <select id="tipo" name="tipo" onchange="this.form.requestSubmit()">
                    <option value="" @selected($tipo === null)>Elige un tipo</option>
                    @foreach ($tipos as $opcion)
                        <option value="{{ $opcion->value }}" @selected($tipo === $opcion)>{{ $opcion->etiqueta() }}</option>
                    @endforeach
                </select>
            </label>
            <label for="starts_on">Desde
                <x-fecha id="starts_on" name="starts_on" :value="$startsOn" required onchange="this.form.requestSubmit()" />
            </label>
            <label for="duracion">Tiempo en la empresa
                <select id="duracion" name="duracion" onchange="this.form.requestSubmit()">
                    <option value="" @selected($duracion === null)>Elige el tiempo</option>
                    @foreach ($duraciones as $opcion)
                        @php
                            $finOpcion = $opcion->fechaFin(\Illuminate\Support\Carbon::parse($startsOn), $endsOn);
                            $textoDuracion = $opcion->etiqueta();
                            if ($opcion !== \App\DuracionEstancia::Indefinido && $opcion !== \App\DuracionEstancia::HastaElDia && $finOpcion) {
                                $textoDuracion .= ' · Hasta el '.\Illuminate\Support\Carbon::parse($finOpcion)->format('d/m/Y');
                            }
                        @endphp
                        <option value="{{ $opcion->value }}" @selected($duracion === $opcion)>{{ $textoDuracion }}</option>
                    @endforeach
                </select>
            </label>
            <label for="centro">Sede municipal
                <select id="centro" name="centro" required onchange="this.form.requestSubmit()">
                    @foreach ($sedes as $opcion)
                        <option value="{{ $opcion->name }}" @selected($sede->id === $opcion->id)>{{ $opcion->etiqueta() }}</option>
                    @endforeach
                </select>
            </label>
        </div>
        @if ($duracion === \App\DuracionEstancia::HastaElDia)
            <div class="alta-extra">
                <label for="ends_on">Último día
                    <x-fecha id="ends_on" name="ends_on" :value="$endsOn" :min="$startsOn" onchange="this.form.requestSubmit()" />
                </label>
            </div>
        @endif
        @if ($tipo)
            <p class="alta-nota">{{ $tipo->descripcion() }} {{ $tipo->textoHorario() }}.</p>
        @endif
    </form>

    @if ($tipo && $duracion === \App\DuracionEstancia::HastaElDia && ! $periodoListo)
        <p class="franja" role="status">Elige el último día para verlo en el calendario.</p>
    @elseif ($tipo && ! $periodoListo)
        <p class="franja" role="status">Elige cuánto tiempo estará para verlo en el calendario.</p>
    @endif

    <details id="alta-calendario" class="alta-bloque alta-calendario" @if ($periodoListo) open @endif>
        <summary id="alta-calendario-titulo">
            @if ($periodoListo && $finPeriodo)
                Calendario del periodo
            @else
                Calendario {{ $anio }}
            @endif
        </summary>
        <div class="alta-bloque-cuerpo calendario-pagina">
            @if ($navegaAnios)
                <nav class="calendario-anio" aria-label="Cambiar año">
                    @if ($puedeRetroceder)
                        <a href="{{ route('equipo.create', array_merge($consulta, ['anio' => $anio - 1])) }}#alta-calendario" aria-label="Año anterior">‹</a>
                    @else
                        <span aria-hidden="true">‹</span>
                    @endif
                    <strong>{{ $anio }}</strong>
                    @if ($puedeAvanzar)
                        <a href="{{ route('equipo.create', array_merge($consulta, ['anio' => $anioSiguiente])) }}#alta-calendario" aria-label="Año siguiente">›</a>
                    @else
                        <span aria-hidden="true">›</span>
                    @endif
                </nav>
            @endif
            @if ($sinCalendario)
                <p class="franja" role="status">El calendario está pendiente de cargar. Puedes configurar el horario ahora.</p>
            @else
                <p class="secundario">Festivos públicos de {{ $centro }} ({{ $sede->autonomia() }}).</p>
            @endif

            <ul class="cal-leyenda" aria-label="Leyenda del calendario">
                <li><span class="cal-muestra cal-muestra-estado" aria-hidden="true"></span>Festivo Nacional</li>
                <li><span class="cal-muestra cal-muestra-comunidad" aria-hidden="true"></span>Festivo Autonómico</li>
                <li><span class="cal-muestra cal-muestra-pueblo" aria-hidden="true"></span>Municipal</li>
                <li><span class="cal-muestra cal-muestra-provincia" aria-hidden="true"></span>Provincial/insular <small>cuando exista</small></li>
                @if ($periodoListo)
                    @php
                        $leyendaLaborable = 'Laborable';
                        if ($tipo) {
                            $leyendaLaborable .= ' · '.$tipo->etiqueta();
                        }
                        if ($duracion) {
                            $leyendaLaborable .= ' · '.$duracion->etiqueta();
                        }
                        $claseLaborable = 'cal-muestra muestra-laborable';
                        if ($tipo) {
                            $claseLaborable .= ' muestra-laborable-'.$tipo->value;
                        }
                    @endphp
                    <li><span class="{{ $claseLaborable }}" aria-hidden="true"></span>{{ $leyendaLaborable }}</li>
                    <li><span class="cal-muestra muestra-fuera" aria-hidden="true"></span>Fuera del tiempo en la empresa</li>
                @endif
            </ul>

            <section class="calendario-anual" aria-label="Calendario anual">
                @foreach ($meses as $mes)
                    @php
                        $numeroMes = ['enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4, 'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8, 'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12][$mes['nombre']];
                        $tituloMes = ucfirst($mes['nombre']).($muestraAnioMes ? ' '.$mes['anio'] : '');
                    @endphp
                    <section class="calendario-anual-mes" aria-labelledby="titulo-mes-{{ $mes['anio'] }}-{{ $mes['nombre'] }}">
                        <h2 id="titulo-mes-{{ $mes['anio'] }}-{{ $mes['nombre'] }}">{{ $tituloMes }}</h2>
                        <div class="calendario-anual-rejilla">
                            @foreach (['L', 'M', 'X', 'J', 'V', 'S', 'D'] as $letra)
                                <span class="calendario-anual-cabecera">{{ $letra }}</span>
                            @endforeach
                            @foreach ($mes['dias'] as $dia)
                                @if ($dia === null)
                                    <span class="casilla calendario-anual-dia calendario-anual-vacio"></span>
                                @else
                                    @php
                                        $festivo = $dia['festivo'];
                                        $fecha = sprintf('%04d-%02d-%02d', $mes['anio'], $numeroMes, $dia['numero']);
                                        $clases = 'casilla calendario-anual-dia';
                                        if ($dia['hoy']) {
                                            $clases .= ' calendario-anual-hoy';
                                        }
                                        if ($festivo) {
                                            $clases .= ' calendario-anual-'.$festivo->ambito;
                                        } elseif ($dia['finde']) {
                                            $clases .= ' calendario-anual-finde';
                                        } elseif ($periodoListo && $dia['laborable'] && $dia['en_periodo']) {
                                            $clases .= ' casilla-laborable '.($tipo ? 'casilla-laborable-'.$tipo->value : 'casilla-en-periodo');
                                        } elseif ($periodoListo && $dia['laborable']) {
                                            $clases .= ' casilla-fuera';
                                        }
                                        $titulo = collect([
                                            $festivo ? $festivo->name.'. '.$festivo->etiqueta().'.' : null,
                                            $dia['hoy'] ? 'Hoy.' : null,
                                            $tipo && $dia['laborable'] && $dia['en_periodo'] ? $tipo->etiqueta().'.' : null,
                                            $periodoListo && $dia['laborable'] && ! $dia['en_periodo'] ? 'Fuera del tiempo en la empresa.' : null,
                                        ])->filter()->implode(' ');
                                        $relleno = $festivo?->ambito ?? '';
                                    @endphp
                                    <span class="{{ $clases }}" data-fecha="{{ $fecha }}" data-ambito="{{ $festivo?->ambito }}" data-relleno="{{ $relleno }}" @if ($titulo !== '') title="{{ $titulo }}" @endif>
                                        <time datetime="{{ $fecha }}">{{ $dia['numero'] }}</time>
                                        @if ($festivo)<span class="sr">{{ $festivo->name }} · {{ $festivo->etiqueta() }}</span>@endif
                                    </span>
                                @endif
                            @endforeach
                        </div>
                        @php
                            $festivosMes = collect($mes['dias'])->filter(fn (?array $dia): bool => $dia !== null && $dia['festivo'] !== null);
                        @endphp
                        @if ($festivosMes->isNotEmpty())
                            <ul class="cal-anual-eventos" aria-label="Festivos de {{ $tituloMes }}">
                                @foreach ($festivosMes as $dia)
                                    <li>
                                        <time datetime="{{ $dia['festivo']->holiday_date->toDateString() }}">{{ $dia['numero'] }}</time>
                                        <div><span>{{ $dia['festivo']->name }}</span><x-ambito-festivo :ambito="$dia['festivo']->ambito" class="cal-anual-tipo" /></div>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>
                @endforeach
            </section>
        </div>
    </details>

    @if ($tipo && $periodoListo)
        <form method="post" action="{{ route('equipo.store') }}" class="formulario">
            @csrf
            <input type="hidden" name="tipo" value="{{ $tipo->value }}">
            <input type="hidden" name="centro" value="{{ $sede->name }}">
            <input type="hidden" name="starts_on" value="{{ $startsOn }}">
            <input type="hidden" name="duracion" value="{{ $duracion->value }}">
            @if ($duracion === \App\DuracionEstancia::HastaElDia)
                <input type="hidden" name="ends_on" value="{{ $endsOn }}">
            @endif

            <details class="alta-bloque" open>
                <summary>Datos de acceso</summary>
                <div class="alta-bloque-cuerpo">
                    <p class="secundario">
                        Horario de {{ $tipo->etiqueta() }}: {{ $tipo->textoHorario() }}.
                        @if ($finPeriodo)
                            En la empresa desde el {{ \Illuminate\Support\Carbon::parse($startsOn)->format('d/m/Y') }} hasta el {{ \Illuminate\Support\Carbon::parse($finPeriodo)->format('d/m/Y') }}.
                        @else
                            En la empresa desde el {{ \Illuminate\Support\Carbon::parse($startsOn)->format('d/m/Y') }}, sin fecha de fin.
                        @endif
                        Sede en {{ $centro }}. Los festivos no esperan fichaje.
                    </p>
                    <div class="alta-fila">
                        <label for="name">Nombre
                            <input id="name" name="name" value="{{ old('name') }}" required maxlength="120">
                        </label>
                        <label for="email">Correo
                            <input id="email" name="email" type="email" value="{{ old('email') }}" required>
                        </label>
                        <label for="password">Contraseña inicial
                            <input id="password" name="password" type="text" required minlength="8" autocomplete="new-password">
                        </label>
                    </div>
                    <div class="alta-acciones">
                        <button class="boton boton-principal" type="submit">Guardar alta</button>
                    </div>
                </div>
            </details>
        </form>
    @endif
@endsection
