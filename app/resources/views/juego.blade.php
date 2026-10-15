<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Juegos en Vivo ⚡🚢</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-950 via-purple-900 to-fuchsia-900 text-white font-sans">
    <div class="max-w-4xl mx-auto px-6 py-12">
        <div class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-3">🎮 Juegos en Vivo</h1>
            <p class="text-white/70 text-lg">Tu estructura emisor → Reverb → receptor, ahora con dos juegos.</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            <!-- Trivia -->
            <a href="{{ route('juego.emisor') }}" class="group relative rounded-2xl bg-gradient-to-br from-fuchsia-600/30 to-purple-600/30 border border-fuchsia-400/30 p-8 hover:border-fuchsia-400/60 transition-all overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-t from-fuchsia-500/10 to-transparent opacity-0 group-hover:opacity-100 transition"></div>
                <div class="relative z-10">
                    <div class="text-5xl mb-4 floaty">⚡</div>
                    <h2 class="text-2xl font-extrabold mb-2">Trivia Relámpago</h2>
                    <p class="text-white/70 text-sm mb-4">12 preguntas · timer · rachas · confetti · auto-revelado</p>
                    <div class="flex gap-2">
                        <span class="px-2 py-1 rounded text-xs bg-fuchsia-500/20 border border-fuchsia-400/30">Anfitrión</span>
                        <span class="px-2 py-1 rounded text-xs bg-white/10 border border-white/10">Jugador</span>
                    </div>
                    <div class="mt-4 text-fuchsia-300 font-semibold group-hover:translate-x-1 transition inline-block">Jugar Trivia →</div>
                </div>
            </a>

            <!-- Batalla Naval -->
            <a href="{{ route('batalla.emisor') }}" class="group relative rounded-2xl bg-gradient-to-br from-sky-600/30 to-blue-600/30 border border-sky-400/30 p-8 hover:border-sky-400/60 transition-all overflow-hidden">
                <div class="absolute inset-0 bg-gradient-to-t from-sky-500/10 to-transparent opacity-0 group-hover:opacity-100 transition"></div>
                <div class="relative z-10">
                    <div class="text-5xl mb-4 floaty">🚢</div>
                    <h2 class="text-2xl font-extrabold mb-2">Batalla Naval</h2>
                    <p class="text-white/70 text-sm mb-4">Coloca barcos · turnos · impacto/hundido · victoria en vivo</p>
                    <div class="flex gap-2">
                        <span class="px-2 py-1 rounded text-xs bg-sky-500/20 border border-sky-400/30">Anfitrión</span>
                        <span class="px-2 py-1 rounded text-xs bg-white/10 border border-white/10">Jugador</span>
                    </div>
                    <div class="mt-4 text-sky-300 font-semibold group-hover:translate-x-1 transition inline-block">Jugar Batalla →</div>
                </div>
            </a>
        </div>

        <div class="mt-8 rounded-2xl bg-black/30 border border-white/10 p-4 text-sm text-white/60">
            <p class="font-bold mb-2">Cómo funciona:</p>
            <ol class="list-decimal list-inside space-y-1">
                <li>Abrí el <b>Control (Anfitrión)</b> en una ventana.</li>
                <li>Abrí la <b>Pantalla (Jugador)</b> en otra ventana/pestaña (o en otro dispositivo).</li>
                <li>El anfitrión controla el juego; el jugador ve todo en tiempo real vía WebSocket (Reverb :8080).</li>
            </ol>
        </div>

        <div class="mt-6 text-center text-sm text-white/40">
            Clásico: <a class="underline" href="/emisor">/emisor</a> · <a class="underline" href="/receptor">/receptor</a>
        </div>
    </div>

    <style>
        @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
        .floaty { animation: floaty 3s ease-in-out infinite; }
    </style>
</body>
</html>