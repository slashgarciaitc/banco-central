@extends('layouts.admin')

@section('title', 'Resumen')
@section('section', 'Resumen')

@section('content')
    <div class="page-heading"><div><div class="eyebrow">Vista general</div><h1>Resumen del núcleo</h1><p class="subheading">Estado consolidado de sucursales, cajeros y operaciones.</p></div><a class="button" href="{{ route('admin.nodes.index') }}">＋ Registrar nodo</a></div>
    @if ($error)<div class="alert error">No fue posible consultar Supabase: {{ $error }}</div>@endif
    <div class="grid stats">
        <article class="card stat"><div class="stat-label">Nodos registrados</div><div class="stat-value">{{ count($nodes) }}</div><div class="stat-foot">Sucursales y cajeros automáticos</div></article>
        <article class="card stat"><div class="stat-label">Nodos activos</div><div class="stat-value">{{ $activeNodes ?? 0 }}</div><div class="stat-foot">Con API key habilitada</div></article>
        <article class="card stat"><div class="stat-label">Efectivo disponible</div><div class="stat-value">${{ number_format($cashTotal ?? 0, 2) }}</div><div class="stat-foot">Total reportado en nodos</div></article>
    </div>
    <section class="card panel"><div class="panel-header"><h2>Nodos bancarios</h2><a class="text-link" href="{{ route('admin.nodes.index') }}">Ver todos →</a></div>
        @if (count($nodes))<div class="table-wrap"><table><thead><tr><th>Nodo</th><th>Tipo</th><th>Responsable</th><th>Efectivo</th><th>Estado</th></tr></thead><tbody>
            @foreach ($nodes as $node)<tr><td><strong>{{ $node['nombre'] }}</strong></td><td>{{ $node['tipo'] === 'cajero' ? 'Cajero ATM' : 'Sucursal' }}</td><td>{{ $node['responsable'] ?: '—' }}</td><td class="money">${{ number_format((float) $node['efectivo'], 2) }}</td><td><span class="badge {{ $node['estado'] === 'activa' ? 'green' : 'gray' }}">{{ ucfirst($node['estado']) }}</span></td></tr>@endforeach
        </tbody></table></div>@else<div class="empty">Aún no hay nodos registrados. Crea una sucursal o cajero para comenzar.</div>@endif
    </section>
    <section class="card panel"><div class="panel-header"><h2>Actividad reciente</h2><a class="text-link" href="{{ route('admin.transactions.index') }}">Historial global →</a></div>
        @if (count($transactions))<div class="table-wrap"><table><thead><tr><th>Operación</th><th>Cuenta</th><th>Nodo</th><th>Monto</th><th>Fecha</th></tr></thead><tbody>
            @foreach ($transactions as $transaction)<tr><td><span class="badge">{{ ucfirst($transaction['tipo']) }}</span></td><td>{{ $transaction['cuenta_origen'] ?? $transaction['cuenta_destino'] ?? '—' }}</td><td>{{ isset($nodesById) ? ($nodesById->get($transaction['nodo_id'])['nombre'] ?? '—') : '—' }}</td><td class="money">${{ number_format((float) $transaction['monto'], 2) }}</td><td class="muted">{{ \Illuminate\Support\Carbon::parse($transaction['created_at'])->format('d/m/Y H:i') }}</td></tr>@endforeach
        </tbody></table></div>@else<div class="empty">Todavía no hay transacciones.</div>@endif
    </section>
@endsection