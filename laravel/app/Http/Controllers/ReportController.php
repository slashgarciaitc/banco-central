<?php

namespace App\Http\Controllers;

use App\Services\SupabaseCentralService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReportController extends Controller
{
    public function index(Request $request, SupabaseCentralService $supabase)
    {
        $filters = $request->validate([
            'desde' => ['nullable', 'date'],
            'hasta' => ['nullable', 'date', 'after_or_equal:desde'],
        ]);

        $desde = empty($filters['desde'])
            ? null
            : Carbon::parse($filters['desde'])->startOfDay()->toIso8601String();
        $hasta = empty($filters['hasta'])
            ? null
            : Carbon::parse($filters['hasta'])->endOfDay()->toIso8601String();

        try {
            $report = $supabase->rpc('reporte_admin', [
                'p_desde' => $desde,
                'p_hasta' => $hasta,
            ]);
            $error = null;
        } catch (Throwable $exception) {
            Log::error('Falló la generación del reporte administrativo.', ['exception' => $exception->getMessage()]);
            $report = null;
            $error = $exception->getMessage();
        }

        return view('admin.reports', compact('report', 'filters', 'error'));
    }
}
