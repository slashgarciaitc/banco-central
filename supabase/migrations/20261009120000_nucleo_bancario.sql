create extension if not exists pgcrypto with schema extensions;

create table public.nodos (
  id bigint generated always as identity primary key,
  tipo text not null check (tipo in ('sucursal', 'cajero')),
  nombre text not null,
  responsable text,
  efectivo numeric(14, 2) not null default 0 check (efectivo >= 0),
  api_key_hash text not null unique,
  created_at timestamptz not null default now()
);

create sequence public.numero_cuenta_seq;

create table public.users_accounts (
  id bigint generated always as identity primary key,
  numero_cuenta text not null unique
    default ('4000' || lpad(nextval('public.numero_cuenta_seq')::text, 6, '0')),
  titular text not null,
  saldo numeric(14, 2) not null default 0 check (saldo >= 0),
  estado text not null default 'activa' check (estado in ('activa', 'bloqueada')),
  nodo_id bigint references public.nodos (id), -- sucursal que abrió la cuenta
  created_at timestamptz not null default now()
);

create table public.transactions (
  id bigint generated always as identity primary key,
  cuenta_origen text references public.users_accounts (numero_cuenta),
  cuenta_destino text references public.users_accounts (numero_cuenta),
  monto numeric(14, 2) not null check (monto > 0),
  tipo text not null check (tipo in ('retiro', 'deposito', 'transferencia')),
  nodo_id bigint references public.nodos (id),
  created_at timestamptz not null default now()
);

-- RLS activo y sin políticas: con la llave pública no se puede leer ni escribir
-- en las tablas, solo llamar a las funciones de abajo.
alter table public.nodos enable row level security;
alter table public.users_accounts enable row level security;
alter table public.transactions enable row level security;

revoke all on public.nodos, public.users_accounts, public.transactions from anon, authenticated;

-- Las transacciones no se pueden editar ni borrar, ni siquiera con la service key.
create function public.transacciones_inmutables() returns trigger
language plpgsql
as $$
begin
  raise exception 'Las transacciones no se pueden modificar ni borrar' using errcode = 'PT403';
end;
$$;

create trigger transactions_inmutables
  before update or delete on public.transactions
  for each row execute function public.transacciones_inmutables();

-- con errcode 'PTxxx' supabase responde con el código http xxx

create function public.nodo_por_key(p_api_key text) returns public.nodos
language plpgsql stable security definer set search_path = ''
as $$
declare
  v_nodo public.nodos;
begin
  select * into v_nodo from public.nodos
   where api_key_hash = encode(extensions.digest(p_api_key, 'sha256'), 'hex');

  if not found then
    raise exception 'API key inválida' using errcode = 'PT401';
  end if;
  return v_nodo;
end;
$$;

-- solo la usa el panel del banco central; en la tabla se guarda el hash de la key
create function public.registrar_nodo(
  p_tipo text, p_nombre text, p_responsable text default null, p_efectivo numeric default 0
) returns json
language plpgsql security definer set search_path = ''
as $$
declare
  v_key text := 'bk_' || encode(extensions.gen_random_bytes(24), 'hex');
  v_id bigint;
begin
  insert into public.nodos (tipo, nombre, responsable, efectivo, api_key_hash)
  values (p_tipo, p_nombre, p_responsable, p_efectivo,
          encode(extensions.digest(v_key, 'sha256'), 'hex'))
  returning id into v_id;

  return json_build_object('id', v_id, 'api_key', v_key);
end;
$$;

create function public.info_nodo(p_api_key text) returns json
language plpgsql stable security definer set search_path = ''
as $$
declare
  v_nodo public.nodos := public.nodo_por_key(p_api_key);
begin
  return json_build_object(
    'id', v_nodo.id,
    'tipo', v_nodo.tipo,
    'nombre', v_nodo.nombre,
    'responsable', v_nodo.responsable,
    'efectivo', v_nodo.efectivo
  );
end;
$$;

create function public.crear_cuenta(
  p_api_key text, p_titular text, p_saldo_inicial numeric default 0
) returns json
language plpgsql security definer set search_path = ''
as $$
declare
  v_nodo public.nodos := public.nodo_por_key(p_api_key);
  v_cuenta public.users_accounts;
begin
  if v_nodo.tipo <> 'sucursal' then
    raise exception 'Solo una sucursal puede abrir cuentas' using errcode = 'PT403';
  end if;

  insert into public.users_accounts (titular, saldo, nodo_id)
  values (p_titular, p_saldo_inicial, v_nodo.id)
  returning * into v_cuenta;

  -- el saldo inicial se registra como un depósito en la sucursal
  if p_saldo_inicial > 0 then
    insert into public.transactions (cuenta_destino, monto, tipo, nodo_id)
    values (v_cuenta.numero_cuenta, p_saldo_inicial, 'deposito', v_nodo.id);

    update public.nodos set efectivo = efectivo + p_saldo_inicial where id = v_nodo.id;
  end if;

  return json_build_object(
    'numero_cuenta', v_cuenta.numero_cuenta,
    'titular', v_cuenta.titular,
    'saldo', v_cuenta.saldo,
    'estado', v_cuenta.estado
  );
end;
$$;

create function public.consultar_saldo(p_api_key text, p_numero_cuenta text) returns json
language plpgsql stable security definer set search_path = ''
as $$
declare
  v_nodo public.nodos := public.nodo_por_key(p_api_key);
  v_cuenta public.users_accounts;
