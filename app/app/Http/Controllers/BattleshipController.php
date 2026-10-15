<?php

namespace App\Http\Controllers;

use App\Events\BattleshipUpdate;
use App\Models\BattleshipGame;
use App\Services\HostSlot;
use Illuminate\Http\Request;

class BattleshipController extends Controller
{
    public const SIZE = 10;

    /** @var array<int,array{name:string,size:int}> */
    public const FLEET = [
        ['name' => 'Portaaviones', 'size' => 5],
        ['name' => 'Acorazado', 'size' => 4],
        ['name' => 'Crucero', 'size' => 3],
        ['name' => 'Crucero', 'size' => 3],
        ['name' => 'Submarino', 'size' => 3],
        ['name' => 'Destructor', 'size' => 2],
    ];

    /** Total de casillas de la flota, derivado de FLEET para que nunca se desincronice. */
    public static function totalCells(): int
    {
        return array_sum(array_column(self::FLEET, 'size'));
    }

    public function index(Request $request)
    {
        // Botones del menú: eligen explícitamente el rol que quieren.
        $wanted = in_array($request->query('rol'), ['host', 'guest'], true)
            ? $request->query('rol')
            : null;

        if (session()->missing('role_batalla')) {
            if ($wanted === 'guest' && ! HostSlot::isTaken('batalla')) {
                // Nadie es anfitrión todavía: si pedís ser invitado, sos el
                // primero y te toca fundar la partida.
                session(['role_batalla' => HostSlot::claim('batalla') ? 'host' : 'guest']);

                return redirect('/batalla-emisor')->with('info', 'No había ningún anfitrión, así que fundaste la partida.');
            }

            if ($wanted === 'host' && HostSlot::isTaken('batalla')) {
                return redirect('/batalla-receptor')->with(
                    'info',
                    'Ya hay un anfitrión jugando, así que entraste como invitado. Si querés tomar el control, usá el botón "👑 Tomar el lugar del anfitrión".'
                );
            }

            session(['role_batalla' => HostSlot::claim('batalla') ? 'host' : 'guest']);
        }

        return redirect(session('role_batalla') === 'host' ? '/batalla-emisor' : '/batalla-receptor');
    }

    public function emisor()
    {
        if ($this->currentRole() !== 'host') {
            return redirect('/batalla-receptor')->with(
                'info',
                'Ya hay un anfitrión jugando, así que entraste como invitado. Si querés tomar el control, usá el botón "👑 Tomar el lugar del anfitrión".'
            );
        }

        HostSlot::refresh('batalla');

        return view('battleship-emisor', ['fleet' => self::FLEET]);
    }

    public function receptor()
    {
        if ($this->currentRole() === 'host') {
            return redirect('/batalla-emisor')->with('info', 'Sos el anfitrión.');
        }

        return view('battleship-receptor', ['fleet' => self::FLEET]);
    }

    /**
     * Rol actual: 'host', 'guest' o null si todavía no se asignó.
     *
     * IMPORTANTE: un invitado NO se autopromueve en cada request (heartbeat
     * incluido). Antes lo hacía, y eso hacía que cualquier pestaña vieja
     * robara el lugar de anfitrión y el rol "flapeara" entre ventanas sin que
     * la interfaz se enterara. Ascender a anfitrión es una acción explícita:
     * el botón "tomar el lugar" o entrar por /batalla con el sitio libre.
     */
    private function currentRole(): ?string
    {
        $role = session('role_batalla');

        if ($role === 'host') {
            if (HostSlot::isMine('batalla')) {
                HostSlot::refresh('batalla');

                return 'host';
            }

            // Perdió el lugar (otro lo tomó o venció el lock): pasa a invitado.
            session(['role_batalla' => 'guest']);

            return 'guest';
        }

        return $role ?? 'guest';
    }

    /**
     * El anfitrión cerró la pestaña. Se llama con `navigator.sendBeacon`, que
     * no puede mandar el header de CSRF, por eso va sin ese middleware.
 */
    public function releaseHost(): \Illuminate\Http\JsonResponse
    {
        if (($this->currentRole() === 'host') && HostSlot::isMine('batalla')) {
            HostSlot::release('batalla');
            broadcast(new BattleshipUpdate('host-left', []));
        }

        return response()->json(['ok' => true]);
    }

    public function state()
    {
        return response()->json([
            'state' => $this->viewFor($this->currentRole() === 'guest' ? 'guest' : 'host'),
        ]);
    }

