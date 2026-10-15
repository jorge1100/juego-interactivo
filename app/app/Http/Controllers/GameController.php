<?php

namespace App\Http\Controllers;

use App\Events\GameUpdate;
use App\Services\HostSlot;
use Illuminate\Http\Request;

class GameController extends Controller
{
    /** @var array<int,array{pregunta:string,opciones:array<int,string>,respuesta:int,dato:string,categoria:string}> */
    public const PREGUNTAS = [
        ['pregunta' => '¿Qué lenguaje se ejecuta en el navegador?', 'opciones' => ['Python', 'JavaScript', 'PHP', 'SQL'], 'respuesta' => 1, 'dato' => 'JavaScript nació en 1995 y hoy mueve casi toda la web interactiva.', 'categoria' => 'Web'],
        ['pregunta' => '¿Qué significa "Reverb" en este proyecto?', 'opciones' => ['Un servidor WebSocket de Laravel', 'Una base de datos', 'Un framework CSS', 'Un tester de APIs'], 'respuesta' => 0, 'dato' => 'Reverb mantiene un canal abierto para avisar al receptor al instante.', 'categoria' => 'Laravel'],
        ['pregunta' => '¿Qué puerto usa la app en tu Docker?', 'opciones' => ['3000', '5436', '8343', '27017'], 'respuesta' => 2, 'dato' => 'Nginx expone la app en http://localhost:8343.', 'categoria' => 'Docker'],
        ['pregunta' => '¿Qué canal escucha el receptor del juego?', 'opciones' => ['style-channel', 'game-channel', 'chat-channel', 'test-channel'], 'respuesta' => 1, 'dato' => 'El juego usa su propio canal: game-channel.', 'categoria' => 'Laravel'],
        ['pregunta' => '¿Cuál es el planeta más grande del sistema solar?', 'opciones' => ['Saturno', 'Júpiter', 'Neptuno', 'Tierra'], 'respuesta' => 1, 'dato' => 'Júpiter es tan grande que cabrían 1.300 Tierras dentro.', 'categoria' => 'Ciencia'],
        ['pregunta' => '¿Qué etiqueta HTML muestra una imagen?', 'opciones' => ['<img>', '<pic>', '<src>', '<image>'], 'respuesta' => 0, 'dato' => 'La etiqueta es <img src="...">.', 'categoria' => 'Web'],
        ['pregunta' => '¿Cuántos segundos tiene un minuto?', 'opciones' => ['30', '60', '90', '100'], 'respuesta' => 1, 'dato' => 'Pregunta relámpago para sumar racha.', 'categoria' => 'General'],
        ['pregunta' => '¿Qué base de datos documental usa el proyecto?', 'opciones' => ['MariaDB', 'SQLite', 'MongoDB', 'Redis'], 'respuesta' => 2, 'dato' => 'MongoDB guarda documentos JSON flexibles.', 'categoria' => 'Backend'],
        ['pregunta' => '¿Qué método HTTP usa el formulario del emisor clásico?', 'opciones' => ['GET', 'POST', 'PUT', 'DELETE'], 'respuesta' => 1, 'dato' => 'El formulario hace POST a /emitir.', 'categoria' => 'Laravel'],
        ['pregunta' => '¿Qué hace "php artisan migrate"?', 'opciones' => ['Borra la BD', 'Crea las tablas', 'Hace backup', 'Optimiza consultas'], 'respuesta' => 1, 'dato' => 'Ejecuta las migraciones pendientes.', 'categoria' => 'Laravel'],
        ['pregunta' => '¿Qué comando limpia la caché de config?', 'opciones' => ['config:clear', 'cache:flush', 'view:clear', 'route:clear'], 'respuesta' => 0, 'dato' => 'config:clear borra el archivo cached.', 'categoria' => 'Laravel'],
        ['pregunta' => '¿Qué color tiene el logo de Laravel?', 'opciones' => ['Rojo', 'Azul', 'Naranja', 'Verde'], 'respuesta' => 0, 'dato' => 'El logo oficial es rojo (#FF2D20).', 'categoria' => 'Laravel'],
    ];

    public function index(Request $request)
    {
        $wanted = in_array($request->query('rol'), ['host', 'guest'], true)
            ? $request->query('rol')
            : null;

        if (session()->missing('role_juego')) {
            if ($wanted === 'guest' && ! HostSlot::isTaken('juego')) {
                session(['role_juego' => HostSlot::claim('juego') ? 'host' : 'guest']);

                return redirect('/juego-emisor')->with('info', 'No había ningún anfitrión, así que fundaste la partida.');
            }

            if ($wanted === 'host' && HostSlot::isTaken('juego')) {
                return redirect('/juego-receptor')->with('info', 'Ya hay un anfitrión jugando, así que entraste como invitado.');
            }

            session(['role_juego' => HostSlot::claim('juego') ? 'host' : 'guest']);
        }

        return redirect(session('role_juego') === 'host' ? '/juego-emisor' : '/juego-receptor');
    }

