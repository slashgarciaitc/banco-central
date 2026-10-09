// Crea un usuario de Supabase Auth (ejecutivos de sucursal o administradores).
// Uso: npm run usuario -- correo@banco.test contraseña
import { createClient } from '@supabase/supabase-js';

const [email, password] = process.argv.slice(2);

const admin = createClient(process.env.SUPABASE_URL, process.env.SUPABASE_SERVICE_ROLE_KEY);
const { data, error } = await admin.auth.admin.createUser({ email, password, email_confirm: true });

if (error) {
  console.error(error.message);
  process.exit(1);
}
console.log(`Usuario ${data.user.email} creado.`);
