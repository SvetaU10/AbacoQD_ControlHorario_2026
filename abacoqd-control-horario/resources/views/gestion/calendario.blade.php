@extends('layouts.app', ['titulo' => 'Calendario laboral', 'miga' => ['texto' => 'Gestión', 'url' => route('gestion.index')]])

@section('contenido')
    @php
        $fuentes = collect($ultima?->fuentes ?? [])->merge($tramos->flatMap(fn ($tramo) => $tramo->importacion->fuentes ?? []))->unique('url');
        $dias = $diasAplicados->map(fn ($dia) => ['date' => $dia->holiday_date->toDateString(), 'name' => $dia->name, 'ambito' => $dia->ambito]);
        $municipioTexto = $municipioElegido ? $municipioElegido['nombre'].' · '.$municipioElegido['provincia'] : $centro->municipality;
    @endphp
    <header class="cal-carga-cabeza">
        <div class="cal-carga-titulo">
            <a class="cal-volver" href="{{ route('gestion.index') }}" aria-label="Volver a Gestión">
                <x-icono nombre="arrow-left" />
            </a>
            <div>
                <p class="espacio-sobre">Gestión · {{ $centro->name }}</p>
                <h1>Calendario laboral</h1>
                <p class="secundario">Los festivos salen del municipio de la sede. Elige desde cuándo usarlos.</p>
            </div>
        </div>
    </header>

    @if ($avisoCatalogo)
        <p class="franja" role="status">{{ $avisoCatalogo }}</p>
    @endif
    <section class="cal-carga-panel" aria-labelledby="titulo-cal-carga">
        <h2 id="titulo-cal-carga">Cargar o actualizar</h2>
        <form method="post" action="{{ route('gestion.calendario.cargar', $centro) }}" class="cal-carga-form" data-cal-carga-form>
            @csrf
            <div class="cal-carga-fila">
                <div class="cal-form-campo">
                    <label for="cal-anio">Año</label>
                    <input id="cal-anio" type="number" name="anio" value="{{ $anio }}" min="2020" max="{{ now()->year + 5 }}" required @if ($errors->has('anio')) aria-invalid="true" aria-describedby="cal-anio-error" @endif>
                    @error('anio')<p id="cal-anio-error" class="cal-error">{{ $message }}</p>@enderror
                </div>
                <div class="cal-municipio" data-municipio-buscador>
                    <label for="cal-municipio">Municipio</label>
                    <input id="cal-municipio" type="text" value="{{ $municipioTexto }}" autocomplete="off" aria-describedby="cal-municipio-ayuda{{ $errors->has('municipio_ine') ? ' cal-municipio-error' : '' }}" disabled @if ($errors->has('municipio_ine')) aria-invalid="true" @endif>
                    <input type="hidden" name="municipio_ine" value="{{ $municipioElegido['ine'] ?? '' }}" data-municipio-ine>
                    <p class="cal-ayuda" id="cal-municipio-ayuda" aria-live="polite" data-municipio-mensaje>{{ $municipioElegido ? $municipioElegido['provincia'].' · '.$municipioElegido['comunidad'].' · España' : 'Indica el municipio al editar la sede.' }}</p>
                    @error('municipio_ine')<p id="cal-municipio-error" class="cal-error">{{ $message }}</p>@enderror
                </div>
            </div>
            <fieldset class="cal-vigencia-form" @if ($errors->has('vigencia_modo')) aria-invalid="true" aria-describedby="cal-modo-error" @endif>
                <legend>Cuándo empieza</legend>
                <div class="cal-vigencia-opciones">
                    <label><input type="radio" name="vigencia_modo" value="anio" @checked($modoVigencia === 'anio')> Todo el año, desde el 1 de enero</label>
                    <label><input type="radio" name="vigencia_modo" value="fecha" @checked($modoVigencia === 'fecha')> Desde una fecha</label>
                </div>
                <label class="cal-vigencia-fecha" for="cal-vigente-desde">Empieza el
                    <input id="cal-vigente-desde" type="date" name="vigente_desde" value="{{ $fechaVigencia }}" min="{{ $anio }}-01-01" max="{{ $anio }}-12-31" aria-describedby="cal-vigencia-ayuda{{ $errors->has('vigente_desde') ? ' cal-vigencia-error' : '' }}" @disabled($modoVigencia === 'anio') @required($modoVigencia === 'fecha') @if ($errors->has('vigente_desde')) aria-invalid="true" @endif>
                </label>
                <p id="cal-vigencia-ayuda" class="cal-ayuda">Las fechas anteriores mantienen su calendario.</p>
                @error('vigencia_modo')<p id="cal-modo-error" class="cal-error">{{ $message }}</p>@enderror
                @error('vigente_desde')<p id="cal-vigencia-error" class="cal-error">{{ $message }}</p>@enderror
            </fieldset>
            <div class="cal-carga-acciones">
                <button class="boton boton-principal" type="submit" data-cal-carga-boton>Cargar / actualizar</button>
                <p class="cal-ayuda cal-carga-progreso" role="status" data-cal-carga-progreso hidden>Buscando las fiestas…</p>
            </div>
        </form>
    </section>

    @if ($ultima)
        <section class="cal-carga-panel cal-carga-resultado" aria-labelledby="titulo-cal-resultado">
            <header><h2 id="titulo-cal-resultado">{{ $etiquetasConsulta[$ultima->estado] ?? 'Última consulta' }}</h2><time class="secundario" datetime="{{ $ultima->consultado_at?->toIso8601String() }}">{{ $ultima->consultado_at?->format('d/m/Y H:i') }}</time></header>
            @unless (session('ok') === $resumenConsulta || session('aviso') === $resumenConsulta)<p>{{ $resumenConsulta }}</p>@endunless
            @if ($ultima->municipio)
                <p class="cal-ayuda">{{ $ultima->municipio }} · {{ $ultima->provincia }} · {{ $ultima->anio }} · Desde el {{ $ultima->vigente_desde?->format('d/m/Y') ?? '01/01/'.$ultima->anio }}</p>
            @endif
            @if ($ultima->puedeConfirmarse())
                <details class="cal-revision-panel"><summary>Revisar fechas y guardar</summary>
                    <p>Revisa las fuentes oficiales. Al confirmar, este municipio se aplicará desde el {{ $ultima->vigente_desde?->format('d/m/Y') ?? '01/01/'.$ultima->anio }}; las fechas anteriores mantendrán su calendario.</p>
                    @if ($fuentes->isNotEmpty())<p><a href="#cal-fuentes" data-cal-ver-fuentes>Ver fuentes oficiales</a></p>@endif
                    <div class="cal-tabla-contenedor" role="region" aria-label="Fechas para revisar" tabindex="0"><table class="cal-carga-tabla"><caption class="sr">Fiestas que se aplicarán a partir de la fecha elegida</caption><thead><tr><th scope="col">Fecha</th><th scope="col">Festividad</th><th scope="col">Tipo</th></tr></thead><tbody>@foreach ($ultima->festivos ?? [] as $dia)@if ($dia['date'] >= ($ultima->vigente_desde?->toDateString() ?? $ultima->anio.'-01-01'))<tr><td>{{ \Illuminate\Support\Carbon::parse($dia['date'])->format('d/m/Y') }}</td><td>{{ $dia['name'] }}</td><td><x-ambito-festivo :ambito="$dia['ambito']" /></td></tr>@endif @endforeach</tbody></table></div>
                    <form method="post" action="{{ route('gestion.calendario.confirmar', ['centro' => $centro, 'importacion' => $ultima]) }}" class="formulario">
                        @csrf
                        <label class="cal-revision"><input type="checkbox" name="revision" value="1" required> He comprobado estas fechas en las fuentes oficiales.</label>
                        <button type="submit" class="boton boton-principal">Guardar estas fechas</button>
                    </form>
                </details>
            @endif
            <details class="cal-detalle" data-consulta-detalle><summary>Detalles de la consulta</summary><p class="cal-ayuda">Resultado registrado: {{ ucfirst(str_replace('_', ' ', $ultima->estado)) }}</p><p class="cal-ayuda">{{ $ultima->mensaje }}</p></details>
        </section>
    @endif

    <section class="cal-carga-panel" aria-labelledby="titulo-cal-activo">
        <header class="cal-activo-cabeza"><div><h2 id="titulo-cal-activo">Calendario activo · {{ $anio }}</h2><p class="cal-ayuda">{{ $tramos->isNotEmpty() ? 'Cada fecha usa el municipio que le corresponde.' : 'Aún no hay un calendario completo confirmado para este centro y año.' }}</p></div>@if ($tramos->isNotEmpty())<span class="pastilla">{{ $dias->count() }} fechas</span>@endif</header>
        @include('calendario.leyenda')
        @include('calendario.vigencias')
        @if ($tramos->isNotEmpty())
            <details class="cal-fechas-guardadas">
                <summary>Ver los festivos guardados</summary>
                <div class="cal-tabla-contenedor" role="region" aria-label="Festivos guardados de {{ $anio }}" tabindex="0">
                    <table class="cal-carga-tabla">
                        <caption class="sr">Festivos guardados de {{ $anio }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">Fecha</th>
                                <th scope="col">Festividad</th>
                                <th scope="col">Tipo</th>
                                <th scope="col"><span class="sr">Editar</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($dias as $dia)
                                <tr>
                                    <td>{{ \Illuminate\Support\Carbon::parse($dia['date'])->format('d/m/Y') }}</td>
                                    <td>{{ $dia['name'] }}</td>
                                    <td><x-ambito-festivo :ambito="$dia['ambito']" /></td>
                                    <td class="festivo-editar-celda">
                                        <button class="festivo-editar" type="button" data-festivo-editar="{{ $dia['date'] }}" aria-label="Editar {{ $dia['name'] }}">
                                            <x-icono nombre="pencil" />
                                        </button>
                                        <dialog class="dialogo-hoy festivo-editar-dialogo" data-festivo-dialogo="{{ $dia['date'] }}" aria-labelledby="titulo-festivo-{{ $dia['date'] }}">
                                            <form method="dialog" class="festivo-editar-cerrar">
                                                <button type="submit" aria-label="Cerrar">×</button>
                                            </form>
                                            <h2 id="titulo-festivo-{{ $dia['date'] }}">Editar festivo</h2>
                                            <form method="post" action="{{ route('gestion.calendario.festivo', $centro) }}" class="formulario">
                                                @csrf
                                                @method('PATCH')
                                                <input type="hidden" name="fecha_original" value="{{ $dia['date'] }}">
                                                <label>Fecha <input name="fecha" type="date" value="{{ $dia['date'] }}" required></label>
                                                <label>Festividad <input name="nombre" value="{{ $dia['name'] }}" required maxlength="120"></label>
                                                <label>Tipo
                                                    <select name="ambito" required>
                                                        <option value="estado" @selected($dia['ambito'] === 'estado')>Nacional</option>
                                                        <option value="comunidad" @selected($dia['ambito'] === 'comunidad')>Autonómico</option>
                                                        <option value="provincia" @selected($dia['ambito'] === 'provincia')>Provincial</option>
                                                        <option value="insular" @selected($dia['ambito'] === 'insular')>Insular</option>
                                                        <option value="pueblo" @selected($dia['ambito'] === 'pueblo')>Municipal</option>
                                                    </select>
                                                </label>
                                                <button class="boton boton-principal" type="submit">Guardar</button>
                                            </form>
                                        </dialog>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @else
            <p>Carga el año para traer los festivos de la sede. Las vacaciones aprobadas y tus fichajes se conservan.</p>
        @endif
        <p class="cal-ayuda">Aquí guardas festivos. Las vacaciones personales se gestionan por separado.</p>
    </section>

    @if ($fuentes->isNotEmpty())
        <details id="cal-fuentes" class="cal-carga-panel cal-secundario"><summary>Fuentes de las fechas</summary><ul class="cal-fuentes-lista">@foreach ($fuentes as $fuente)<li>@if (str_starts_with($fuente['url'] ?? '', 'https://'))<a href="{{ $fuente['url'] }}" target="_blank" rel="noopener noreferrer">{{ $fuente['nombre'] }}<span class="sr"> (se abre en otra pestaña)</span></a>@else{{ $fuente['nombre'] }}@endif</li>@endforeach</ul><p class="cal-ayuda">Datos de festivos.io, CC BY 4.0, y de las fuentes indicadas. Ante diferencias prevalece la publicación oficial.</p></details>
    @endif
    @if ($historial->count() > 1)
        <details class="cal-carga-panel cal-secundario"><summary>Consultas anteriores de {{ $anio }}</summary><div class="cal-tabla-contenedor" role="region" aria-label="Consultas anteriores" tabindex="0"><table class="cal-carga-tabla"><caption class="sr">Últimas diez consultas de {{ $anio }}</caption><thead><tr><th scope="col">Consulta</th><th scope="col">Municipio</th><th scope="col">Desde</th><th scope="col">Resultado</th></tr></thead><tbody>@foreach ($historial as $consulta)<tr><td>{{ $consulta->consultado_at?->format('d/m/Y H:i') }}</td><td>{{ $consulta->municipio }}</td><td>{{ $consulta->vigente_desde?->format('d/m/Y') ?? '01/01/'.$consulta->anio }}</td><td>{{ $etiquetasConsulta[$consulta->estado] ?? 'Consulta' }}</td></tr>@endforeach</tbody></table></div></details>
    @endif

    <script>
    (() => {
        const raiz = document.querySelector('[data-municipio-buscador]');
        const codigo = raiz.querySelector('[data-municipio-ine]');
        const mensaje = raiz.querySelector('[data-municipio-mensaje]');
        const formulario = document.querySelector('[data-cal-carga-form]');
        const anio = document.querySelector('#cal-anio');
        const fechaEfecto = document.querySelector('#cal-vigente-desde');
        function actualizarVigencia() {
            const desdeFecha = formulario.querySelector('[name=vigencia_modo]:checked').value === 'fecha';
            fechaEfecto.disabled = !desdeFecha; fechaEfecto.required = desdeFecha;
            fechaEfecto.min = anio.value + '-01-01'; fechaEfecto.max = anio.value + '-12-31';
            if (!fechaEfecto.value.startsWith(anio.value + '-')) fechaEfecto.value = fechaEfecto.min;
        }
        anio.addEventListener('input', actualizarVigencia);
        formulario.querySelectorAll('[name=vigencia_modo]').forEach(campo => campo.addEventListener('change', actualizarVigencia));
        actualizarVigencia();
        document.querySelectorAll('[data-cal-ver-fuentes]').forEach(enlace => enlace.addEventListener('click', () => {
            const fuentes = document.querySelector('#cal-fuentes');
            if (fuentes) { fuentes.open = true; fuentes.querySelector('summary').focus(); }
        }));
        document.querySelectorAll('[data-festivo-editar]').forEach((boton) => {
            boton.addEventListener('click', () => {
                document.querySelector('[data-festivo-dialogo="' + boton.dataset.festivoEditar + '"]')?.showModal();
            });
        });
        formulario.addEventListener('submit', (evento) => {
            if (formulario.getAttribute('aria-busy') === 'true') { evento.preventDefault(); return; }
            if (!codigo.value) { evento.preventDefault(); mensaje.textContent = 'Indica el municipio al editar la sede.'; return; }
            formulario.setAttribute('aria-busy', 'true'); formulario.querySelector('[data-cal-carga-boton]').disabled = true; formulario.querySelector('[data-cal-carga-boton]').textContent = 'Buscando…'; formulario.querySelector('[data-cal-carga-progreso]').hidden = false;
        });
        window.addEventListener('pageshow', () => {
            actualizarVigencia(); formulario.removeAttribute('aria-busy'); formulario.querySelector('[data-cal-carga-boton]').disabled = false; formulario.querySelector('[data-cal-carga-boton]').textContent = 'Cargar / actualizar'; formulario.querySelector('[data-cal-carga-progreso]').hidden = true;
        });
    })();
    </script>
@endsection
