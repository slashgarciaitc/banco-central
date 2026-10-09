// Prueba los pasos de la demo del examen contra Supabase.
// Uso: npm run prueba
import { createClient } from '@supabase/supabase-js';

const { SUPABASE_URL, SUPABASE_ANON_KEY, SUPABASE_SERVICE_ROLE_KEY } = process.env;
const admin = createClient(SUPABASE_URL, SUPABASE_SERVICE_ROLE_KEY); // Banco Central
const nodo = createClient(SUPABASE_URL, SUPABASE_ANON_KEY);          // sucursales y cajeros

let fallas = 0;
function revisar(nombre, ok, detalle = '') {
  if (!ok) fallas++;
  console.log(`${ok ? 'OK   ' : 'FALLA'} ${nombre} ${detalle}`);
}

// Paso 1: el Banco Central crea una sucursal y un cajero con su efectivo
const suc = await admin.rpc('registrar_nodo', {
  p_tipo: 'sucursal', p_nombre: 'Sucursal Centro', p_responsable: 'Ejecutivo', p_efectivo: 50000,
});
const atm = await admin.rpc('registrar_nodo', {
  p_tipo: 'cajero', p_nombre: 'Cajero Tec', p_responsable: 'Operador', p_efectivo: 10000,
});
revisar('Paso 1: registrar sucursal y cajero', !suc.error && !atm.error);
const keySucursal = suc.data.api_key;
const keyCajero = atm.data.api_key;

// Paso 2: la sucursal registra un cliente con $1,000
const alta = await nodo.rpc('crear_cuenta', {
  p_api_key: keySucursal, p_titular: 'Cliente Demo', p_saldo_inicial: 1000,
});
revisar('Paso 2: crear cuenta con $1,000', alta.data?.saldo === 1000, alta.data?.numero_cuenta);
const cuenta = alta.data.numero_cuenta;

// Paso 3: el Banco Central ve la cuenta
const { data: fila } = await admin.from('users_accounts').select('*').eq('numero_cuenta', cuenta).single();
revisar('Paso 3: la cuenta existe con saldo 1000', Number(fila.saldo) === 1000);

// Paso 4: el cajero retira $300
const retiro = await nodo.rpc('retirar', { p_api_key: keyCajero, p_numero_cuenta: cuenta, p_monto: 300 });
revisar('Paso 4: retirar $300', retiro.data?.saldo === 700, retiro.error?.message);

// Paso 5: saldo en 700 y transacción registrada sin poder modificarse
const { data: despues } = await admin.from('users_accounts').select('saldo').eq('numero_cuenta', cuenta).single();
revisar('Paso 5: saldo central en 700', Number(despues.saldo) === 700);

const { data: txs } = await admin.from('transactions').select('*').eq('cuenta_origen', cuenta);
revisar('Paso 5: retiro registrado', txs.length === 1 && Number(txs[0].monto) === 300);

const editar = await admin.from('transactions').update({ monto: 1 }).eq('id', txs[0].id);
revisar('Paso 5: no se puede editar la transacción', editar.error !== null, editar.error?.message);
const borrar = await admin.from('transactions').delete().eq('id', txs[0].id);
revisar('Paso 5: no se puede borrar la transacción', borrar.error !== null, borrar.error?.message);

// Seguridad y errores
const lectura = await nodo.from('users_accounts').select('*');
revisar('La llave pública no lee las tablas', lectura.error !== null, lectura.error?.message);

const keyFalsa = await nodo.rpc('retirar', { p_api_key: 'bk_falsa', p_numero_cuenta: cuenta, p_monto: 10 });
revisar('API key falsa: 401', keyFalsa.status === 401);

const cajeroAbre = await nodo.rpc('crear_cuenta', { p_api_key: keyCajero, p_titular: 'X' });
revisar('Un cajero no puede abrir cuentas: 403', cajeroAbre.status === 403);

const sinFondos = await nodo.rpc('retirar', { p_api_key: keyCajero, p_numero_cuenta: cuenta, p_monto: 5000 });
revisar('Fondos insuficientes: 422', sinFondos.status === 422);

const deposito = await nodo.rpc('depositar', { p_api_key: keyCajero, p_numero_cuenta: cuenta, p_monto: 50 });
revisar('Depósito de $50: saldo 750', deposito.data?.saldo === 750);

// Dos retiros de $600 al mismo tiempo sobre $1,000: solo debe pasar uno
const otra = await nodo.rpc('crear_cuenta', { p_api_key: keySucursal, p_titular: 'Cliente 2', p_saldo_inicial: 1000 });
const resultados = await Promise.all([1, 2].map(() =>
  nodo.rpc('retirar', { p_api_key: keyCajero, p_numero_cuenta: otra.data.numero_cuenta, p_monto: 600 })));
revisar('Retiros simultáneos: solo pasa uno', resultados.filter((r) => !r.error).length === 1);

const reporte = await nodo.rpc('reporte_nodo', { p_api_key: keySucursal });
revisar('Reporte de la sucursal', reporte.data?.cuentas_abiertas === 2);

console.log(fallas === 0 ? '\nTodo bien' : `\n${fallas} pruebas fallaron`);
process.exit(fallas === 0 ? 0 : 1);
