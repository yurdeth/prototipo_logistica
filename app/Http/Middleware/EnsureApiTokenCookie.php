<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiTokenCookie
{
    /**
     * Nombre de la cookie donde la SPA lee el token JWT para la API.
     */
    public const COOKIE = 'api_token';

    /**
     * Duración de la cookie en minutos (30 días).
     */
    private const DURACION = 60 * 24 * 30;

    /**
     * Tras autenticar por sesión web, emite el token JWT de Passport y lo deja
     * en una cookie legible por la SPA (httpOnly=false) para que las peticiones
     * a routes/api.php lo envíen como Authorization: Bearer.
     * Sin sesión activa, la cookie se expira.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $respuesta = $next($request);
        $usuario = $request->user();

        if ($usuario === null) {
            return $respuesta->withCookie(Cookie::forget(self::COOKIE));
        }

        // Solo reutiliza la cookie si contiene un JWT de Passport (tres segmentos
        // base64url separados por punto); un valor cifrado o corrupto se re-emite.
        $cookieActual = $request->cookie(self::COOKIE);
        if (is_string($cookieActual) && $this->esJwtValido($cookieActual)) {
            return $respuesta;
        }

        try {
            $token = $usuario->createToken('spa')->accessToken;
        } catch (RuntimeException $e) {
            // Sin cliente personal de Passport (passport:client --personal) la
            // emisión falla; la página debe seguir renderizando sin cookie.
            Log::warning('No se pudo emitir el token de la SPA', ['error' => $e->getMessage()]);

            return $respuesta;
        }

        // cookie() usa argumentos posicionales: ($name, $value, $minutes, $path,
        // $domain, $secure, $httpOnly, $raw, $sameSite). httpOnly=false para que
        // la SPA pueda leer el token y enviarlo como Authorization: Bearer.
        $cookie = cookie(
            self::COOKIE,
            $token,
            self::DURACION,
            '/',
            null,
            config('app.env') === 'production',
            false,
            false,
            'lax',
        );

        return $respuesta->withCookie($cookie);
    }

    /**
     * Un access token de Passport es un JWT: header.payload.signature en base64url.
     */
    private function esJwtValido(string $valor): bool
    {
        if ($valor === '') {
            return false;
        }

        $segmentos = explode('.', $valor);

        return count($segmentos) === 3
            && ! str_contains($valor, '{')
            && collect($segmentos)->every(fn (string $s) => $s !== '' && preg_match('/^[A-Za-z0-9_-]+$/', $s) === 1);
    }
}
