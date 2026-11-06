<div class="vacaciones-panel">
    <form method="dialog" class="vacaciones-cerrar-form">
        <button class="vacaciones-cerrar" type="submit" data-vacaciones-cierra aria-label="Cerrar">×</button>
    </form>
    <header class="vacaciones-panel-cabeza">
        <div>
            <p class="vacaciones-panel-kicker">Año {{ $anio }}</p>
            <h2 id="titulo-vacaciones">Vacaciones</h2>
        </div>
        <p class="vacaciones-panel-cupo">
            <strong>{{ $vacaciones['disponibles'] }}</strong>
            <span>{{ $vacaciones['disponibles'] === 1 ? 'día disponible' : 'días disponibles' }}</span>
        </p>
    </header>

    <section>
        <h3>Aprobadas</h3>
        @if ($vacacionesAprobadas === [])
            <p class="secundario">No hay vacaciones aprobadas.</p>
        @else
            <ul class="vacaciones-lista">
                @foreach ($vacacionesAprobadas as $periodo)
                    <li class="vacaciones-aprobada">{{ $periodo }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    <section>
        <h3>Rechazadas</h3>
        @if ($vacacionesRechazadas === [])
            <p class="secundario">No hay vacaciones rechazadas.</p>
        @else
            <ul class="vacaciones-lista">
                @foreach ($vacacionesRechazadas as $periodo)
                    <li class="vacaciones-rechazada">{{ $periodo }}</li>
                @endforeach
            </ul>
        @endif
    </section>

    <button class="boton boton-principal" type="button" data-vacaciones-pedir>Pedir vacaciones</button>
</div>
