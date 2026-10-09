<?php

namespace App\Http\Controllers;

use App\Services\SupabaseCentralService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class TransactionController extends Controller
{
    public function index(Request $request, SupabaseCentralService $supabase)
    {
        $filters = $request->validate([
            'cuenta' => ['nullable', 'string', 'max:32', 'regex:/^\d+$/'],
            'nodo_id' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        try {
            $transactions = $supabase->table('transactions', $this->transactionQuery($filters, 200));
            $nodes = $supabase->table('nodos', ['select' => 'id,nombre,tipo', 'limit' => 500]);
            $error = null;
        } catch (Throwable $exception) {
            Log::error('Falló la consulta de transacciones.', ['exception' => $exception->getMessage()]);
            $transactions = [];
            $nodes = [];
            $error = $exception->getMessage();
        }

        return view('admin.transactions', [
            'transactions' => $transactions,
            'nodesById' => collect($nodes)->keyBy('id'),
            'filters' => $filters,
            'error' => $error,
        ]);
    }

    public function export(Request $request, SupabaseCentralService $supabase)
    {
        $filters = $request->validate([
            'cuenta' => ['nullable', 'string', 'max:32', 'regex:/^\d+$/'],
            'nodo_id' => ['nullable', 'integer', 'min:1'],
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        try {
            $transactions = $supabase->table('transactions', $this->transactionQuery($filters, 1000));
        } catch (Throwable $exception) {
            Log::error('Falló la exportación del historial.', ['exception' => $exception->getMessage()]);

            return back()->with('error', $exception->getMessage());
        }

        return response()->streamDownload(function () use ($transactions): void {
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ID', 'Tipo', 'Cuenta origen', 'Cuenta destino', 'Nodo ID', 'Monto', 'Fecha'], ',', '"', '\\');
            foreach ($transactions as $transaction) {
                fputcsv($output, [
                    $transaction['id'],
                    $transaction['tipo'],
                    $transaction['cuenta_origen'] ?? '',
                    $transaction['cuenta_destino'] ?? '',
                    $transaction['nodo_id'] ?? '',
                    $transaction['monto'],
                    $transaction['created_at'],
                ], ',', '"', '\\');
            }
            fclose($output);
        }, 'reporte-transacciones-'.now()->format('Ymd-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function transactionQuery(array $filters, int $limit): array
    {
        $query = [
            'select' => 'id,cuenta_origen,cuenta_destino,monto,tipo,nodo_id,created_at',
            'order' => 'created_at.desc',
            'limit' => $limit,
        ];

        if (! empty($filters['nodo_id'])) {
            $query['nodo_id'] = 'eq.'.$filters['nodo_id'];
        }
        if (! empty($filters['cuenta'])) {
            $account = $filters['cuenta'];
            $query['or'] = "(cuenta_origen.eq.{$account},cuenta_destino.eq.{$account})";
        }

        $dateConditions = [];
        if (! empty($filters['desde'])) {
            $dateConditions[] = 'created_at.gte.'.Carbon::parse($filters['desde'])->startOfDay()->toIso8601String();
        }
        if (! empty($filters['hasta'])) {
            $dateConditions[] = 'created_at.lte.'.Carbon::parse($filters['hasta'])->endOfDay()->toIso8601String();
        }
        if ($dateConditions !== []) {
            $query['and'] = '('.implode(',', $dateConditions).')';
        }

        return $query;
    }
}
