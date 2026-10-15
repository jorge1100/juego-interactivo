<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BattleshipGame extends Model
{
    protected $table = 'battleship_games';

    protected $fillable = ['room', 'state'];

    protected $casts = ['state' => 'array'];

    /**
     * Estado inicial de una partida (el anfitrión empieza).
     *
     * @return array<string,mixed>
     */
    public static function blank(): array
    {
        return [
            'phase' => 'placement', // placement | playing | finished
            'turn' => 'host',
            'winner' => null,
            'players' => [
                'host' => ['ready' => false, 'ships' => [], 'shots' => []],
                'guest' => ['ready' => false, 'ships' => [], 'shots' => []],
            ],
        ];
    }

    /** Lee el estado de la sala (o el inicial si no existe). */
    public static function stateOf(string $room): array
    {
        return static::firstOrNew(['room' => $room])->state ?? static::blank();
    }

    /** @param  array<string,mixed>  $state */
    public static function saveState(string $room, array $state): void
    {
        static::firstOrNew(['room' => $room])->fill(['state' => $state])->save();
    }
}