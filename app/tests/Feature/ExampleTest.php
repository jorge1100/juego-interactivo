<?php

namespace Tests\Feature;

use App\Models\BattleshipGame;
use App\Services\HostSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,array<string,mixed>> */
    private function fleet(int $offset = 0): array
    {
        $sizes = [5, 4, 3, 3, 3, 2];
        $ships = [];
        $r = 0;

        foreach ($sizes as $size) {
            $ships[] = [
                'r' => $r,
                'c' => $offset,
                'horizontal' => true,
                'size' => $size,
            ];
            $r += 1;
        }

        return $ships;
    }

    private function asHost(): self
    {
        // El anfitrión además tiene que tener tomado el "lugar" compartido.
        Cache::put(
            'batalla:host_lock',
            ['token' => 'token-host', 'expires_at' => now()->addMinutes(1)->getTimestamp()],
            now()->addMinutes(1)
        );

        return $this->withSession(['role_batalla' => 'host', 'bs_token' => 'token-host']);
    }

    private function asGuest(): self
    {
        return $this->withSession(['role_batalla' => 'guest', 'bs_token' => 'token-guest']);
    }

    // ---------------- Menú y páginas ----------------

    public function test_root_shows_menu(): void
    {
        $this->get('/')->assertStatus(200)->assertSee('Trivia Relámpago');
        $this->get('/emisor')->assertStatus(200)->assertSee('Trivia Relámpago');
        $this->get('/receptor')->assertStatus(200)->assertSee('Batalla Naval');
    }

    public function test_trivia_pages_return_successful_response(): void
    {
        // El primero que entra es anfitrión -> ve el control del juego.
        $this->get('/juego-emisor')->assertStatus(200)->assertSee('ANFITRIÓN');

        // Y si ya hay anfitrión, el segundo es invitado -> ve la pantalla.
        $fresh = $this->flushSession();
        Cache::put('juego:host_lock', ['token' => 'otro', 'expires_at' => now()->addMinutes(10)->getTimestamp()], now()->addMinutes(10));
        $fresh->get('/juego-receptor')->assertStatus(200)->assertSee('INVITADO');
    }

    // ---------------- Batalla Naval: roles ----------------

    public function test_first_visitor_becomes_host_and_second_becomes_guest(): void
    {
        Cache::flush();

        // Primero: nadie tiene el lugar -> anfitrión
        $this->get('/batalla')->assertRedirect('/batalla-emisor');
        $this->assertSame('host', session('role_batalla'));

        // Segundo navegador distinto: el lugar está ocupado -> invitado
        $fresh = $this->flushSession();
        $fresh->get('/batalla')->assertRedirect('/batalla-receptor');
        $this->assertSame('guest', session('role_batalla'));
    }

    public function test_expired_host_slot_can_be_claimed_again(): void
    {
        Cache::flush();

        // Un anfitrión toma el lugar...
        $this->assertTrue(HostSlot::claim('batalla'));

        // ...y su pestaña muere: el lock queda vencido (fila vieja en la tabla).
        Cache::put(
            'batalla:host_lock',
            ['token' => 'muerto', 'expires_at' => now()->subMinute()->getTimestamp()],
            now()->addMinutes(5)
        );

        // A pesar de que la fila sigue existiendo, otro jugador debe poder tomarlo.
        $this->assertFalse(HostSlot::isTaken('batalla'));

        session(['bs_token' => 'nuevo-host']);
        $this->assertTrue(HostSlot::claim('batalla'), 'Un lock vencido tiene que poder reclamarse.');
        $this->assertTrue(HostSlot::isMine('batalla'));
    }

    public function test_guest_is_redirected_away_from_emisor(): void
    {
        Cache::flush();
        Cache::put('batalla:host_lock', ['token' => 'otro', 'expires_at' => now()->addMinutes(10)->getTimestamp()], now()->addMinutes(10));

        $this->asGuest()->get('/batalla-emisor')->assertRedirect('/batalla-receptor');
    }

    public function test_host_sees_the_emisor(): void
    {
        Cache::flush();
        $this->asHost()->get('/batalla-emisor')->assertStatus(200)->assertSee('ANFITRIÓN');
    }

    public function test_guest_sees_the_receptor(): void
    {
        Cache::flush();
        Cache::put('batalla:host_lock', ['token' => 'otro', 'expires_at' => now()->addMinutes(10)->getTimestamp()], now()->addMinutes(10));

        $this->asGuest()->get('/batalla-receptor')->assertStatus(200)->assertSee('INVITADO');
    }

    // ---------------- Batalla Naval: partida ----------------

    public function test_full_match_flow_with_hit_miss_turn_change_and_win(): void
    {
        Cache::flush();

        // Ambos colocan la misma flota (para que los disparos sean verificables)
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'start'])->assertOk();

        $state = $this->asHost()->getJson('/batalla-estado')->json('state');
        $this->assertSame('playing', $state['phase']);
        $this->assertSame('host', $state['turn']);

        // El invitado no puede disparar fuera de turno
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 0, 'c' => 0])
            ->assertStatus(422);

        // Impacto: el servidor lo resuelve y el turno NO cambia
        $hit = $this->asHost()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 0, 'c' => 0])->json();
        $this->assertTrue($hit['hit']);
        $this->assertFalse($hit['won']);
        $this->assertSame('host', BattleshipGame::stateOf('default')['turn']);

        // Agua: cambia el turno al invitado
        $miss = $this->asHost()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 9, 'c' => 9])->json();
        $this->assertFalse($miss['hit']);
        $this->assertSame('guest', BattleshipGame::stateOf('default')['turn']);

        // El invitado dispara al agua en la misma casilla (legal para él: es el
        // tablero del anfitrión) y el turno vuelve al anfitrión.
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 9, 'c' => 9])
            ->assertOk();
        $this->assertSame('host', BattleshipGame::stateOf('default')['turn']);

        // El anfitrión ya había disparado en J10: no puede repetir casilla.
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 9, 'c' => 9])
            ->assertStatus(422);
    }

    public function test_sinking_a_ship_marks_all_its_cells(): void
    {
        Cache::flush();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'start'])->assertOk();

        // El anfitrión pasa turno con un disparo al agua
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 9, 'c' => 9])->assertOk();

        // El invitado hunde el Destructor (fila 5, cols 0-1)
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 5, 'c' => 0])->assertOk();
        $last = $this->asGuest()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 5, 'c' => 1])->json();

        $this->assertTrue($last['sunk']);

        $shots = BattleshipGame::stateOf('default')['players']['host']['shots'];
        $this->assertSame('sunk', $shots['5,0']);
        $this->assertSame('sunk', $shots['5,1']);
    }

    public function test_fleet_totals_twenty_cells(): void
    {
        // 5+4+3+3+3+2 = 20 casillas: la victoria no puede declararse antes.
        $this->assertSame(20, \App\Http\Controllers\BattleshipController::totalCells());
        $this->assertCount(6, $this->fleet());
    }

    public function test_winning_reveals_enemy_fleet(): void
    {
        Cache::flush();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'start'])->assertOk();

        // El invitado pasa a turno disparando al agua
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 9, 'c' => 9])->assertOk();

        // Hundir las 17 casillas de la flota del anfitrión
        $won = false;
        foreach ($this->fleet() as $ship) {
            for ($i = 0; $i < $ship['size']; $i++) {
                $res = $this->asGuest()->postJson('/batalla-emitir', [
                    'type' => 'shot', 'r' => $ship['r'], 'c' => $ship['c'] + $i,
                ])->json();
                $won = $won || $res['won'];
            }
        }

        $this->assertTrue($won, 'El invitado debería ganar tras hundir las 17 casillas.');

        $state = BattleshipGame::stateOf('default');
        $this->assertSame('finished', $state['phase']);
        $this->assertSame('guest', $state['winner']);

        // Con la partida terminada, la flota rival se revela
        $view = $this->asGuest()->getJson('/batalla-estado')->json('state');
        $this->assertCount(6, $view['fleet']);
    }

    public function test_enemy_fleet_is_hidden_while_playing(): void
    {
        Cache::flush();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'start'])->assertOk();

        $this->assertSame([], $this->asGuest()->getJson('/batalla-estado')->json('state.fleet'));
    }

    public function test_invalid_fleet_is_rejected(): void
    {
        Cache::flush();

        // Flota incompleta
        $this->asHost()->postJson('/batalla-emitir', [
            'type' => 'place',
            'ships' => [['r' => 0, 'c' => 0, 'horizontal' => true, 'size' => 5]],
        ])->assertStatus(422);

        // Barcos superpuestos
        $this->asHost()->postJson('/batalla-emitir', [
            'type' => 'place',
            'ships' => [
                ['r' => 0, 'c' => 0, 'horizontal' => true, 'size' => 5],
                ['r' => 0, 'c' => 2, 'horizontal' => true, 'size' => 4],
                ['r' => 2, 'c' => 0, 'horizontal' => true, 'size' => 3],
                ['r' => 3, 'c' => 0, 'horizontal' => true, 'size' => 3],
                ['r' => 4, 'c' => 0, 'horizontal' => true, 'size' => 3],
                ['r' => 5, 'c' => 0, 'horizontal' => true, 'size' => 2],
            ],
        ])->assertStatus(422);
    }

    public function test_cannot_start_before_both_are_ready(): void
    {
        Cache::flush();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'start'])->assertStatus(422);
    }

    public function test_reset_clears_the_board(): void
    {
        Cache::flush();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'reset'])->assertOk();

        $state = $this->asHost()->getJson('/batalla-estado')->json('state');
        $this->assertSame('placement', $state['phase']);
        $this->assertSame([], $state['myShips']);
    }

    // ---------------- Trivia ----------------

    public function test_trivia_emitir_validates_and_broadcasts(): void
    {
        $this->post('/juego-emitir', ['type' => 'question', 'index' => 0, 'seconds' => 15])
            ->assertRedirect()->assertSessionHas('ok');

        $this->post('/juego-emitir', ['type' => 'reveal', 'index' => 0])
            ->assertRedirect()->assertSessionHas('ok');

        $this->post('/juego-emitir', ['type' => 'invalido'])->assertSessionHasErrors('type');
    }

    public function test_surrender_gives_the_win_to_the_opponent(): void
    {
        Cache::flush();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'start'])->assertOk();

        // El invitado se rinde -> gana el anfitrión
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'surrender'])->assertOk();

        $state = BattleshipGame::stateOf('default');
        $this->assertSame('finished', $state['phase']);
        $this->assertSame('host', $state['winner']);
    }

    public function test_finish_declares_the_player_with_more_hits(): void
    {
        Cache::flush();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'start'])->assertOk();

        // El anfitrión le pega dos veces a la flota del invitado
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 0, 'c' => 0])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 0, 'c' => 1])->assertOk();

        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'finish'])->assertOk();

        $state = BattleshipGame::stateOf('default');
        $this->assertSame('finished', $state['phase']);
        $this->assertSame('host', $state['winner'], 'El anfitrión lleva más impactos, debe ganar.');
    }

    public function test_state_reports_progress(): void
    {
        Cache::flush();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'place', 'ships' => $this->fleet()])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'ready'])->assertOk();
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'start'])->assertOk();

        $this->asHost()->postJson('/batalla-emitir', ['type' => 'shot', 'r' => 0, 'c' => 0])->assertOk();

        $view = $this->asHost()->getJson('/batalla-estado')->json('state');
        $this->assertSame(1, $view['myHits']);
        $this->assertSame(0, $view['enemyHits']);
        $this->assertSame(20, $view['totalCells']);
    }

    private function asTriviaHost(): self
    {
        Cache::put(
            'juego:host_lock',
            ['token' => 'host-1', 'expires_at' => now()->addMinute()->getTimestamp()],
            now()->addMinute()
        );

        return $this->withSession(['role_juego' => 'host', 'bs_token' => 'host-1']);
    }

    public function test_trivia_index_accepts_role_buttons(): void
    {
        Cache::flush();

        // Botón "Entrar como ANFITRIÓN" del menú
        $this->get('/juego?rol=host')->assertRedirect('/juego-emisor');
        $this->assertSame('host', session('role_juego'));

        // Botón "Entrar como INVITADO"
        Cache::put('juego:host_lock', ['token' => 'otro', 'expires_at' => now()->addMinute()->getTimestamp()], now()->addMinute());
        $guest = $this->flushSession();
        $guest->get('/juego?rol=guest')->assertRedirect('/juego-receptor');
        $this->assertSame('guest', session('role_juego'));

        // Si ya hay anfitrión, pedir host cae a invitado (con aviso)
        $otro = $this->flushSession();
        $otro->get('/juego?rol=host')
            ->assertRedirect('/juego-receptor')
            ->assertSessionHas('info');
    }

    public function test_trivia_views_include_the_csrf_token(): void
    {
        Cache::flush();
        Cache::put('juego:host_lock', ['token' => 'host-1', 'expires_at' => now()->addMinute()->getTimestamp()], now()->addMinute());

        // Sin el meta csrf-token, todo POST del Trivia responde 419 y no se puede jugar.
        $this->asTriviaHost()
            ->get('/juego-emisor')->assertOk()->assertSee('name="csrf-token"', false);

        $this->withSession(['role_juego' => 'guest', 'bs_token' => 'guest-1'])
            ->get('/juego-receptor')->assertOk()->assertSee('name="csrf-token"', false);
    }

    public function test_trivia_views_send_heartbeat_to_keep_the_host_slot(): void
    {
        Cache::flush();

        // Sin latidos, la reserva de 45 s del lugar de anfitrión se vencía y el
        // anfitrión pasaba a invitado a los ~45 s (bug reportado).
        foreach (['/juego-emisor' => 'host', '/juego-receptor' => 'guest'] as $url => $role) {
            Cache::put('juego:host_lock', ['token' => 'host-1', 'expires_at' => now()->addMinute()->getTimestamp()], now()->addMinute());

            $html = $this->withSession(['role_juego' => $role, 'bs_token' => $role === 'host' ? 'host-1' : 'guest-1'])
                ->get($url)->assertOk()->getContent();

            $this->assertStringContainsString("type: 'heartbeat'", $html, "{$url} debe mandar latidos");
        }
    }

    public function test_trivia_heartbeat_refreshes_the_host_slot(): void
    {
        Cache::flush();
        $host = $this->asTriviaHost();

        // Un lock casi vencido...
        Cache::put(
            'juego:host_lock',
            ['token' => 'host-1', 'expires_at' => now()->addSeconds(5)->getTimestamp()],
            now()->addSeconds(5)
        );

        // ...que el latido debe renovar.
        $host->postJson('/juego-emitir', ['type' => 'heartbeat'])->assertOk();

        $lock = Cache::get('juego:host_lock');
        $this->assertSame('host-1', $lock['token']);
        $this->assertGreaterThan(time() + 30, $lock['expires_at'], 'El latido tiene que extender la reserva.');
    }

    public function test_guest_is_promoted_automatically_after_one_minute_without_host(): void
    {
        Cache::flush();

        // El invitado manda su latido mientras el anfitrión está vivo: no pasa nada.
        $this->asHost()->postJson('/batalla-emitir', ['type' => 'heartbeat'])->assertOk()->assertJson(['promoted' => false]);
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'heartbeat'])->assertOk()->assertJson(['promoted' => false]);

        // Pasa 1 minuto sin que el anfitrión renueve su reserva.
        Cache::put(
            'batalla:host_lock',
            ['token' => 'token-host', 'expires_at' => now()->subSecond()->getTimestamp()],
            now()->addMinute()
        );

        // El siguiente latido del invitado lo toma automáticamente.
        $this->asGuest()->postJson('/batalla-emitir', ['type' => 'heartbeat'])
            ->assertOk()
            ->assertJson(['promoted' => true]);

        $this->assertTrue(HostSlot::isMine('batalla'));
    }

    public function test_only_one_guest_is_promoted_when_two_are_connected(): void
    {
        Cache::flush();

        // El lugar quedó libre (el anfitrión se fue).
        $this->assertFalse(HostSlot::isTaken('batalla'));

        $primero = $this->asGuest();
        $primero->postJson('/batalla-emitir', ['type' => 'heartbeat'])->assertJson(['promoted' => true]);

        // El segundo invitado ya no puede: el lugar está tomado.
        $this->flushSession();
        $this->withSession(['role_batalla' => 'guest', 'bs_token' => 'otro-invitado'])
            ->postJson('/batalla-emitir', ['type' => 'heartbeat'])
            ->assertJson(['promoted' => false]);
    }

    public function test_broadcast_test_endpoint_dispatches_event(): void
    {
        $this->get('/broadcast-test')
            ->assertOk()
            ->assertJson(['ok' => true, 'channel' => 'style-channel']);
    }
}