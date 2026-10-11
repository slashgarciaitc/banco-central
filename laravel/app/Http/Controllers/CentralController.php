<?php

namespace App\Http\Controllers;

use App\Services\SupabaseCentralService;
use Illuminate\Support\Facades\Log;
use Throwable;

class CentralController extends Controller
{
    public function index(SupabaseCentralService $supabase)
    {
        try {
            $nodes = $supabase->table('nodos', [
                'select' => 'id,tipo,nombre,responsable,efectivo,estado,created_at',
                'order' => 'created_at.desc',
                'limit' => 200,
            ]);
            $transactions = $supabase->table('transactions', [
                'select' => 'id,cuenta_origen,cuenta_destino,monto,tipo,nodo_id,created_at',
                'order' => 'created_at.desc',
                'limit' => 8,
            ]);
        } catch (Throwable $exception) {
            Log::error('Falló la carga del panel central.', ['exception' => $exception->getMessage()]);

            return view('admin.dashboard', [
                'nodes' => [],
                'transactions' => [],
                'error' => $exception->getMessage(),
            ]);
        }

        $nodesById = collect($nodes)->keyBy('id');

        return view('admin.dashboard', [
            'nodes' => $nodes,
            'transactions' => $transactions,
            'nodesById' => $nodesById,
            'error' => null,
            'activeNodes' => collect($nodes)->where('estado', 'activa')->count(),
            'cashTotal' => collect($nodes)->sum(fn (array $node): float => (float) $node['efectivo']),
        ]);
    }
}
