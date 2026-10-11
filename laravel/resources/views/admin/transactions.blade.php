@extends('layouts.admin')

@section('title', 'Transacciones')
@section('section', 'Transacciones')

@section('content')
    <div class="page-heading"><div><div class="eyebrow">Auditoría</div><h1>Historial global</h1><p class="subheading">Consulta operaciones consolidadas por cuenta o nodo.</p></div></div>
    @if ($error)<div class="alert error">No fue posible consultar Supabase: {{ $error }}</div>@endif
    <section class="card panel"><form class="filters" method="get" action="{{ route('admin.transactions.index') }}"><div class="field"><label for="cuenta">Número de cuenta</label><input id="cuenta" name="cuenta" inputmode="numeric" value="{{ $filters['cuenta'] ?? '' }}" placeholder="4000000001"></div><div class="field"><label for="nodo_id">Nodo</label><select id="nodo_id" name="nodo_id"><option value="">Todos los nodos</option>@foreach ($nodesById as $node)<option value="{{ $node['id'] }}" @selected((string) ($filters['nodo_id'] ?? '') === (string) $node['id'])>{{ $node['nombre'] }} · {{ $node['tipo'] }}</option>@endforeach</select></div><div class="field"><label for="desde">Desde</label><input id="desde" type="date" name="desde" value="{{ $filters['desde'] ?? '' }}"></div><div class="field"><label for="hasta">Hasta</label><input id="hasta" type="date" name="hasta" value="{{ $filters['hasta'] ?? '' }}"></div><button class="button" type="submit">Filtrar historial</button><a class="button secondary" href="{{ route('admin.transactions.index') }}">Limpiar</a><a class="button secondary" href="{{ route('admin.transactions.export', request()->query()) }}">Exportar CSV</a></form></section>
    <section class="card panel"><div class="panel-header"><h2>Operaciones</h2><span class="muted">Máximo 200 resultados por consulta</span></div>
        @if (count($transactions))<div class="table-wrap"><table><thead><tr><th>Transacción</th><th>Tipo</th><th>Cuenta origen</th><th>Cuenta destino</th><th>Nodo</th><th>Monto</th><th>Fecha</th></tr></thead><tbody>
            @foreach ($transactions as $transaction)<tr><td class="muted">#{{ $transaction['id'] }}</td><td><span class="badge">{{ ucfirst($transaction['tipo']) }}</span></td><td>{{ $transaction['cuenta_origen'] ?? '—' }}</td><td>{{ $transaction['cuenta_destino'] ?? '—' }}</td><td>{{ $nodesById->get($transaction['nodo_id'])['nombre'] ?? '—' }}</td><td class="money">${{ number_format((float) $transaction['monto'], 2) }}</td><td class="muted">{{ \Illuminate\Support\Carbon::parse($transaction['created_at'])->format('d/m/Y H:i:s') }}</td></tr>@endforeach
        </tbody></table></div>@else<div class="empty">No se encontraron transacciones con esos filtros.</div>@endif
    </section>
@endsection