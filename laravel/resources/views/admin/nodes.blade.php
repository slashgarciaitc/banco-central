@extends('layouts.admin')

@section('title', 'Sucursales y ATM')
@section('section', 'Sucursales y ATM')

@section('content')
    <div class="page-heading"><div><div class="eyebrow">Infraestructura</div><h1>Sucursales y cajeros</h1><p class="subheading">Registra nodos, responsables y administra el acceso a la red central.</p></div></div>
    @if ($error)<div class="alert error">No fue posible consultar Supabase: {{ $error }}</div>@endif
    @if ($key = session('new_api_key'))<div class="secret-box"><strong>API key nueva · se muestra una sola vez</strong><code>{{ $key }}</code><p class="hint">Cópiala ahora y entrégala al nodo por un canal seguro. Solo se almacena el hash.</p></div>@endif
    <section class="card panel"><div class="panel-header"><h2>Registrar un nodo</h2></div>
        <form method="post" action="{{ route('admin.nodes.store') }}">@csrf
            <div class="form-grid">
                <div class="field"><label for="tipo">Tipo de nodo</label><select id="tipo" name="tipo" required><option value="sucursal">Sucursal</option><option value="cajero">Cajero automático (ATM)</option></select>@error('tipo')<span class="hint">{{ $message }}</span>@enderror</div>
                <div class="field"><label for="nombre">Nombre</label><input id="nombre" name="nombre" value="{{ old('nombre') }}" maxlength="120" required placeholder="Sucursal Centro"></div>
                <div class="field"><label for="responsable">Responsable</label><input id="responsable" name="responsable" value="{{ old('responsable') }}" maxlength="120" placeholder="Nombre del responsable"></div>
                <div class="field"><label for="efectivo">Efectivo inicial</label><input id="efectivo" name="efectivo" type="number" min="0" step="0.01" value="{{ old('efectivo', '0.00') }}" required><span class="hint">Se usa como disponibilidad inicial del nodo.</span></div>
                <div class="field full"><div><button class="button" type="submit">Crear nodo y generar API key</button></div></div>
            </div>
        </form>
    </section>
    <section class="card panel"><div class="panel-header"><h2>Nodos registrados</h2><span class="muted">{{ count($nodes) }} en total</span></div>
        @if (count($nodes))<div class="table-wrap"><table><thead><tr><th>ID</th><th>Tipo</th><th>Configuración</th><th>Efectivo actual</th><th>Estado / acceso</th></tr></thead><tbody>
            @foreach ($nodes as $node)<tr><td class="muted">#{{ $node['id'] }}</td><td><span class="badge">{{ $node['tipo'] === 'cajero' ? 'ATM' : 'Sucursal' }}</span></td><td><form class="inline-form" method="post" action="{{ route('admin.nodes.update', $node['id']) }}">@csrf @method('PATCH')<input aria-label="Nombre del nodo" name="nombre" value="{{ $node['nombre'] }}" maxlength="120" required><input aria-label="Responsable" name="responsable" value="{{ $node['responsable'] }}" maxlength="120" placeholder="Responsable"><button class="button secondary small" type="submit">Guardar datos</button></form></td><td class="money">${{ number_format((float) $node['efectivo'], 2) }}</td><td><span class="badge {{ $node['estado'] === 'activa' ? 'green' : 'gray' }}">{{ ucfirst($node['estado']) }}</span><form method="post" action="{{ route('admin.nodes.update', $node['id']) }}" style="margin-top:7px">@csrf @method('PATCH')<input type="hidden" name="estado" value="{{ $node['estado'] === 'activa' ? 'inactiva' : 'activa' }}"><button class="button secondary small" type="submit">{{ $node['estado'] === 'activa' ? 'Desactivar acceso' : 'Reactivar acceso' }}</button></form></td></tr>@endforeach
        </tbody></table></div>@else<div class="empty">No hay nodos para mostrar.</div>@endif
    </section>
    <section class="card panel"><div class="panel-header"><h2>Conciliación de efectivo</h2><span class="muted">Cada ajuste queda en una bitácora inmutable</span></div>
        @if (count($nodes))<form method="post" action="{{ route('admin.nodes.cash-adjustments.store') }}">@csrf
            <div class="form-grid">
                <div class="field"><label for="nodo_id">Nodo</label><select id="nodo_id" name="nodo_id" required><option value="">Selecciona un nodo</option>@foreach ($nodes as $node)<option value="{{ $node['id'] }}">{{ $node['nombre'] }} · efectivo ${{ number_format((float) $node['efectivo'], 2) }}</option>@endforeach</select></div>
                <div class="field"><label for="direccion">Movimiento</label><select id="direccion" name="direccion" required><option value="entrada">Agregar efectivo</option><option value="salida">Retirar efectivo</option></select></div>
                <div class="field"><label for="monto">Monto</label><input id="monto" name="monto" type="number" min="0.01" max="999999999999.99" step="0.01" required></div>
                <div class="field"><label for="motivo">Motivo / referencia</label><input id="motivo" name="motivo" minlength="3" maxlength="500" required placeholder="Conciliación de caja al cierre"></div>
                <div class="field full"><div><button class="button" type="submit">Registrar ajuste auditable</button></div></div>
            </div>
        </form>@else<div class="empty">Registra primero un nodo para poder conciliar efectivo.</div>@endif
    </section>
    <section class="card panel"><div class="panel-header"><h2>Bitácora de ajustes</h2><span class="muted">Últimos {{ count($adjustments) }} movimientos</span></div>
        @if (count($adjustments))<div class="table-wrap"><table><thead><tr><th>Fecha</th><th>Nodo</th><th>Movimiento</th><th>Antes</th><th>Después</th><th>Motivo</th><th>Administrador</th></tr></thead><tbody>
            @foreach ($adjustments as $adjustment)<tr><td class="muted">{{ \Illuminate\Support\Carbon::parse($adjustment['created_at'])->format('d/m/Y H:i:s') }}</td><td>{{ collect($nodes)->firstWhere('id', $adjustment['nodo_id'])['nombre'] ?? '#'.$adjustment['nodo_id'] }}</td><td class="money">{{ (float) $adjustment['delta'] > 0 ? '+' : '' }}${{ number_format((float) $adjustment['delta'], 2) }}</td><td>${{ number_format((float) $adjustment['efectivo_anterior'], 2) }}</td><td>${{ number_format((float) $adjustment['efectivo_nuevo'], 2) }}</td><td>{{ $adjustment['motivo'] }}</td><td class="muted">{{ $adjustment['admin_email'] }}</td></tr>@endforeach
        </tbody></table></div>@else<div class="empty">Aún no se registraron ajustes manuales.</div>@endif
    </section>
@endsection