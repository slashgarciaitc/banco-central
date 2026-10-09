@extends('layouts.admin')

@section('title', 'Reportes')
@section('section', 'Reportes')

@section('content')
    <div class="page-heading"><div><div class="eyebrow">Consolidación administrativa</div><h1>Reportes de operaciones</h1><p class="subheading">Resumen global y desglose por tipo de operación y nodo.</p></div><a class="button secondary" href="{{ route('admin.transactions.export', request()->query()) }}">Exportar transacciones CSV</a></div>
    @if ($error)<div class="alert error">No fue posible consultar Supabase: {{ $error }}</div>@endif
    <section class="card panel"><form class="filters" method="get" action="{{ route('admin.reports.index') }}"><div class="field"><label for="desde">Desde</label><input id="desde" type="date" name="desde" value="{{ $filters['desde'] ?? '' }}"></div><div class="field"><label for="hasta">Hasta</label><input id="hasta" type="date" name="hasta" value="{{ $filters['hasta'] ?? '' }}"></div><button class="button" type="submit">Generar reporte</button><a class="button secondary" href="{{ route('admin.reports.index') }}">Todo el período</a></form></section>
    @if ($report)
        @php($summary = $report['resumen'] ?? [])
        <div class="grid stats">
            <article class="card stat"><div class="stat-label">Nodos activos / total</div><div class="stat-value">{{ $summary['nodos_activos'] ?? 0 }} / {{ $summary['nodos'] ?? 0 }}</div><div class="stat-foot">Sucursales y cajeros registrados</div></article>
            <article class="card stat"><div class="stat-label">Cuentas abiertas</div><div class="stat-value">{{ number_format((int) ($summary['cuentas'] ?? 0)) }}</div><div class="stat-foot">Total en el núcleo central</div></article>
            <article class="card stat"><div class="stat-label">Efectivo total disponible</div><div class="stat-value">${{ number_format((float) ($summary['efectivo_total'] ?? 0), 2) }}</div><div class="stat-foot">Suma del efectivo actual de nodos</div></article>
            <article class="card stat"><div class="stat-label">Operaciones del período</div><div class="stat-value">{{ number_format((int) ($summary['transacciones'] ?? 0)) }}</div><div class="stat-foot">Volumen: ${{ number_format((float) ($summary['monto_operado'] ?? 0), 2) }}</div></article>
        </div>
        <section class="card panel"><div class="panel-header"><h2>Operaciones por tipo</h2></div>
            @if (count($report['por_tipo'] ?? []))<div class="table-wrap"><table><thead><tr><th>Tipo de operación</th><th>Cantidad</th><th>Volumen</th></tr></thead><tbody>@foreach ($report['por_tipo'] as $row)<tr><td><span class="badge">{{ ucfirst($row['tipo']) }}</span></td><td>{{ number_format((int) $row['cantidad']) }}</td><td class="money">${{ number_format((float) $row['monto'], 2) }}</td></tr>@endforeach</tbody></table></div>@else<div class="empty">No hubo operaciones en el período.</div>@endif
        </section>
        <section class="card panel"><div class="panel-header"><h2>Desglose por nodo</h2></div>
            @if (count($report['por_nodo'] ?? []))<div class="table-wrap"><table><thead><tr><th>Nodo</th><th>Tipo</th><th>Estado</th><th>Cuentas abiertas</th><th>Operaciones</th><th>Volumen</th><th>Efectivo actual</th></tr></thead><tbody>@foreach ($report['por_nodo'] as $node)<tr><td><strong>{{ $node['nodo'] }}</strong></td><td>{{ $node['tipo_nodo'] === 'cajero' ? 'ATM' : 'Sucursal' }}</td><td><span class="badge {{ $node['estado'] === 'activa' ? 'green' : 'gray' }}">{{ ucfirst($node['estado']) }}</span></td><td>{{ number_format((int) $node['cuentas_abiertas']) }}</td><td>{{ number_format((int) $node['transacciones']) }}</td><td class="money">${{ number_format((float) $node['monto_operado'], 2) }}</td><td class="money">${{ number_format((float) $node['efectivo'], 2) }}</td></tr>@endforeach</tbody></table></div>@else<div class="empty">No hay nodos registrados.</div>@endif
        </section>
        <section class="card panel"><div class="panel-header"><h2>Ajustes de efectivo del período</h2></div>
            @if (count($report['ajustes_efectivo'] ?? []))<div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Nodo</th><th>Ajuste</th><th>Efectivo nuevo</th><th>Motivo</th><th>Administrador</th></tr></thead><tbody>@foreach ($report['ajustes_efectivo'] as $adjustment)<tr><td class="muted">{{ \Illuminate\Support\Carbon::parse($adjustment['fecha'])->format('d/m/Y H:i:s') }}</td><td>{{ $adjustment['nodo'] }}</td><td class="money">{{ (float) $adjustment['delta'] > 0 ? '+' : '' }}${{ number_format((float) $adjustment['delta'], 2) }}</td><td>${{ number_format((float) $adjustment['efectivo_nuevo'], 2) }}</td><td>{{ $adjustment['motivo'] }}</td><td class="muted">{{ $adjustment['admin_email'] }}</td></tr>@endforeach</tbody></table></div>@else<div class="empty">No hay ajustes de caja en el período.</div>@endif
        </section>
    @endif
@endsection