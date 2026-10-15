<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Jugador 🎮 · Trivia Relámpago</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @keyframes pop { 0%{transform:scale(.9);opacity:0} 100%{transform:scale(1);opacity:1} }
        @keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-6px)} 75%{transform:translateX(6px)} }
        @keyframes floaty { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-8px)} }
        @keyframes pulse-ring { 0%{box-shadow:0 0 0 0 rgba(249,115,22,.7)} 100%{box-shadow:0 0 0 12px rgba(249,115,22,0)} }
        .pop { animation: pop .25s ease-out; }
        .shake { animation: shake .3s ease-in-out 2; }
        .floaty { animation: floaty 3s ease-in-out infinite; }
        .pulse-ring { animation: pulse-ring 1.5s ease-out infinite; }
        .opt { transition: transform .12s ease, background .15s ease, border-color .15s ease, box-shadow .15s ease; }
        .opt:not(:disabled):hover { transform: translateY(-2px) scale(1.01); box-shadow: 0 8px 20px -4px rgba(0,0,0,.4); }
        .opt:disabled { cursor: default; }
        #timerBar { transition: width 1s linear; }
        #confetti { position: fixed; inset: 0; pointer-events: none; z-index: 50; }
        .letter-badge { width: 36px; height: 36px; }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-indigo-950 via-purple-900 to-fuchsia-900 text-white font-sans">
<canvas id="confetti"></canvas>

<div class="max-w-2xl mx-auto px-5 py-8">
    <div class="flex flex-wrap items-center justify-end gap-2 mb-4">
        <a href="{{ route('juego.receptor') }}" class="rounded-xl bg-white/10 border border-white/15 px-4 py-2 text-xs font-bold hover:bg-white/20 transition">🎮 Abrir mi pantalla</a>
        <a href="/" class="rounded-xl bg-white/10 border border-white/15 px-4 py-2 text-xs font-bold hover:bg-white/20 transition">← Salir al menú</a>
    </div>
    <div class="rounded-2xl bg-gradient-to-r from-amber-600/30 to-orange-600/30 border-2 border-amber-400/50 p-4 mb-4 text-center glow">
        <h1 class="text-3xl font-extrabold text-amber-300">🎮 INVITADO</h1>
        <p class="text-amber-200 text-sm">Sos el invitado de esta partida</p>
    </div>

    <div class="flex items-center justify-between gap-3 flex-wrap">
        <div class="flex items-center gap-3">
            <div class="text-4xl floaty">⚡</div>
            <div>
                <h1 class="text-2xl font-extrabold leading-none">Trivia Relámpago</h1>
                <div id="conn" class="text-xs text-white/50 mt-1">● conectando…</div>
            </div>
        </div>
        <div class="flex gap-2 text-center">
            <div class="rounded-2xl bg-white/10 border border-white/15 px-4 py-2">
                <div class="text-[11px] uppercase tracking-wider text-white/50 font-bold">Puntos</div>
                <div id="score" class="text-2xl font-extrabold text-amber-300">0</div>
            </div>
            <div class="rounded-2xl bg-white/10 border border-white/15 px-4 py-2">
                <div class="text-[11px] uppercase tracking-wider text-white/50 font-bold">Racha 🔥</div>
                <div id="streak" class="text-2xl font-extrabold text-orange-400">0</div>
            </div>
        </div>
    </div>

    <div class="mt-4 rounded-2xl bg-white/10 border border-white/15 px-4 py-3 flex items-center gap-3">
        <span class="text-2xl">🧑‍🚀</span>
        <input id="nick" maxlength="20" placeholder="Tu nombre (ej: Roku)"
            class="flex-1 bg-transparent outline-none placeholder:text-white/30 font-bold">
        <span id="qcount" class="text-xs text-white/40 font-bold">— / —</div>
    </div>

    <div id="banner" class="hidden mt-4 rounded-2xl bg-amber-400/15 border border-amber-300/40 text-amber-200 px-4 py-3 text-sm font-semibold pop"></div>

    <div class="mt-3">
        {{-- Solo aparece si NO hay ningún anfitrión conectado --}}
    @if (session('sinAnfitrion'))
        <a href="{{ route('juego.emisor') }}" class="inline-block rounded-xl border border-amber-400/40 bg-amber-500/20 px-4 py-2 text-xs font-bold text-amber-200 hover:bg-amber-500/30 transition">
            👑 Tomar el lugar del anfitrión (no hay ninguno conectado)
        </a>
    @endif
        <p class="mt-1 text-[11px] text-white/35">Si abríste como invitado y el anfitrión no aparece, tomá el control desde acá.</p>
    </div>

    <div id="card" class="mt-4 rounded-3xl bg-white text-slate-900 p-6 md:p-8 shadow-2xl pop">
        <div id="idle">
            <div class="text-5xl text-center">🎮</div>
            <h2 class="text-2xl font-extrabold text-center mt-2">Esperando al anfitrión…</h2>
            <p class="text-center text-slate-500 text-sm mt-2">Cuando lancen una pregunta aparecerá aquí con el temporizador.<br>Respondé rápido: ¡la velocidad da bonus!</p>
            <div class="mt-4 flex justify-center gap-1">
                <span class="w-2 h-2 rounded-full bg-fuchsia-500 animate-bounce"></span>
                <span class="w-2 h-2 rounded-full bg-fuchsia-500 animate-bounce" style="animation-delay:.15s"></span>
                <span class="w-2 h-2 rounded-full bg-fuchsia-500 animate-bounce" style="animation-delay:.3s"></span>
            </div>
        </div>

        <div id="play" class="hidden">
            <div class="flex items-center justify-between text-xs font-bold text-slate-400 mb-2">
                <span id="qn">PREGUNTA 1/12</span>
                <span id="cat" class="px-2 py-0.5 rounded bg-fuchsia-100 text-fuchsia-700"></span>
            </div>
            <div class="flex items-center gap-2 text-slate-500 text-sm mb-3">
                <span id="tlabel" class="text-fuchsia-600 font-bold">⏱ 15s</span>
                <div class="flex-1 h-2.5 rounded-full bg-slate-100 overflow-hidden">
                    <div id="timerBar" class="h-full w-full rounded-full bg-gradient-to-r from-fuchsia-500 to-amber-400"></div>
                </div>
            </div>
            <h2 id="qtext" class="text-xl md:text-2xl font-extrabold mt-2 leading-snug">…</h2>
            <div id="opts" class="grid gap-2.5 mt-5"></div>
            <div id="fact" class="hidden mt-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm px-4 py-3"></div>
        </div>
    </div>

    <div class="mt-4 flex items-center justify-between text-xs text-white/40">
        <span id="status">Conectando al canal game-channel…</span>
        <button id="btnSound" class="rounded-lg bg-white/10 border border-white/15 px-3 py-1 font-bold hover:bg-white/20">🔊 sonido: on</button>
    </div>
