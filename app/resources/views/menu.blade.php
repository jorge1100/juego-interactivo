<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menú · Juegos en Vivo</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-6px)} }
        @keyframes fadeIn { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:none} }
        .floaty { animation: floaty 3s ease-in-out infinite; }
        .card-hover { transition: all .25s ease; }
        .card-hover:hover { transform: translateY(-4px); box-shadow: 0 18px 36px -14px rgba(0,0,0,.6); }
        .stagger-1 { animation: fadeIn .5s ease-out .05s both; }
        .stagger-2 { animation: fadeIn .5s ease-out .2s both; }
        .pill { display:inline-flex; align-items:center; gap:.4rem; border-radius:999px; padding:.45rem .9rem; font-size:.8rem; font-weight:700; }
        .libre { background:rgba(52,211,153,.15); border:1px solid rgba(52,211,153,.45); color:#6ee7b7; }
        .ocupado { background:rgba(251,191,36,.15); border:1px solid rgba(251,191,36,.45); color:#fcd34d; }
        .btn-jugar { display:block; border-radius:14px; padding:.7rem 1rem; font-weight:800; font-size:.9rem; text-align:center; transition:.15s; }
        .btn-host { background:linear-gradient(135deg,#3b82f6,#2563eb); color:#fff; }
        .btn-host:hover { filter:brightness(1.12); }
        .btn-guest { background:linear-gradient(135deg,#f59e0b,#d97706); color:#1f2937; }
        .btn-guest:hover { filter:brightness(1.08); }
        .btn-take { background:rgba(255,255,255,.08); border:1px solid rgba(255,255,255,.18); color:rgba(255,255,255,.8); }
        .btn-take:hover { background:rgba(255,255,255,.16); color:#fff; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-950 via-purple-900 to-fuchsia-900 text-white font-sans flex items-center justify-center p-6">
<div class="max-w-5xl w-full">
    <div class="text-center mb-10">
        <h1 class="text-5xl font-extrabold mb-3">🎮 Juegos en Vivo</h1>
        <p class="text-white/70 text-lg">Elegí un juego y entrá con el rol que quieras</p>
        <p class="text-white/50 text-sm mt-2">El <span class="text-blue-300 font-bold">anfitrión</span> lanza y dispara · el <span class="text-amber-300 font-bold">invitado</span> juega en su pantalla</p>
    </div>

    @if (session('info'))
        <div class="mx-auto mb-6 max-w-3xl rounded-xl border border-sky-400/40 bg-sky-500/15 px-4 py-3 text-sm text-sky-100">
            {{ session('info') }}
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">

        {{-- ============ TRIVIA ============ --}}
        <div class="card-hover stagger-1 rounded-3xl border border-fuchsia-400/30 bg-gradient-to-br from-fuchsia-600/20 to-purple-600/20 p-7">
            <div class="mb-1 flex items-start justify-between gap-3">
                <div>
                    <div class="text-5xl floaty">⚡</div>
                    <h2 class="mt-2 text-2xl font-extrabold">Trivia Relámpago</h2>
                    <p class="text-white/60 text-xs mt-1">12 preguntas · timer · rachas · confetti</p>
                </div>
            </div>

            {{-- Estado real del lugar de anfitrión --}}
            <div class="mt-4">
                @if ($juegoOcupado)
                    <span class="pill ocupado">🔵 Ya hay un anfitrión</span>
                @else
                    <span class="pill libre">🟢 Lugar de anfitrión libre</span>
                @endif
                @if ($miJuego)
                    <span class="pill ml-2 {{ $miJuego === 'host' ? 'libre' : 'ocupado' }}">
                        Vos ya sos: {{ $miJuego === 'host' ? 'Anfitrión' : 'Invitado' }}
                    </span>
                @endif
            </div>

            <div class="mt-4 grid gap-2">
                @if ($miJuego === 'host')
                    <a href="/juego-emisor" class="btn-jugar btn-host">⚓ Volver a mi partida</a>
                @else
                    <a href="/juego?rol=host" class="btn-jugar btn-host">🎛️ Entrar como ANFITRIÓN</a>
                    <a href="/juego?rol=guest" class="btn-jugar btn-guest">🎮 Entrar como INVITADO</a>
                @endif
            </div>
        </div>

        {{-- ============ BATALLA NAVAL ============ --}}
        <div class="card-hover stagger-2 rounded-3xl border border-sky-400/30 bg-gradient-to-br from-sky-600/20 to-blue-600/20 p-7">
            <div class="mb-1 flex items-start justify-between gap-3">
                <div>
                    <div class="text-5xl floaty">🚢</div>
                    <h2 class="mt-2 text-2xl font-extrabold">Batalla Naval</h2>
                    <p class="text-white/60 text-xs mt-1">2 jugadores · colocá barcos · hundí la flota</p>
                </div>
            </div>

            <div class="mt-4">
                @if ($batallaOcupado)
                    <span class="pill ocupado">🔵 Ya hay un anfitrión</span>
                @else
                    <span class="pill libre">🟢 Lugar de anfitrión libre</span>
                @endif
                @if ($miBatalla)
                    <span class="pill ml-2 {{ $miBatalla === 'host' ? 'libre' : 'ocupado' }}">
                        Vos ya sos: {{ $miBatalla === 'host' ? 'Anfitrión' : 'Invitado' }}
                    </span>
                @endif
            </div>

            <div class="mt-4 grid gap-2">
                @if ($miBatalla === 'host')
                    <a href="/batalla-emisor" class="btn-jugar btn-host">⚓ Volver a mi partida</a>
                @else
                    <a href="/batalla?rol=host" class="btn-jugar btn-host">🎛️ Entrar como ANFITRIÓN</a>
                    <a href="/batalla?rol=guest" class="btn-jugar btn-guest">🎮 Entrar como INVITADO</a>
                @endif
            </div>
        </div>
    </div>

    <div class="mt-10 text-center">
        <p class="text-white/35 text-xs">
            Si ya sos invitado y el anfitrión cerró la partida, entrá y usá
            <span class="text-amber-300">👑 Tomar el lugar</span> para seguir jugando.
        </p>
        <p class="mt-3 text-white/25 text-xs">
            Clásico: <a class="underline hover:text-white/70" href="/emisor">/emisor</a> ·
            <a class="underline hover:text-white/70" href="/receptor">/receptor</a> ·
            Reverb :8080
        </p>
    </div>
</div>
</body>
</html>