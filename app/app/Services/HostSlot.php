<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Asigna el rol de anfitrión/invitado de forma compartida entre navegadores.
 *
 * La cache del proyecto es `database`, o sea que es compartida por todas las
 * sesiones: a diferencia de `session()`, sí sirve para saber si YA EXISTE
 * un anfitrión en otra pestaña/navegador.
 *
 * El TTL es corto (45 s) y el anfitrión lo renueva con un heartbeat cada 5 s.
 * Así, si una pestaña se cierra a la fuerza, se cuelga, se apaga la compu o
 * se cae la red, el lugar queda libre solo a los 45 segundos en vez de tener
 * que esperar el TTL largo.
 */
class HostSlot
{
    public const TTL_MINUTES = 1;

    /**
     * Segundos que el anfitrión puede estar desconectado antes de que el
     * lugar quede libre y el invitado lo tome automáticamente.
     * Con el latido cada 5 s, 60 s equivale a "1 minuto sin señal".
     */
    public const TTL_SECONDS = 60;

    /** Identificador único de esta pestaña, para saber si el lock es mío. */
    public static function token(): string
    {
        if (! session()->has('bs_token')) {
            session(['bs_token' => (string) Str::uuid()]);
        }

        return session('bs_token');
    }

    private static function key(string $game): string
    {
        return $game . ':host_lock';
    }

    /** @return array{token:string,expires_at:int}|null */
    private static function read(string $game): ?array
    {
        $value = Cache::get(self::key($game));

        return is_array($value) && isset($value['token'], $value['expires_at']) ? $value : null;
    }

    /** ¿El lock existe y sigue vigente? */
    public static function isTaken(string $game): bool
    {
        $lock = self::read($game);

        return $lock !== null && $lock['expires_at'] > now()->getTimestamp();
    }

    /** ¿El lock actual me pertenece a mí? */
    public static function isMine(string $game): bool
    {
        $lock = self::read($game);

        return $lock !== null && $lock['token'] === self::token();
    }

    /** Intenta tomar el lugar de anfitrión. True si lo consiguió (o ya era mío). */
    public static function claim(string $game): bool
    {
        if (self::isMine($game)) {
            self::refresh($game);

            return true;
        }

        if (self::isTaken($game)) {
            return false;
        }

        // El lock está vencido. Ojo: el cache de base de datos NO borra las
        // filas vencidas, y `add()` usa `insertOrIgnore`, así que sin borrar
        // primero la fila vieja el insert se ignoraría y NUNCA se podría
        // volver a tomar el lugar de anfitrión.
        $key = self::key($game);
        Cache::forget($key);

        return Cache::add($key, self::payload(), now()->addSeconds(self::TTL_SECONDS));
    }

    /** Renueva el lock (lo llama el anfitrión en cada heartbeat). */
    public static function refresh(string $game): void
    {
        if (self::isMine($game)) {
            Cache::put(self::key($game), self::payload(), now()->addSeconds(self::TTL_SECONDS));
        }
    }

    /** Suelta el lugar de anfitrión para que otro pueda tomarlo. */
    public static function release(string $game): void
    {
        if (self::isMine($game)) {
            Cache::forget(self::key($game));
        }
    }

    /** @return array{token:string,expires_at:int} */
    private static function payload(): array
    {
        return [
            'token' => self::token(),
            'expires_at' => now()->addSeconds(self::TTL_SECONDS)->getTimestamp(),
        ];
    }
}