begin
  select * into v_cuenta from public.users_accounts where numero_cuenta = p_numero_cuenta;
  if not found then
    raise exception 'La cuenta no existe' using errcode = 'PT404';
  end if;

  return json_build_object(
    'numero_cuenta', v_cuenta.numero_cuenta,
    'titular', v_cuenta.titular,
    'saldo', v_cuenta.saldo,
    'estado', v_cuenta.estado
  );
end;
$$;

create function public.retirar(p_api_key text, p_numero_cuenta text, p_monto numeric) returns json
language plpgsql security definer set search_path = ''
as $$
declare
  v_nodo public.nodos := public.nodo_por_key(p_api_key);
  v_cuenta public.users_accounts;
  v_tx public.transactions;
begin
  if p_monto is null or p_monto <= 0 then
    raise exception 'El monto debe ser mayor a 0' using errcode = 'PT400';
  end if;

  -- for update bloquea la cuenta y el nodo hasta terminar, así dos retiros
  -- al mismo tiempo no pueden dejar el saldo en negativo
  select * into v_cuenta from public.users_accounts
   where numero_cuenta = p_numero_cuenta for update;

  if not found then
    raise exception 'La cuenta no existe' using errcode = 'PT404';
  end if;
  if v_cuenta.estado <> 'activa' then
    raise exception 'La cuenta está bloqueada' using errcode = 'PT403';
  end if;
  if v_cuenta.saldo < p_monto then
    raise exception 'Fondos insuficientes' using errcode = 'PT422';
  end if;

  select * into v_nodo from public.nodos where id = v_nodo.id for update;
  if v_nodo.efectivo < p_monto then
    raise exception 'El nodo no tiene efectivo suficiente' using errcode = 'PT422';
  end if;

  update public.users_accounts set saldo = saldo - p_monto where id = v_cuenta.id;
  update public.nodos set efectivo = efectivo - p_monto where id = v_nodo.id;

  insert into public.transactions (cuenta_origen, monto, tipo, nodo_id)
  values (p_numero_cuenta, p_monto, 'retiro', v_nodo.id)
  returning * into v_tx;

  return json_build_object(
    'transaccion_id', v_tx.id,
    'numero_cuenta', p_numero_cuenta,
    'monto', p_monto,
    'saldo', v_cuenta.saldo - p_monto,
    'efectivo_nodo', v_nodo.efectivo - p_monto,
    'fecha', v_tx.created_at
  );
end;
$$;

create function public.depositar(p_api_key text, p_numero_cuenta text, p_monto numeric) returns json
language plpgsql security definer set search_path = ''
as $$
declare
  v_nodo public.nodos := public.nodo_por_key(p_api_key);
  v_cuenta public.users_accounts;
  v_tx public.transactions;
begin
  if p_monto is null or p_monto <= 0 then
    raise exception 'El monto debe ser mayor a 0' using errcode = 'PT400';
  end if;

  select * into v_cuenta from public.users_accounts
   where numero_cuenta = p_numero_cuenta for update;

  if not found then
    raise exception 'La cuenta no existe' using errcode = 'PT404';
  end if;
  if v_cuenta.estado <> 'activa' then
    raise exception 'La cuenta está bloqueada' using errcode = 'PT403';
  end if;

  update public.users_accounts set saldo = saldo + p_monto where id = v_cuenta.id;
  update public.nodos set efectivo = efectivo + p_monto where id = v_nodo.id
  returning efectivo into v_nodo.efectivo;

  insert into public.transactions (cuenta_destino, monto, tipo, nodo_id)
  values (p_numero_cuenta, p_monto, 'deposito', v_nodo.id)
  returning * into v_tx;

  return json_build_object(
    'transaccion_id', v_tx.id,
    'numero_cuenta', p_numero_cuenta,
    'monto', p_monto,
    'saldo', v_cuenta.saldo + p_monto,
    'efectivo_nodo', v_nodo.efectivo,
    'fecha', v_tx.created_at
  );
end;
$$;

-- Sin cuenta: movimientos hechos en el nodo. Con cuenta: movimientos de esa cuenta.
create function public.historial(p_api_key text, p_numero_cuenta text default null)
returns setof public.transactions
language plpgsql stable security definer set search_path = ''
as $$
declare
  v_nodo public.nodos := public.nodo_por_key(p_api_key);
begin
  if p_numero_cuenta is null then
    return query
      select * from public.transactions
       where nodo_id = v_nodo.id
       order by created_at desc;
  else
    return query
      select * from public.transactions
       where cuenta_origen = p_numero_cuenta or cuenta_destino = p_numero_cuenta
       order by created_at desc;
  end if;
end;
$$;

create function public.reporte_nodo(p_api_key text) returns json
language plpgsql stable security definer set search_path = ''
as $$
declare
  v_nodo public.nodos := public.nodo_por_key(p_api_key);
begin
  return json_build_object(
    'nodo', v_nodo.nombre,
    'efectivo', v_nodo.efectivo,
    'cuentas_abiertas', (select count(*) from public.users_accounts where nodo_id = v_nodo.id),
    'operaciones', (
      select coalesce(json_agg(r), '[]'::json)
        from (select tipo, count(*) as cantidad, sum(monto) as total
                from public.transactions
               where nodo_id = v_nodo.id
               group by tipo) r
    )
  );
end;
$$;

-- Permisos: los nodos (llave pública) solo pueden llamar a estas funciones.
revoke execute on all functions in schema public from public, anon, authenticated;

grant execute on function
  public.info_nodo(text),
  public.crear_cuenta(text, text, numeric),
  public.consultar_saldo(text, text),
  public.retirar(text, text, numeric),
  public.depositar(text, text, numeric),
  public.historial(text, text),
  public.reporte_nodo(text)
to anon, authenticated;

grant execute on function public.registrar_nodo(text, text, text, numeric) to service_role;
