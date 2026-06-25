<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class UseSanctumTokenCookie
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken() ?: $this->cookieToken($request);

        if (! is_string($token) || $token === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $accessToken = PersonalAccessToken::findToken(urldecode($token));
        $user = $accessToken?->tokenable;

        if (! $accessToken || ! $user instanceof Authenticatable) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($user instanceof User && $user->status !== 'active') {
            $accessToken->delete();

            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if ($accessToken->expires_at && $accessToken->expires_at->isPast()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (method_exists($user, 'withAccessToken')) {
            $user->withAccessToken($accessToken);
        }

        Auth::guard('sanctum')->setUser($user);
        Auth::setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    private function cookieToken(Request $request): ?string
    {
        $token = $request->cookie('konverpro_token');
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $rawCookie = $request->headers->get('Cookie');
        if (! is_string($rawCookie) || $rawCookie === '') {
            return null;
        }

        $cookies = [];
        parse_str(str_replace('; ', '&', $rawCookie), $cookies);

        return isset($cookies['konverpro_token']) && is_string($cookies['konverpro_token'])
            ? $cookies['konverpro_token']
            : null;
    }
}