    public function emitir(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:place,ready,start,shot,reset,message,heartbeat,release-host,claim-host,surrender,finish'],
            'player' => ['nullable', 'in:host,guest'],
            'ships' => ['nullable', 'array'],
            'ships.*.r' => ['integer', 'min:0', 'max:9'],
            'ships.*.c' => ['integer', 'min:0', 'max:9'],
            'ships.*.horizontal' => ['boolean'],
            'ships.*.size' => ['integer', 'min:2', 'max:5'],
            'r' => ['nullable', 'integer', 'min:0', 'max:9'],
            'c' => ['nullable', 'integer', 'min:0', 'max:9'],
            'message' => ['nullable', 'string', 'max:280'],
        ]);

        $me = $this->currentRole() === 'guest' ? 'guest' : 'host';
        $type = $data['type'];
        $room = 'default';

        // Heartbeat y mensajes no tocan el estado de la partida.
        if ($type === 'heartbeat') {
            if ($me === 'host') {
                HostSlot::refresh('batalla');

                return response()->json(['ok' => true, 'promoted' => false]);
            }

            // El anfitrión lleva más de 1 minuto sin señal: el invitado vivo
            // toma su lugar solo. `claim()` es atómico, así que si hay dos
            // invitados conectados, solo uno se vuelve anfitrión.
            if (! HostSlot::isTaken('batalla') && HostSlot::claim('batalla')) {
                session(['role_batalla' => 'host']);
                broadcast(new BattleshipUpdate('host-changed', ['player' => 'guest']));

                return response()->json(['ok' => true, 'promoted' => true]);
            }

            return response()->json(['ok' => true, 'promoted' => false]);
        }

        if ($type === 'message') {
            broadcast(new BattleshipUpdate('message', ['message' => $data['message'] ?? '']));

            return response()->json(['ok' => true]);
        }

        // El anfitrión cierra la pestaña: libera el lugar para que otro lo tome.
        if ($type === 'release-host') {
            if ($me === 'host') {
                HostSlot::release('batalla');
                broadcast(new BattleshipUpdate('host-left', []));
            }

            return response()->json(['ok' => true]);
        }

        $state = BattleshipGame::stateOf($room);

        if ($type === 'reset') {
            $state = BattleshipGame::blank();
            BattleshipGame::saveState($room, $state);
            $this->broadcastState($state, 'Partida reiniciada.');

            return response()->json(['ok' => true]);
        }

        if ($type === 'surrender') {
            // Rendirse: gana el rival.
            if ($state['phase'] !== 'playing') {
                return response()->json(['error' => 'La partida no está en curso'], 422);
            }

            $state['phase'] = 'finished';
            $state['winner'] = $me === 'host' ? 'guest' : 'host';
            BattleshipGame::saveState($room, $state);
            $this->broadcastState($state, $this->roleName($me) . ' se rindió. Gana ' . $this->roleName($state['winner']) . '.');

            return response()->json(['ok' => true]);
        }

        if ($type === 'finish') {
            // Terminar ahora: gana quien más casillas haya hundido.
            if ($state['phase'] === 'finished') {
                return response()->json(['error' => 'La partida ya terminó'], 422);
            }

            $hostHits = $this->hits($state, 'guest'); // disparos del anfitrión sobre el invitado
            $guestHits = $this->hits($state, 'host');

            $state['phase'] = 'finished';
            $state['winner'] = $hostHits === $guestHits ? null : ($hostHits > $guestHits ? 'host' : 'guest');
            $state['turn'] = null;
            BattleshipGame::saveState($room, $state);

            $msg = $state['winner'] === null
                ? 'Partida terminada: empate ' . $hostHits . '-' . $guestHits . '.'
                : 'Partida terminada. Gana ' . $this->roleName($state['winner']) . ' (' . ($state['winner'] === 'host' ? $hostHits : $guestHits) . ' impacto' . (($state['winner'] === 'host' ? $hostHits : $guestHits) === 1 ? '' : 's') . ').';

            $this->broadcastState($state, $msg);

            return response()->json(['ok' => true, 'winner' => $state['winner']]);
        }

        if ($type === 'claim-host') {
            // Tomar el lugar de anfitrión y dejar la partida lista para reiniciar.
            if (! HostSlot::claim('batalla')) {
                return response()->json(['error' => 'Ya hay un anfitrión conectado.'], 422);
            }

            session(['role_batalla' => 'host']);

            $state = BattleshipGame::blank();
            BattleshipGame::saveState($room, $state);
            $this->broadcastState($state, 'El invitado tomó el lugar de anfitrión. Vuelvan a colocar.');

            return response()->json(['ok' => true]);
        }

        if ($type === 'place') {
            $ships = $this->validateFleet($data['ships'] ?? []);
            if ($ships === null) {
                return response()->json(['error' => 'Flota inválida'], 422);
            }

            $state['players'][$me]['ships'] = $ships;
            $state['players'][$me]['ready'] = false;
            BattleshipGame::saveState($room, $state);
            $this->broadcastState($state, 'El jugador ' . $this->roleName($me) . ' colocó su flota.');

            return response()->json(['ok' => true]);
        }

        if ($type === 'ready') {
            if (count($state['players'][$me]['ships']) !== count(self::FLEET)) {
                return response()->json(['error' => 'Faltan barcos'], 422);
            }

            $state['players'][$me]['ready'] = true;
            BattleshipGame::saveState($room, $state);
            $this->broadcastState($state, $this->roleName($me) . ' está listo.');

            return response()->json(['ok' => true]);
        }

        if ($type === 'start') {
            if (! ($state['players']['host']['ready'] && $state['players']['guest']['ready'])) {
                return response()->json(['error' => 'Faltan listos'], 422);
            }

            $state['phase'] = 'playing';
            $state['turn'] = 'host';
            $state['winner'] = null;
            BattleshipGame::saveState($room, $state);
            $this->broadcastState($state, '¡Partida iniciada! Dispara el anfitrión.');

            return response()->json(['ok' => true]);
        }

        if ($type === 'shot') {
            $r = (int) $data['r'];
            $c = (int) $data['c'];

            if ($state['phase'] !== 'playing') {
                return response()->json(['error' => 'La partida no empezó'], 422);
            }

            if ($state['turn'] !== $me) {
                return response()->json(['error' => 'No es tu turno'], 422);
            }

            $enemy = $me === 'host' ? 'guest' : 'host';
            $key = "$r,$c";

            if (isset($state['players'][$enemy]['shots'][$key])) {
                return response()->json(['error' => 'Ya disparaste ahí'], 422);
            }

            // El SERVIDOR resuelve el impacto contra la flota del defensor.
            $hitShip = $this->shipAt($state['players'][$enemy]['ships'], $r, $c);
            $state['players'][$enemy]['shots'][$key] = $hitShip ? 'hit' : 'miss';

            // ¿Ese barco quedó hundido?
            $sunk = false;
            if ($hitShip) {
                $cells = $this->shipCells($hitShip);
                $allHit = true;

                foreach ($cells as $cell) {
                    if (($state['players'][$enemy]['shots'][$cell[0] . ',' . $cell[1]] ?? null) !== 'hit') {
                        $allHit = false;
                        break;
                    }
                }

                if ($allHit) {
                    $sunk = true;
                    foreach ($cells as $cell) {
                        $state['players'][$enemy]['shots'][$cell[0] . ',' . $cell[1]] = 'sunk';
                    }
                }
            }

            $enemyHits = count(array_filter(
                $state['players'][$enemy]['shots'],
                fn ($v) => in_array($v, ['hit', 'sunk'], true)
            ));

            $won = $enemyHits >= self::totalCells();
            $message = $won
                ? '¡' . $this->roleName($me) . ' hundió toda la flota enemiga! 🏆'
                : ($sunk ? '💥 ¡Barco hundido!' : ($hitShip ? '💥 ¡Impacto! Repetís turno.' : '💨 Solo agua. Pasa el turno.'));

            if ($won) {
                $state['phase'] = 'finished';
                $state['winner'] = $me;
            } elseif (! $hitShip) {
                $state['turn'] = $enemy; // el agua pasa el turno
            }

            BattleshipGame::saveState($room, $state);
            $this->broadcastState($state, $message);

            return response()->json(['ok' => true, 'hit' => (bool) $hitShip, 'sunk' => $sunk, 'won' => $won]);
        }

        return response()->json(['error' => 'Acción desconocida'], 422);
    }

    private function broadcastState(array $state, string $message): void
    {
        broadcast(new BattleshipUpdate('state', [
            'views' => [
                'host' => $this->viewFor('host', $state),
                'guest' => $this->viewFor('guest', $state),
            ],
            'message' => $message,
        ]));
    }

    /**
     * Vista pública del estado para un jugador: nunca incluye la flota del rival
     * mientras la partida está en curso.
     *
     * @param  array<string,mixed>  $state
     * @return array<string,mixed>
     */
    private function viewFor(string $role, ?array $state = null): array
    {
        $state ??= BattleshipGame::stateOf('default');
        $enemy = $role === 'host' ? 'guest' : 'host';
        $finished = $state['phase'] === 'finished';

        return [
            'phase' => $state['phase'],
            'turn' => $state['turn'],
            'winner' => $state['winner'],
            'me' => $role,
            'myShips' => $state['players'][$role]['ships'],
            'myReady' => $state['players'][$role]['ready'],
            'enemyReady' => $state['players'][$enemy]['ready'],
            'myShots' => $state['players'][$enemy]['shots'],   // disparos que yo hice al rival
            'enemyShots' => $state['players'][$role]['shots'], // disparos que me hicieron
            'fleet' => $finished ? $state['players'][$enemy]['ships'] : [], // rival oculto hasta el final
            // Progreso, para poder mostrar "¿cuánto falta para ganar?"
            'myHits' => $this->hits($state, $enemy),
            'enemyHits' => $this->hits($state, $role),
            'totalCells' => self::totalCells(),
            // ¿Hay un anfitrión conectado? Si no, el invitado puede tomar su lugar.
            'hasHost' => HostSlot::isTaken('batalla'),
        ];
    }

    private function roleName(string $role): string
    {
        return $role === 'host' ? 'El anfitrión' : 'El invitado';
    }

    /** Cuántas casillas hundió el jugador indicado sobre el rival. */
    private function hits(array $state, string $defender): int
    {
        return count(array_filter(
            $state['players'][$defender]['shots'],
            fn ($v) => in_array($v, ['hit', 'sunk'], true)
        ));
    }

    /**
     * @param  array<int,array<string,mixed>>|null  $ships
     * @return array<int,array<string,mixed>>|null
     */
    private function validateFleet(?array $ships): ?array
    {
        if (! is_array($ships) || count($ships) !== count(self::FLEET)) {
            return null;
        }

        $used = [];
        foreach ($ships as $i => $ship) {
            if (($ship['size'] ?? null) !== self::FLEET[$i]['size']) {
                return null;
            }

            foreach ($this->cellsOf((int) $ship['r'], (int) $ship['c'], (int) $ship['size'], (bool) ($ship['horizontal'] ?? true)) as $cell) {
                if ($cell[0] < 0 || $cell[0] >= self::SIZE || $cell[1] < 0 || $cell[1] >= self::SIZE) {
                    return null;
                }
                if (isset($used["{$cell[0]},{$cell[1]}"])) {
                    return null; // solapamiento
                }
                $used["{$cell[0]},{$cell[1]}"] = true;
            }
        }

        return $ships;
    }

    /**
     * @param  array<int,array<string,mixed>>  $ships
     * @return array<string,mixed>|null
     */
    private function shipAt(array $ships, int $r, int $c): ?array
    {
        foreach ($ships as $i => $ship) {
            $cells = $this->cellsOf(
                (int) $ship['r'],
                (int) $ship['c'],
                (int) $ship['size'],
                (bool) ($ship['horizontal'] ?? true)
            );

            foreach ($cells as $cell) {
                if ($cell[0] === $r && $cell[1] === $c) {
                    return [
                        'index' => $i,
                        'name' => self::FLEET[$i]['name'],
                        'size' => (int) $ship['size'],
                        'r' => (int) $ship['r'],
                        'c' => (int) $ship['c'],
                        'horizontal' => (bool) ($ship['horizontal'] ?? true),
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string,mixed>  $ship
     * @return array<int,array{int,int}>
     */
    private function shipCells(array $ship): array
    {
        return $this->cellsOf(
            (int) $ship['r'],
            (int) $ship['c'],
            (int) $ship['size'],
            (bool) ($ship['horizontal'] ?? true)
        );
    }

    /** @return array<int,array{int,int}> */
    private function cellsOf(int $r, int $c, int $size, bool $horizontal): array
    {
        $cells = [];
        for ($i = 0; $i < $size; $i++) {
            $cells[] = $horizontal ? [$r, $c + $i] : [$r + $i, $c];
        }

        return $cells;
    }
}