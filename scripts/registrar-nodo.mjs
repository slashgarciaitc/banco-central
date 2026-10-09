// Registra una sucursal o un cajero desde la terminal (mientras no esté el panel de Laravel).
// Uso: npm run nodo -- cajero "Cajero Tec" 20000 "Responsable"
import { createClient } from '@supabase/supabase-js';

const [tipo, nombre, efectivo = 0, responsable = null] = process.argv.slice(2);

const admin = createClient(process.env.SUPABASE_URL, process.env.SUPABASE_SERVICE_ROLE_KEY);
const { data, error } = await admin.rpc('registrar_nodo', {
  p_tipo: tipo, p_nombre: nombre, p_efectivo: Number(efectivo), p_responsable: responsable,
});

if (error) {
  console.error(error.message);
  process.exit(1);
}
console.log(`Nodo ${data.id} registrado. API key:`);
console.log(data.api_key);