</div>

<script>
const $ = (id) => document.getElementById(id);
const conn = $('conn'), statusEl = $('status'), banner = $('banner');
const idle = $('idle'), play = $('play'), card = $('card');
const qn = $('qn'), cat = $('cat'), qtext = $('qtext'), opts = $('opts'), fact = $('fact');
const timerBar = $('timerBar'), tlabel = $('tlabel'), qcount = $('qcount');
const scoreEl = $('score'), streakEl = $('streak'), nick = $('nick');

let score = parseInt(localStorage.getItem('trivia_score') || '0', 10);
let streak = parseInt(localStorage.getItem('trivia_streak') || '0', 10);
let picked = -1, correctIndex = -1, locked = true, revealed = false, timeLeft = 0, totalTime = 15, timer = null;
let pendingAnswer = -1, pendingFact = '', pendingCat = '';
let soundOn = true;
nick.value = localStorage.getItem('trivia_nick') || '';
nick.addEventListener('input', () => localStorage.setItem('trivia_nick', nick.value));

function paint() {
    scoreEl.textContent = score;
    streakEl.textContent = streak;
    localStorage.setItem('trivia_score', score);
    localStorage.setItem('trivia_streak', streak);
}
paint();

$('btnSound').addEventListener('click', (e) => {
    soundOn = !soundOn;
    e.target.textContent = soundOn ? '🔊 sonido: on' : '🔇 sonido: off';
});

function beep(freq = 600, dur = 0.12, type = 'sine', when = 0) {
    if (!soundOn) return;
    try {
        const ctx = beep.ctx || (beep.ctx = new (window.AudioContext || window.webkitAudioContext)());
        const o = ctx.createOscillator(), g = ctx.createGain();
        o.type = type; o.frequency.value = freq;
        g.gain.setValueAtTime(0.001, ctx.currentTime + when);
        g.gain.exponentialRampToValueAtTime(0.3, ctx.currentTime + when + 0.02);
        g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + when + dur);
        o.connect(g); g.connect(ctx.destination);
        o.start(ctx.currentTime + when); o.stop(ctx.currentTime + when + dur + 0.05);
    } catch (e) {}
}
const sndPick = () => beep(520, .1);
const sndGood = () => { beep(660,.12); beep(880,.15,'sine',.12); beep(1320,.2,'sine',.24); };
const sndBad = () => beep(180, .3, 'sawtooth');
const sndTick = () => beep(900, .05, 'square');
const sndLaunch = () => { beep(700, .1); beep(950, .12, 'sine', .1); };

