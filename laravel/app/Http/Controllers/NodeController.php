<?php

namespace App\Http\Controllers;

use App\Services\SupabaseCentralService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class NodeController extends Controller
{
    public function index(SupabaseCentralService $supabase)
    {
        try {
            $nodes = $supabase->table('nodos', [
                'select' => 'id,tipo,nombre,responsable,efectivo,estado,created_at',
                'order' => 'created_at.desc',
                'limit' => 500,
            ]);
            $adjustments = $supabase->table('cash_adjustments', [
                'select' => 'id,nodo_id,delta,efectivo_anterior,efectivo_nuevo,motivo,admin_email,created_at',
                'order' => 'created_at.desc',
                'limit' => 50,
            ]);
            $error = null;
        } catch (Throwable $exception) {
            Log::error('Falló la consulta de nodos.', ['exception' => $exception->getMessage()]);
            $nodes = [];
            $adjustments = [];
            $error = $exception->getMessage();
        }

        return view('admin.nodes', compact('nodes', 'adjustments', 'error'));
    }

    public function store(Request $request, SupabaseCentralService $supabase)
    {
        $values = $request->validate([
            'tipo' => ['required', 'in:sucursal,cajero'],
            'nombre' => ['required', 'string', 'max:120'],
            'responsable' => ['nullable', 'string', 'max:120'],
            'efectivo' => ['required', 'numeric', 'min:0', 'max:999999999999.99'],
        ]);

        try {
            $node = $supabase->rpc('registrar_nodo', [
                'p_tipo' => $values['tipo'],
                'p_nombre' => $values['nombre'],
                'p_responsable' => $values['responsable'] ?? null,
                'p_efectivo' => $values['efectivo'],
            ]);
        } catch (Throwable $exception) {
            Log::error('Falló el registro del nodo.', ['exception' => $exception->getMessage()]);

            return back()->withInput()->with('error', $exception->getMessage());
        }

        return redirect()->route('admin.nodes.index')
            ->with('success', 'Nodo creado. Copia y guarda su API key; no volverá a mostrarse.')
            ->with('new_api_key', $node['api_key'] ?? null);
    }

    public function update(Request $request, int $id, SupabaseCentralService $supabase)
    {
        $values = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:120'],
            'responsable' => ['sometimes', 'nullable', 'string', 'max:120'],
            'estado' => ['sometimes', 'required', 'in:activa,inactiva'],
        ]);

        if ($values === []) {
            return back()->with('error', 'Indica al menos un dato del nodo para actualizar.');
        }

        try {
            $updated = $supabase->updateNode($id, $values);
        } catch (Throwable $exception) {
            Log::error('Falló la actualización del nodo.', ['node_id' => $id, 'exception' => $exception->getMessage()]);

            return back()->withInput()->with('error', $exception->getMessage());
        }

        if ($updated === []) {
            return back()->with('error', 'No se encontró el nodo solicitado.');
        }

        return back()->with('success', 'Nodo actualizado correctamente.');
    }

    public function adjustCash(Request $request, SupabaseCentralService $supabase)
    {
        $values = $request->validate([
            'nodo_id' => ['required', 'integer', 'min:1'],
            'direccion' => ['required', 'in:entrada,salida'],
            'monto' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:999999999999.99'],
            'motivo' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $amount = ltrim((string) $values['monto'], '+');
        $delta = $values['direccion'] === 'salida' ? '-'.$amount : $amount;

        try {
            $supabase->rpc('ajustar_efectivo', [
                'p_nodo_id' => $values['nodo_id'],
                'p_delta' => $delta,
                'p_motivo' => trim($values['motivo']),
                'p_admin_email' => (string) $request->session()->get('central_admin.email'),
            ]);
        } catch (Throwable $exception) {
            Log::error('Falló el ajuste de efectivo.', ['node_id' => $values['nodo_id'], 'exception' => $exception->getMessage()]);

            return back()->withInput()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'Ajuste de efectivo registrado en la bitácora inmutable.');
    }
}