    public function emisor()
    {
        if ($this->currentRole() !== 'host') {
            return redirect('/juego-receptor')->with('info', 'Ya hay un anfitrión. Sos el invitado.');
        }

        return view('juego-emisor', ['preguntas' => self::PREGUNTAS]);
    }

    public function receptor()
    {
        if ($this->currentRole() === 'host') {
            return redirect('/juego-emisor')->with('info', 'Sos el anfitrión.');
        }

        // Solo le ofrecemos tomar el lugar si no hay ningún anfitrión conectado.
        session(['sinAnfitrion' => ! HostSlot::isTaken('juego')]);

        return view('juego-receptor');
    }

    /**
     * Rol actual. Un invitado NO se autopromueve en cada request: antes lo
     * hacía y cualquier pestaña vieja robaba el lugar y el rol "flapeara".
     * Subir a anfitrión es una acción explícita desde el menú.
     */
    private function currentRole(): string
    {
        $role = session('role_juego');

        if ($role === 'host') {
            if (HostSlot::isMine('juego')) {
                HostSlot::refresh('juego');

                return 'host';
            }

            // Perdió el lugar (otro lo tomó o venció el lock): pasa a invitado.
            session(['role_juego' => 'guest']);

            return 'guest';
        }

        if (session()->missing('role_juego')) {
            session(['role_juego' => HostSlot::claim('juego') ? 'host' : 'guest']);

            return session('role_juego');
        }

        return 'guest';
    }

    public function emitir(Request $request)
    {
        $data = $request->validate([
            'type' => ['required', 'in:question,reveal,reset,message,heartbeat,release-host'],
            'index' => ['nullable', 'integer', 'min:0', 'max:' . (count(self::PREGUNTAS) - 1)],
            'seconds' => ['nullable', 'integer', 'min:5', 'max:60'],
            'message' => ['nullable', 'string', 'max:280'],
        ]);

        $type = $data['type'];

        // Latido: mantiene viva la reserva del lugar de anfitrión (45 s).
        // Sin esto el anfitrión perdía el puesto a los 45 segundos y pasaba a
        // invitado. Al cerrar la pestaña se libera el lugar.
        if ($type === 'heartbeat') {
            if (session('role_juego') === 'host') {
                HostSlot::refresh('juego');

                return response()->json(['ok' => true, 'promoted' => false]);
            }

            // El anfitrión lleva más de 1 minuto sin señal: el invitado vivo
            // toma su lugar solo. `claim()` es atómico, así que si hay varios
            // invitados conectados, solo uno se vuelve anfitrión.
            if (session('role_juego') === 'guest'
                && ! HostSlot::isTaken('juego')
                && HostSlot::claim('juego')) {
                session(['role_juego' => 'host']);

                return response()->json(['ok' => true, 'promoted' => true]);
            }

            return response()->json(['ok' => true, 'promoted' => false]);
        }

        if ($type === 'release-host') {
            if (session('role_juego') === 'host') {
                HostSlot::release('juego');
            }

            return response()->json(['ok' => true]);
        }

        if ($type === 'question') {
            $i = (int) ($data['index'] ?? 0);
            $q = self::PREGUNTAS[$i];
            broadcast(new GameUpdate('question', [
                'index' => $i,
                'total' => count(self::PREGUNTAS),
                'question' => $q['pregunta'],
                'options' => $q['opciones'],
                'seconds' => (int) ($data['seconds'] ?? 15),
                'correctIndex' => $q['respuesta'],
                'fact' => $q['dato'],
                'category' => $q['categoria'],
            ]));
        } elseif ($type === 'reveal') {
            $i = (int) ($data['index'] ?? 0);
            $q = self::PREGUNTAS[$i];
            broadcast(new GameUpdate('reveal', [
                'index' => $i,
                'correctIndex' => $q['respuesta'],
                'fact' => $q['dato'],
            ]));
        } elseif ($type === 'reset') {
            broadcast(new GameUpdate('reset', []));
        } else {
            broadcast(new GameUpdate('message', [
                'message' => $data['message'] ?? '',
            ]));
        }

        return back()->with('ok', 'Jugada emitida correctamente.');
    }
}