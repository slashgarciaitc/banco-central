-- Permite a administración desactivar nodos sin borrar su historial ni revelar su API key.
alter table public.nodos
  add column if not exists estado text not null default 'activa'
  check (estado in ('activa', 'inactiva'));

create or replace function public.nodo_por_key(p_api_key text) returns public.nodos
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
  if v_nodo.estado <> 'activa' then
    raise exception 'El nodo está desactivado' using errcode = 'PT403';
  end if;

  return v_nodo;
end;
$$;