function showBanner(msg) {
    if (!msg) return;
    banner.textContent = '📣 ' + msg;
    banner.classList.remove('hidden');
    clearTimeout(showBanner.t);
    showBanner.t = setTimeout(() => banner.classList.add('hidden'), 6000);
}

function startTimer(seconds) {
    clearInterval(timer);
    totalTime = seconds; timeLeft = seconds;
    timerBar.style.width = '100%';
    tlabel.textContent = `⏱ ${timeLeft}s`;
    tlabel.classList.remove('text-red-600');
    timer = setInterval(() => {
        timeLeft--;
        if (timeLeft <= 5 && timeLeft > 0) { sndTick(); tlabel.classList.add('text-red-600'); }
        if (timeLeft <= 0) {
            clearInterval(timer);
            tlabel.textContent = '⏱ ¡tiempo!';
            timerBar.style.width = '0%';
            reveal({ correctIndex: pendingAnswer, fact: pendingFact, auto: true });
            return;
        }
        tlabel.textContent = `⏱ ${timeLeft}s`;
        timerBar.style.width = (timeLeft / totalTime * 100) + '%';
    }, 1000);
}

function renderQuestion(p) {
    picked = -1; correctIndex = -1; locked = false; revealed = false;
    pendingAnswer = (typeof p.correctIndex === 'number') ? p.correctIndex : -1;
    pendingFact = p.fact || '';
    pendingCat = p.category || '';
    idle.classList.add('hidden'); play.classList.remove('hidden');
    fact.classList.add('hidden');
    card.classList.remove('shake'); card.classList.remove('pop'); void card.offsetWidth; card.classList.add('pop');
    qn.textContent = `PREGUNTA ${p.index + 1}/${p.total}`;
    cat.textContent = pendingCat;
    qcount.textContent = `${p.index + 1} / ${p.total}`;
    qtext.textContent = p.question;
    opts.innerHTML = '';
    const letters = ['A','B','C','D'];
    const colors = [
        'bg-violet-100 border-violet-300 hover:bg-violet-200',
        'bg-sky-100 border-sky-300 hover:bg-sky-200',
        'bg-amber-100 border-amber-300 hover:bg-amber-200',
        'bg-rose-100 border-rose-300 hover:bg-rose-200'
    ];
    p.options.forEach((op, i) => {
        const b = document.createElement('button');
        b.className = `opt text-left rounded-2xl border-2 px-4 py-3 font-bold flex items-center gap-3 ${colors[i % 4]} focus:outline-none focus:ring-2 focus:ring-fuchsia-400`;
        b.innerHTML = `<span class="letter-badge shrink-0 rounded-xl bg-slate-900 text-white flex items-center justify-center font-extrabold text-base">${letters[i]}</span><span>${op}</span>`;
        b.addEventListener('click', () => pick(i, b));
        opts.appendChild(b);
    });
    sndLaunch();
    startTimer(p.seconds || 15);
}

function pick(i, btn) {
    if (locked || revealed) return;
    picked = i; sndPick();
    const letters = ['A','B','C','D'];
    [...opts.children].forEach((c, j) => {
        c.classList.remove('ring-4', 'ring-fuchsia-400', 'pulse-ring');
        if (j === i) {
            c.classList.add('ring-4', 'ring-fuchsia-400', 'pulse-ring');
        }
    });
    statusEl.textContent = `🔒 Elegiste ${letters[i]} — podés cambiar hasta que acabe el tiempo o el anfitrión revele.`;
}

function lockOptions() { locked = true; [...opts.children].forEach(c => c.disabled = true); }

