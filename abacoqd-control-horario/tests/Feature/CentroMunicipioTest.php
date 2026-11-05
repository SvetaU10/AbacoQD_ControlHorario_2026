<?php

namespace Tests\Feature;

use App\Models\Centro;
use App\Models\Festivo;
use App\Models\ImportacionCalendario;
use App\Models\User;
use App\Services\CatalogoMunicipios;
use App\Services\NagerFestivos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CentroMunicipioTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_alta_resuelve_ine_y_no_confia_en_provincia_o_comunidad_enviadas(): void
    {
        $jefa = User::factory()->jefa()->create();

        $this->actingAs($jefa)->post(route('equipo.centros.store'), [
            'name' => 'Sede Álava', 'municipio_ine' => '01001',
            'municipality' => 'Texto falso', 'community' => 'Autonomía falsa', 'province' => 'Provincia falsa',
        ])->assertSessionHasNoErrors()->assertSessionHas('ok');

        $sede = Centro::query()->where('name', 'Sede Álava')->firstOrFail();
        $this->assertSame('01001', $sede->municipio_ine);
        $this->assertSame('Alegría-Dulantzi', $sede->municipality);
        $this->assertSame('Araba/Álava', $sede->province);
        $this->assertSame('01', $sede->province_code);
        $this->assertSame('País Vasco', $sede->community);
        $this->assertSame('ES-PV', $sede->comunidad_codigo);
        Http::assertNothingSent();
    }

    public function test_la_edicion_cambia_a_cualquier_municipio_y_arrastra_usuarios_e_invalida_calendario(): void
    {
        $jefa = User::factory()->jefa()->create();
        $sede = $jefa->sede;
        $this->calendarioConfirmado($sede, 2026);
        $original = ImportacionCalendario::query()->where('centro_id', $sede->id)->sole();

        $this->actingAs($jefa)->patch(route('equipo.centros.update', $sede), [
            'name' => 'Sede València', 'municipio_ine' => '46250',
        ])->assertSessionHasNoErrors()->assertSessionHas('ok');

        $sede->refresh();
        $this->assertSame('46250', $sede->municipio_ine);
        $this->assertSame('València', $sede->municipality);
        $this->assertSame('Valencia/València', $sede->province);
        $this->assertSame('Comunitat Valenciana', $sede->community);
        $this->assertSame('València', $jefa->fresh()->municipality);
        $this->assertNull($original->fresh()->clave_activa);
        $this->assertSame('configuracion_modificada', ImportacionCalendario::query()->where('centro_id', $sede->id)->latest('id')->firstOrFail()->estado);
        Http::assertNothingSent();
    }

    public function test_renombrar_con_el_mismo_ine_conserva_el_calendario(): void
    {
        $jefa = User::factory()->jefa()->create();
        $sede = $jefa->sede;
        $sede->update(['municipio_ine' => '28079', 'municipality' => 'Madrid', 'community' => 'Comunidad de Madrid']);
        $this->calendarioConfirmado($sede, 2026);
        $original = ImportacionCalendario::query()->where('centro_id', $sede->id)->sole();

        $this->actingAs($jefa)->patch(route('equipo.centros.update', $sede), [
            'name' => 'Nombre nuevo', 'municipio_ine' => '28079',
        ])->assertSessionHasNoErrors()->assertSessionHas('ok');

        $this->assertSame($sede->id.':2026', $original->fresh()->clave_activa);
        $this->assertSame(1, ImportacionCalendario::query()->where('centro_id', $sede->id)->count());
    }

    public function test_el_formulario_legacy_no_conserva_la_identidad_ine_de_otro_territorio(): void
    {
        $jefa = User::factory()->jefa()->create();
        $sede = $jefa->sede;
        $sede->update(['municipio_ine' => '28079', 'province' => 'Madrid', 'province_code' => '28', 'comunidad_codigo' => 'ES-MD']);
        $this->calendarioConfirmado($sede, 2026);

        $this->actingAs($jefa)->patch(route('equipo.centros.update', $sede), [
            'name' => 'Sede por revisar', 'municipality' => 'Getafe', 'community' => 'Comunidad de Madrid',
        ])->assertSessionHasNoErrors()->assertSessionHas('ok');

        $sede->refresh();
        $this->assertNull($sede->municipio_ine);
        $this->assertNull($sede->province);
        $this->assertNull($sede->comunidad_codigo);
        $this->assertSame(0, ImportacionCalendario::query()->where('centro_id', $sede->id)->whereNotNull('clave_activa')->count());
    }

    public function test_un_ine_inexistente_no_crea_ni_cambia_sedes(): void
    {
        $jefa = User::factory()->jefa()->create();
        $total = Centro::query()->count();

        $this->actingAs($jefa)->post(route('equipo.centros.store'), [
            'name' => 'Sede inexistente', 'municipio_ine' => '99999',
        ])->assertSessionHasErrors('municipio_ine');
        $this->patch(route('equipo.centros.update', $jefa->sede), [
            'name' => 'No se debe guardar', 'municipio_ine' => '99999',
        ])->assertSessionHasErrors('municipio_ine');

        $this->assertSame($total, Centro::query()->count());
        $this->assertSame('Madrid', $jefa->sede->fresh()->name);
        Http::assertNothingSent();
    }

    public function test_cambiar_sede_conserva_festivos_manuales_pasados_sin_inventar_cobertura(): void
    {
        $this->travelTo(Carbon::parse('2026-10-30 10:00:00', 'Europe/Madrid'));
        $jefa = User::factory()->jefa()->create();
        $sede = $jefa->sede;
        Festivo::create(['holiday_date' => '2026-01-10', 'name' => 'Madrid manual enero', 'ambito' => 'pueblo', 'municipality' => 'Madrid', 'centro_id' => $sede->id]);
        Festivo::create(['holiday_date' => '2026-11-10', 'name' => 'Madrid manual futuro', 'ambito' => 'pueblo', 'municipality' => 'Madrid', 'centro_id' => $sede->id]);
        Festivo::create(['holiday_date' => '2026-11-11', 'name' => 'Segovia manual futuro', 'ambito' => 'pueblo', 'municipality' => 'Segovia']);

        $this->actingAs($jefa)->patch(route('equipo.centros.update', $sede), [
            'name' => 'Sede Segovia', 'municipio_ine' => '40194',
        ])->assertSessionHasNoErrors()->assertSessionHas('ok');

        $this->actingAs($jefa->fresh());
        $this->get(route('calendario', ['anio' => 2026, 'mes' => 1]))->assertOk()->assertSee('Madrid manual enero');
        $this->get(route('calendario', ['anio' => 2026, 'mes' => 11]))->assertOk()
            ->assertSee('Segovia manual futuro')->assertDontSee('Madrid manual futuro');
        $this->assertDatabaseHas('importaciones_calendario', ['centro_id' => $sede->id, 'estado' => 'contexto_manual', 'municipio' => 'Madrid']);
        $this->assertFalse(app(NagerFestivos::class)->coberturaConfirmada($sede->fresh(), 2026, Carbon::parse('2026-01-10')));
        $this->assertFalse(app(NagerFestivos::class)->coberturaConfirmada($sede->fresh(), 2026, Carbon::parse('2026-11-11')));
        $this->assertSame(3, Festivo::query()->count());
        Http::assertNothingSent();
    }

    public function test_gestion_muestra_selector_y_procedencia_del_catalogo_local(): void
    {
        $jefa = User::factory()->jefa()->create();

        $this->actingAs($jefa)->get(route('gestion.index'))->assertOk()
            ->assertSee('data-sede-municipio', false)
            ->assertSee('name="municipio_ine"', false)
            ->assertSee('8.132 municipios')
            ->assertSee(app(CatalogoMunicipios::class)->estado()['referencia'], false);

        Http::assertNothingSent();
    }
}
