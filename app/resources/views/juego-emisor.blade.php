<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Control · Trivia Relámpago 🎛️</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white font-sans">
<div class="max-w-5xl mx-auto px-5 py-8">
    <div class="rounded-2xl bg-gradient-to-r from-blue-600/30 to-indigo-600/30 border-2 border-blue-400/50 p-4 mb-4 text-center glow">
        <h1 class="text-3xl font-extrabold text-blue-300">🎛️ ANFITRIÓN</h1>
        <p class="text-blue-200 text-sm">Sos el anfitrión de esta partida</p>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <h1 class="text-3xl font-extrabold">🎛️ Control · Trivia Relámpago</h1>
            <p class="text-white/50 text-sm mt-1">Lanzá preguntas, controlá el tiempo, revelá respuestas. La pantalla del jugador se actualiza sola.</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ route('juego.receptor') }}" target="_blank" class="rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold px-4 py-2 text-sm transition">🎮 Abrir pantalla</a>
            <a href="{{ route('batalla.emisor') }}" class="rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-bold px-4 py-2 text-sm transition">🚢 Batalla Naval</a>
            <a href="/" class="rounded-xl bg-white/10 border border-white/15 px-4 py-2 text-sm font-bold hover:bg-white/20 transition">← Salir al menú</a>
        </div>
    </div>

    @if (session('ok'))
        <div id="flash" class="mb-4 rounded-xl bg-emerald-500/15 border border-emerald-400/40 text-emerald-200 px-4 py-2 text-sm pop-in">✅ {{ session('ok') }}</div>
    @endif
    @if ($errors->any())
        <div class="mb-4 rounded-xl bg-red-500/15 border border-red-400/40 text-red-200 px-4 py-2 text-sm">⚠️ {{ $errors->first() }}</div>
    @endif

    <div class="mb-4 p-4 rounded-xl bg-white/5 border border-white/10">
        <p class="font-bold text-white mb-2">📋 Flujo de juego:</p>
        <ol class="list-decimal list-inside space-y-1 text-white/70 text-sm">
            <li><b>🚀 Lanzar</b> una pregunta → los jugadores eligen su opción (A/B/C/D) contra reloj.</li>
            <li>Los jugadores pueden <b>cambiar de opción</b> mientras dure el timer.</li>
            <li><b>👁 Revelar</b> → muestra correcta/incorrecta, suma puntos (10 base + bonus velocidad + bonus racha).</li>
            <li>Si no revelás, <b>auto-revela al acabar el tiempo</b> (para que no se trabe).</li>
            <li><b>🔄 Reset</b> reinicia puntos y racha de todos.</li>
        </ol>
    </div>

    <form id="emitForm" method="POST" action="{{ route('juego.emitir') }}" class="mb-6">
        @csrf
        <input type="hidden" name="type" id="f_type" value="question">
        <input type="hidden" name="index" id="f_index" value="0">
        <input type="hidden" name="seconds" id="f_seconds" value="15">
        <input type="hidden" name="message" id="f_message" value="">

        <div class="flex flex-wrap items-center gap-3 mb-4">
            <label class="text-sm text-white/60">⏱️ Tiempo por pregunta</label>
            <div class="flex gap-2" id="secs">
                @foreach ([10,15,20,30] as $s)
                    <button type="button" data-sec="{{ $s }}"
                        class="secBtn rounded-lg px-3 py-1.5 text-sm font-bold border {{ $s===15 ? 'bg-fuchsia-500 border-fuchsia-400' : 'bg-white/10 border-white/15 hover:bg-white/20' }}">{{ $s }}s</button>
                @endforeach
            </div>
            <div class="ms-auto flex gap-2">
                <button type="button" id="btnReset" class="rounded-xl bg-white/10 border border-white/15 px-4 py-2 text-sm font-bold hover:bg-white/20 transition">🔄 Reset total</button>
                <button type="button" id="btnMsg" class="rounded-xl bg-white/10 border border-white/15 px-4 py-2 text-sm font-bold hover:bg-white/20 transition">📣 Animar</button>
            </div>
        </div>

        <div class="mb-4 hidden gap-2" id="msgRow">
            <input id="msgInput" type="text" maxlength="280" placeholder="Ej: ¡Última pregunta, doble puntos! 🎉"
                class="flex-1 rounded-xl bg-black/40 border border-white/15 px-3 py-2 text-sm outline-none focus:border-fuchsia-400">
            <button type="button" id="btnMsgSend" class="rounded-xl bg-amber-400 text-slate-950 font-bold px-4 py-2 text-sm">Enviar</button>
        </div>
    </form>

    <!-- Filtros -->
    <div class="flex flex-wrap gap-2 mb-4">
        <button class="filterBtn rounded-lg px-3 py-1.5 text-sm font-bold bg-fuchsia-500 border-fuchsia-400" data-cat="all">Todas</button>
        @php
            $cats = array_unique(array_column($preguntas, 'categoria'));
            sort($cats);
        @endphp
        @foreach ($cats as $cat)
            <button class="filterBtn rounded-lg px-3 py-1.5 text-sm font-bold bg-white/10 border-white/15 hover:bg-white/20" data-cat="{{ $cat }}">{{ $cat }}</button>
        @endforeach
    </div>

    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-3" id="questionsGrid">
        @foreach ($preguntas as $i => $p)
            <div class="qcard rounded-2xl bg-white/5 border border-white/10 p-4 hover:border-fuchsia-400/50 transition" data-index="{{ $i }}" data-category="{{ $p['categoria'] }}">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-bold text-fuchsia-300">PREGUNTA {{ $i+1 }} / {{ count($preguntas) }}</span>
                    <span class="text-xs px-2 py-0.5 rounded bg-white/10 text-white/70">{{ $p['categoria'] }}</span>
                </div>
                <div class="font-bold leading-snug mb-2">{{ $p['pregunta'] }}</div>
                <ul class="space-y-1 text-sm text-white/70 mb-3">
                    @foreach ($p['opciones'] as $j => $op)
                        <li class="rounded-lg bg-black/30 px-2 py-1 flex items-center gap-2">
                            <span class="w-6 h-6 shrink-0 rounded-full bg-slate-800 flex items-center justify-center text-xs font-bold">{{ ['A','B','C','D'][$j] }}</span>
                            {{ $op }} @if($j===$p['respuesta']) <span title="Respuesta correcta" class="text-emerald-400 ml-auto">✅</span> @endif
                        </li>
                    @endforeach
                </ul>
                <div class="flex gap-2">
                    <button class="btnLaunch flex-1 rounded-xl bg-fuchsia-500 hover:bg-fuchsia-400 font-bold px-3 py-2 text-sm transition" data-index="{{ $i }}">🚀 Lanzar</button>
                    <button class="btnReveal flex-1 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold px-3 py-2 text-sm transition" data-index="{{ $i }}">👁 Revelar</button>
                </div>
            </div>
        @endforeach
    </div>

    <p class="mt-6 text-center text-xs text-white/30">
        Eventos viajan por <code>game-channel</code> · Reverb :8080 ·
        <a class="underline hover:text-white/70" href="/">← Menú principal</a>
    </p>
