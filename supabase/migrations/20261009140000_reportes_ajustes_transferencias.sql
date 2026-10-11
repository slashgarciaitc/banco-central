-- Ajustes de caja con bitácora append-only. La escritura se hace únicamente
-- mediante una función RPC invocable por service_role desde el panel Laravel.
create table public.cash_adjustments (
  id bigint generated always as identity primary key,
  nodo_id bigint not null references public.nodos (id),
  delta numeric(14, 2) not null check (delta <> 0),
  efectivo_anterior numeric(14, 2) not null check (efectivo_anterior >= 0),
  efectivo_nuevo numeric(14, 2) not null check (efectivo_nuevo >= 0),
  motivo text not null check (char_length(trim(motivo)) between 3 and 500),
  admin_email text not null,
  created_at timestamptz not null default now()
);

alter table public.cash_adjustments enable row level security;
revoke all on public.cash_adjustments from anon, authenticated;
grant select on public.cash_adjustments to service_role;

create trigger cash_adjustments_inmutables
  before update or delete on public.cash_adjustments
  for each row execute function public.transacciones_inmutables();

create function public.ajustar_efectivo(
  p_nodo_id bigint,
  p_delta numeric,
  p_motivo text,
  p_admin_email text
) returns jsonb
language plpgsql security definer set search_path = ''
as $$
declare
  v_nodo public.nodos;
  v_antes numeric(14, 2);
  v_despues numeric(14, 2);
  v_ajuste public.cash_adjustments;
begin
  if p_delta is null or p_delta = 0 or p_delta <> round(p_delta, 2) then
    raise exception 'El ajuste debe ser distinto de cero y tener máximo dos decimales' using errcode = 'PT400';
  end if;
  if p_motivo is null or char_length(trim(p_motivo)) not between 3 and 500 then
    raise exception 'El motivo debe tener entre 3 y 500 caracteres' using errcode = 'PT400';
  end if;
  if p_admin_email is null or char_length(trim(p_admin_email)) = 0 then
    raise exception 'Falta identificar al administrador' using errcode = 'PT400';
  end if;

  select * into v_nodo from public.nodos where id = p_nodo_id for update;
  if not found then
    raise exception 'El nodo no existe' using errcode = 'PT404';
  end if;

  v_antes := v_nodo.efectivo;
  v_despues := v_antes + p_delta;
  if v_despues < 0 then
    raise exception 'El ajuste dejaría el efectivo por debajo de cero' using errcode = 'PT422';
  end if;
  if v_despues > 999999999999.99 then
    raise exception 'El efectivo supera el máximo permitido' using errcode = 'PT422';
  end if;

  update public.nodos set efectivo = v_despues where id = v_nodo.id;
  insert into public.cash_adjustments
    (nodo_id, delta, efectivo_anterior, efectivo_nuevo, motivo, admin_email)
  values
    (v_nodo.id, p_delta, v_antes, v_despues, trim(p_motivo), lower(trim(p_admin_email)))
  returning * into v_ajuste;

  return jsonb_build_object(
    'ajuste_id', v_ajuste.id,
    'nodo_id', v_nodo.id,
    'efectivo_anterior', v_antes,
    'delta', p_delta,
    'efectivo_nuevo', v_despues,
    'motivo', v_ajuste.motivo,
    'admin_email', v_ajuste.admin_email,
    'fecha', v_ajuste.created_at
  );
end;
$$;

create function public.transferir(
  p_api_key text,
  p_cuenta_origen text,
  p_cuenta_destino text,
  p_monto numeric
) returns jsonb
language plpgsql security definer set search_path = ''
as $$
declare
  v_nodo public.nodos := public.nodo_por_key(p_api_key);
  v_origen public.users_accounts;
  v_destino public.users_accounts;
  v_tx public.transactions;