function reveal(p) {
    if (revealed) return;
    revealed = true;
    correctIndex = (typeof p.correctIndex === 'number') ? p.correctIndex : pendingAnswer;
    clearInterval(timer); lockOptions();
    const kids = [...opts.children];
    kids.forEach((c, j) => {
        c.disabled = true;
        c.classList.remove('pulse-ring');
        if (j === correctIndex) {
            c.className = 'opt text-left rounded-2xl border-2 px-4 py-3 font-bold flex items-center gap-3 bg-emerald-500 border-emerald-600 text-white pop';
        } else if (j === picked) {
            c.className = 'opt text-left rounded-2xl border-2 px-4 py-3 font-bold flex items-center gap-3 bg-red-500 border-red-600 text-white shake';
        } else {
            c.classList.add('opacity-50');
        }
    });
    if (picked === correctIndex && picked >= 0) {
        const bonus = Math.max(0, timeLeft) * 2;
        const gained = 10 + bonus + Math.min(streak, 5) * 2;
        score += gained; streak++;
        sndGood(); confettiBurst();
        statusEl.textContent = `✅ ¡Correcto! +${gained} pts${bonus ? ` (bonus velocidad +${bonus})` : ''}${streak > 1 ? ` · Racha ${streak} 🔥` : ''}`;
    } else if (picked < 0) {
        streak = 0; sndBad();
        statusEl.textContent = `⏱ Sin respuesta. Era la opción ${['A','B','C','D'][correctIndex]}.`;
    } else {
        streak = 0; sndBad(); card.classList.add('shake');
        statusEl.textContent = `❌ Casi… era la opción ${['A','B','C','D'][correctIndex]}.`;
    }
    paint();
    if (p.fact) { fact.textContent = '💡 ' + p.fact; fact.classList.remove('hidden'); }
}

function resetGame() {
    clearInterval(timer);
    idle.classList.remove('hidden'); play.classList.add('hidden');
    qcount.textContent = '— / —';
    cat.textContent = '';
    statusEl.textContent = '🔄 Juego reiniciado. Esperando pregunta…';
    picked = -1; correctIndex = -1; locked = true; revealed = false;
}

// Confetti
const cv = $('confetti'), cx = cv.getContext('2d');
let parts = [];
function sizeCanvas() { cv.width = innerWidth; cv.height = innerHeight; }
addEventListener('resize', sizeCanvas); sizeCanvas();
function confettiBurst() {
    const colors = ['#f0abfc','#fbbf24','#34d399','#60a5fa','#fb7185','#a78bfa'];
    for (let i = 0; i < 120; i++) {
        parts.push({ x: innerWidth/2 + (Math.random()-.5)*200, y: innerHeight*0.35,
            vx: (Math.random()-.5)*12, vy: Math.random()*-9-2, s: Math.random()*8+3,
            c: colors[i % colors.length], r: Math.random()*Math.PI, vr: (Math.random()-.5)*.3, life: 90 });
    }
    if (!confettiBurst.raf) confettiLoop();
}
function confettiLoop() {
    confettiBurst.raf = requestAnimationFrame(confettiLoop);
    cx.clearRect(0, 0, cv.width, cv.height);
    parts = parts.filter(p => p.life > 0);
    if (!parts.length) { cancelAnimationFrame(confettiBurst.raf); confettiBurst.raf = null; return; }
    for (const p of parts) {
        p.x += p.vx; p.y += p.vy; p.vy += .35; p.r += p.vr; p.life--;
        cx.save(); cx.translate(p.x, p.y); cx.rotate(p.r);
        cx.fillStyle = p.c; cx.fillRect(-p.s/2, -p.s/2, p.s, p.s*0.6);
        cx.restore();
    }
}

// Reverb
document.addEventListener('DOMContentLoaded', () => {
    try {
        window.Echo.channel('game-channel').listen('.GameUpdate', (e) => {
            conn.textContent = '● en vivo';
            conn.className = 'text-xs text-emerald-300 mt-1 font-bold';
            if (e.type === 'question') { statusEl.textContent = '🆕 ¡Nueva pregunta! Elegí rápido.'; renderQuestion(e.payload); }
            else if (e.type === 'reveal') { reveal(e.payload); }
            else if (e.type === 'reset') { resetGame(); }
            else if (e.type === 'message') { showBanner(e.payload.message); beep(780, .12); }
        });
        statusEl.textContent = '✅ Conectado. Esperando al anfitrión…';
        conn.textContent = '● conectado';
        conn.className = 'text-xs text-emerald-300 mt-1 font-bold';
    } catch (err) {
        statusEl.textContent = '⚠️ No se pudo conectar al WebSocket. Revisá Reverb :8080.';
    }
});

// Latido: mantiene viva la sesión (y la reserva del lugar si sos anfitrión).
const CSRF_TRIVIA = document.querySelector('meta[name="csrf-token"]')?.content || '';
setInterval(async () => {
    try {
        const res = await fetch('{{ route('juego.emitir') }}', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_TRIVIA, 'Accept': 'application/json' },
            body: JSON.stringify({ type: 'heartbeat' })
        });
        const data = await res.json().catch(() => ({}));

        // El anfitrión lleva más de 1 minuto sin señal: pasamos a mandar nosotros.
        if (data.promoted) {
            say('👑 El anfitrión se desconectó. Ahora mandás vos.');
            setTimeout(() => { location.href = '{{ route('juego.emisor') }}'; }, 1200);
        }
    } catch (e) {}
}, 5000);
</script>
</body>
</html>