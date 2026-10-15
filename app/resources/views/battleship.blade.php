<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batalla Naval 🚢</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-900 text-white font-sans">
    <div class="max-w-4xl mx-auto px-6 py-12">
        <div class="text-center mb-12">
            <div class="text-6xl mb-4 floaty">🚢</div>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-3">Batalla Naval</h1>
            <p class="text-white/70 text-lg">Dos jugadores · Colocación de barcos · Turnos en vivo · Hundir la flota</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <a href="{{ route('batalla.emisor') }}"
               class="group relative rounded-2xl bg-gradient-to-br from-sky-600/30 to-blue-600/30 border border-sky-400/30 p-8 hover:border-sky-400/60 transition-all overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-t from-sky-500/10 to-transparent opacity-0 group-hover:opacity-100 transition"></div>
                <div class="relative z-10">
                    <div class="text-5xl mb-4">🎛️</div>
                    <h2 class="text-2xl font-extrabold mb-2">Soy el Anfitrión</h2>
                    <p class="text-white/70 text-sm mb-4">Colocás tus barcos, iniciás la partida, disparás primero.</p>
                    <div class="mt-4 text-sky-300 font-semibold group-hover:translate-x-1 transition inline-block">Abrir control →</div>
                </div>
            </a>
            <a href="{{ route('batalla.receptor') }}"
               class="group relative rounded-2xl bg-gradient-to-br from-amber-600/30 to-orange-600/30 border border-amber-400/30 p-8 hover:border-amber-400/60 transition-all overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-t from-amber-500/10 to-transparent opacity-0 group-hover:opacity-100 transition"></div>
                <div class="relative z-10">
                    <div class="text-5xl mb-4">🎮</div>
                    <h2 class="text-2xl font-extrabold mb-2">Soy el Invitado</h2>
                    <p class="text-white/70 text-sm mb-4">Colocás tus barcos, esperás turno, respondés los disparos.</p>
                    <div class="mt-4 text-amber-300 font-semibold group-hover:translate-x-1 transition inline-block">Abrir pantalla →</div>
                </div>
            </a>
        </div>

        <div class="mt-8 rounded-2xl bg-black/30 border border-white/10 p-4 text-sm text-white/60">
            <p class="font-bold mb-2">Cómo jugar:</p>
            <ol class="list-decimal list-inside space-y-1">
                <li>Ambos abren su vista (Anfitrión e Invitado).</li>
                <li>Cada uno coloca sus 6 barcos en su tablero (drag/click para rotar).</li>
                <li>Cuando los dos terminan, el Anfitrión aprieta <b>🚀 Iniciar Partida</b>.</li>
                <li>Turnos alternados: el que toca elige casilla en el tablero ENEMIGO y dispara.</li>
                <li><b>💥 Impacto</b> = juega de nuevo · <b>🌊 Agua</b> = turno del otro · <b>🚢 Hundido</b> = aviso especial.</li>
                <li>Gana quien hunda los 17 casillas enemigas primero.</li>
            </ol>
        </div>

        <div class="mt-6 text-center text-sm text-white/40">
            Otros juegos: <a class="underline" href="{{ route('juego') }}">Trivia Relámpago ⚡</a> · Clásico: <a class="underline" href="/emisor">/emisor</a> · <a class="underline" href="/receptor">/receptor</a>
        </div>
    </div>

    <style>
        @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
        .floaty { animation: floaty 3s ease-in-out infinite; }
    </style>
</body>
</html>