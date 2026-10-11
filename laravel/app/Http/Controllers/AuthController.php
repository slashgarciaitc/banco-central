<?php

namespace App\Http\Controllers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function create(Request $request)
    {
        if ($request->session()->has('central_admin.email')) {
            return redirect()->route('admin.dashboard');
        }

        return view('auth.login');
    }

    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $email = Str::lower($credentials['email']);
        $allowedEmails = config('central.admin_emails', []);

        if (! in_array($email, $allowedEmails, true)) {
            return back()->withErrors(['email' => 'Credenciales inválidas o usuario no autorizado.'])->onlyInput('email');
        }

        $url = (string) config('central.supabase_url');
        $anonKey = (string) config('central.anon_key');

        if ($url === '' || $anonKey === '') {
            return back()->withErrors(['email' => 'Falta configurar la conexión de autenticación de Supabase.'])->onlyInput('email');
        }

        try {
            $response = Http::acceptJson()
                ->withHeaders(['apikey' => $anonKey])
                ->timeout(10)
                ->post("{$url}/auth/v1/token?grant_type=password", [
                    'email' => $email,
                    'password' => $credentials['password'],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('No se pudo conectar con Supabase Auth.', ['exception' => $exception->getMessage()]);

            return back()->withErrors(['email' => 'No fue posible conectar con el servicio de autenticación.'])->onlyInput('email');
        }

        if ($response->failed() || Str::lower((string) $response->json('user.email')) !== $email) {
            return back()->withErrors(['email' => 'Credenciales inválidas o usuario no autorizado.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $request->session()->put('central_admin', [
            'id' => $response->json('user.id'),
            'email' => $email,
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request)
    {
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