begin
  if p_cuenta_origen is null or p_cuenta_destino is null or p_cuenta_origen = p_cuenta_destino then
    raise exception 'Las cuentas de origen y destino deben ser distintas' using errcode = 'PT400';
  end if;
  if p_monto is null or p_monto <= 0 or p_monto <> round(p_monto, 2) then
    raise exception 'El monto debe ser positivo y tener máximo dos decimales' using errcode = 'PT400';
  end if;

  -- Bloquea en orden de id para prevenir saldos negativos y reducir deadlocks concurrentes.
  perform id from public.users_accounts
   where numero_cuenta in (p_cuenta_origen, p_cuenta_destino)
   order by id
   for update;

  select * into v_origen from public.users_accounts where numero_cuenta = p_cuenta_origen;
  if not found then
    raise exception 'La cuenta de origen no existe' using errcode = 'PT404';
  end if;
  select * into v_destino from public.users_accounts where numero_cuenta = p_cuenta_destino;
  if not found then
    raise exception 'La cuenta de destino no existe' using errcode = 'PT404';
  end if;
  if v_origen.estado <> 'activa' or v_destino.estado <> 'activa' then
    raise exception 'Ambas cuentas deben estar activas' using errcode = 'PT403';
  end if;
  if v_origen.saldo < p_monto then
    raise exception 'Fondos insuficientes' using errcode = 'PT422';
  end if;

  update public.users_accounts set saldo = saldo - p_monto where id = v_origen.id;
  update public.users_accounts set saldo = saldo + p_monto where id = v_destino.id;

  insert into public.transactions (cuenta_origen, cuenta_destino, monto, tipo, nodo_id)
  values (v_origen.numero_cuenta, v_destino.numero_cuenta, p_monto, 'transferencia', v_nodo.id)
  returning * into v_tx;

  return jsonb_build_object(
    'transaccion_id', v_tx.id,
    'cuenta_origen', v_origen.numero_cuenta,
    'saldo_origen', v_origen.saldo - p_monto,
    'cuenta_destino', v_destino.numero_cuenta,
    'saldo_destino', v_destino.saldo + p_monto,
    'monto', p_monto,
    'nodo_id', v_nodo.id,
    'fecha', v_tx.created_at
  );
end;
$$;

create function public.reporte_admin(
  p_desde timestamptz default null,
  p_hasta timestamptz default null
) returns jsonb
language sql stable security definer set search_path = ''
as $$
  select jsonb_build_object(
    'desde', p_desde,
    'hasta', p_hasta,
    'resumen', jsonb_build_object(
      'nodos', (select count(*) from public.nodos),
      'nodos_activos', (select count(*) from public.nodos where estado = 'activa'),
      'efectivo_total', coalesce((select sum(efectivo) from public.nodos), 0),
      'cuentas', (select count(*) from public.users_accounts),
      'transacciones', (
        select count(*) from public.transactions t
         where (p_desde is null or t.created_at >= p_desde)
           and (p_hasta is null or t.created_at <= p_hasta)
      ),
      'monto_operado', coalesce((
        select sum(t.monto) from public.transactions t
         where (p_desde is null or t.created_at >= p_desde)
           and (p_hasta is null or t.created_at <= p_hasta)
      ), 0)
    ),
    'por_tipo', coalesce((
      select jsonb_agg(jsonb_build_object('tipo', r.tipo, 'cantidad', r.cantidad, 'monto', r.monto) order by r.tipo)
        from (
          select t.tipo, count(*) as cantidad, sum(t.monto) as monto
            from public.transactions t
           where (p_desde is null or t.created_at >= p_desde)
             and (p_hasta is null or t.created_at <= p_hasta)
           group by t.tipo
        ) r
    ), '[]'::jsonb),
    'por_nodo', coalesce((
      select jsonb_agg(jsonb_build_object(
        'nodo_id', n.id,
        'nodo', n.nombre,
        'tipo_nodo', n.tipo,
        'estado', n.estado,
        'efectivo', n.efectivo,
        'cuentas_abiertas', (select count(*) from public.users_accounts a where a.nodo_id = n.id),
        'transacciones', (
          select count(*) from public.transactions t
           where t.nodo_id = n.id
             and (p_desde is null or t.created_at >= p_desde)
             and (p_hasta is null or t.created_at <= p_hasta)
        ),
        'monto_operado', coalesce((
          select sum(t.monto) from public.transactions t
           where t.nodo_id = n.id
             and (p_desde is null or t.created_at >= p_desde)
             and (p_hasta is null or t.created_at <= p_hasta)
        ), 0)
      ) order by n.nombre)
        from public.nodos n
    ), '[]'::jsonb),
    'ajustes_efectivo', coalesce((
      select jsonb_agg(jsonb_build_object(
        'ajuste_id', a.id,
        'nodo_id', a.nodo_id,
        'nodo', n.nombre,
        'delta', a.delta,
        'efectivo_anterior', a.efectivo_anterior,
        'efectivo_nuevo', a.efectivo_nuevo,
        'motivo', a.motivo,
        'admin_email', a.admin_email,
        'fecha', a.created_at
      ) order by a.created_at desc)
        from public.cash_adjustments a
        join public.nodos n on n.id = a.nodo_id
       where (p_desde is null or a.created_at >= p_desde)
         and (p_hasta is null or a.created_at <= p_hasta)
    ), '[]'::jsonb)
  );
$$;

revoke execute on function public.ajustar_efectivo(bigint, numeric, text, text) from public, anon, authenticated;
revoke execute on function public.reporte_admin(timestamptz, timestamptz) from public, anon, authenticated;
grant execute on function public.ajustar_efectivo(bigint, numeric, text, text) to service_role;
grant execute on function public.reporte_admin(timestamptz, timestamptz) to service_role;

revoke execute on function public.transferir(text, text, text, numeric) from public, anon, authenticated;
grant execute on function public.transferir(text, text, text, numeric) to anon, authenticated;