</div>

<script>
const form = document.getElementById('emitForm');
const fType = document.getElementById('f_type');
const fIndex = document.getElementById('f_index');
const fSeconds = document.getElementById('f_seconds');
const fMessage = document.getElementById('f_message');
let seconds = 15;

document.querySelectorAll('.secBtn').forEach(b => b.addEventListener('click', () => {
    seconds = parseInt(b.dataset.sec, 10);
    fSeconds.value = seconds;
    document.querySelectorAll('.secBtn').forEach(x => x.className = 'secBtn rounded-lg px-3 py-1.5 text-sm font-bold border bg-white/10 border-white/15 hover:bg-white/20');
    b.className = 'secBtn rounded-lg px-3 py-1.5 text-sm font-bold border bg-fuchsia-500 border-fuchsia-400';
}));

function send(type, index = 0) {
    fType.value = type;
    fIndex.value = index;
    fSeconds.value = seconds;
    form.submit();
}
document.querySelectorAll('.btnLaunch').forEach(b => b.addEventListener('click', () => send('question', b.dataset.index)));
document.querySelectorAll('.btnReveal').forEach(b => b.addEventListener('click', () => send('reveal', b.dataset.index)));
document.getElementById('btnReset').addEventListener('click', () => send('reset'));

// Filtros
document.querySelectorAll('.filterBtn').forEach(b => b.addEventListener('click', () => {
    const cat = b.dataset.cat;
    document.querySelectorAll('.filterBtn').forEach(x => x.className = 'filterBtn rounded-lg px-3 py-1.5 text-sm font-bold bg-white/10 border-white/15 hover:bg-white/20');
    b.className = 'filterBtn rounded-lg px-3 py-1.5 text-sm font-bold bg-fuchsia-500 border-fuchsia-400';
    document.querySelectorAll('.qcard').forEach(card => {
        card.style.display = (cat === 'all' || card.dataset.category === cat) ? 'block' : 'none';
    });
}));

// Mensaje animado
const msgRow = document.getElementById('msgRow');
document.getElementById('btnMsg').addEventListener('click', () => msgRow.classList.toggle('hidden'));
document.getElementById('btnMsgSend').addEventListener('click', () => {
    fMessage.value = document.getElementById('msgInput').value || '¡Vamos, que se puede! 💪';
    send('message');
});

setTimeout(() => document.getElementById('flash')?.remove(), 3000);

// Latido: renueva la reserva del lugar de anfitrión. Sin esto, a los 45 s el
// servidor daba el lugar por libre y el anfitrión pasaba a solo invitado.
const CSRF_TRIVIA = document.querySelector('meta[name="csrf-token"]')?.content || '';
setInterval(() => {
    fetch('{{ route('juego.emitir') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TRIVIA, 'Accept': 'application/json' },
        body: JSON.stringify({ type: 'heartbeat' })
    }).catch(() => {});
}, 5000);

// Animación entrada
document.querySelectorAll('.qcard').forEach((c, i) => {
    c.style.opacity = '0';
    c.style.transform = 'translateY(10px)';
    setTimeout(() => { c.style.transition = 'all .3s ease'; c.style.opacity = '1'; c.style.transform = 'none'; }, 50 * i);
});
</script>
<style>
@keyframes pop-in { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:none} }
.pop-in { animation: pop-in .3s ease-out; }
</style>
</body>
</html>