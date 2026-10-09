<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'central.supabase_url' => 'https://demo.supabase.co',
            'central.service_role_key' => 'server-only-test-key',
        ]);
    }

    public function test_an_admin_can_register_a_node_and_receive_its_key_once(): void
    {
        Http::fake([
            'https://demo.supabase.co/rest/v1/rpc/registrar_nodo' => Http::response([
                'id' => 17,
                'api_key' => 'bk_demo_key',
            ]),
        ]);

        $this->withSession(['central_admin' => ['email' => 'admin@example.test']])
            ->post('/admin/nodos', [
                'tipo' => 'cajero',
                'nombre' => 'ATM Centro',
                'responsable' => 'Operaciones',
                'efectivo' => 5000,
            ])
            ->assertRedirect(route('admin.nodes.index'))
            ->assertSessionHas('new_api_key', 'bk_demo_key');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/rest/v1/rpc/registrar_nodo')
            && $request['p_tipo'] === 'cajero'
            && $request['p_efectivo'] === 5000);
    }

    public function test_admin_can_export_an_authenticated_csv_report(): void
    {
        Http::fake([
            'https://demo.supabase.co/rest/v1/transactions*' => Http::response([[
                'id' => 1,
                'tipo' => 'retiro',
                'cuenta_origen' => '400000000001',
                'cuenta_destino' => null,
                'monto' => 300,
                'nodo_id' => 7,
                'created_at' => '2026-10-09T12:00:00Z',
            ]]),
        ]);

        $response = $this->withSession(['central_admin' => ['email' => 'admin@example.test']])
            ->get('/admin/transacciones/exportar?cuenta=400000000001');

        $response->assertDownload();
        $this->assertStringContainsString('400000000001', $response->streamedContent());
    }

    public function test_admin_can_make_an_audited_cash_adjustment(): void
    {
        Http::fake([
            'https://demo.supabase.co/rest/v1/rpc/ajustar_efectivo' => Http::response([
                'ajuste_id' => 31,
                'nodo_id' => 17,
                'delta' => '-125.50',
                'efectivo_nuevo' => '4874.50',
            ]),
        ]);

        $this->from('/admin/nodos')
            ->withSession(['central_admin' => ['email' => 'admin@example.test']])
            ->post(route('admin.nodes.cash-adjustments.store'), [
                'nodo_id' => 17,
                'direccion' => 'salida',
                'monto' => '125.50',
                'motivo' => 'Conciliación cierre',
            ])
            ->assertRedirect('/admin/nodos')
            ->assertSessionHas('success');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/rest/v1/rpc/ajustar_efectivo')
            && (int) $request['p_nodo_id'] === 17
            && $request['p_delta'] === '-125.50'
            && $request['p_admin_email'] === 'admin@example.test');
    }

    public function test_admin_can_view_aggregated_reports_from_supabase(): void
    {
        Http::fake([
            'https://demo.supabase.co/rest/v1/rpc/reporte_admin' => Http::response([
                'resumen' => [
                    'nodos' => 2,
                    'nodos_activos' => 2,
                    'efectivo_total' => 25000,
                    'cuentas' => 4,
                    'transacciones' => 3,
                    'monto_operado' => 700,
                ],
                'por_tipo' => [['tipo' => 'retiro', 'cantidad' => 1, 'monto' => 300]],
                'por_nodo' => [],
                'ajustes_efectivo' => [],
            ]),
        ]);

        $this->withSession(['central_admin' => ['email' => 'admin@example.test']])
            ->get('/admin/reportes?desde=2026-10-01&hasta=2026-10-09')
            ->assertOk()
            ->assertSee('Reportes de operaciones')
            ->assertSee('$700.00');

        Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/rest/v1/rpc/reporte_admin')
            && $request['p_desde'] === '2026-10-01T00:00:00+00:00'
            && $request['p_hasta'] === '2026-10-09T23:59:59+00:00');
    }
}
