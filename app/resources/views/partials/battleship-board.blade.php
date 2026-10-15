{{-- Tablero de Batalla Naval. $role = 'host' | 'guest' --}}
@php
    $isHost = $role === 'host';
@endphp

<div class="mx-auto max-w-6xl px-4 py-6" id="bs" data-role="{{ $role }}">

    {{-- Banner de rol --}}
    <div class="mb-4 rounded-2xl border-2 p-4 text-center {{ $isHost
        ? 'bg-gradient-to-r from-blue-600/30 to-indigo-600/30 border-blue-400/50'
        : 'bg-gradient-to-r from-amber-600/30 to-orange-600/30 border-amber-400/50' }}">
        <h1 class="text-3xl font-extrabold {{ $isHost ? 'text-blue-300' : 'text-amber-300' }}">
            {{ $isHost ? '⚓ ANFITRIÓN' : '🎯 INVITADO' }}
        </h1>
        <p class="text-sm {{ $isHost ? 'text-blue-200' : 'text-amber-200' }}">
            {{ $isHost ? 'Mandatás la flota y disparás primero' : ' defendés tu flota y esperás el turno' }}
        </p>
    </div>

    @if (session('info'))
        <div class="mb-4 rounded-xl border border-sky-400/40 bg-sky-500/15 px-4 py-3 text-sm text-sky-100">
            {{ session('info') }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex gap-2">
            {{-- Solo el anfitrión tiene una "otra vista" para abrir: la del
                 invitado. Si sos invitado, para subir a anfitrión está el
                 botón de la corona; un enlace que rebota sería confuso. --}}
            @if ($isHost)
                <a href="{{ route('batalla.receptor') }}" target="_blank"
                   class="rounded-xl bg-amber-500 px-4 py-2 text-sm font-bold text-slate-950 transition hover:bg-amber-400">
                    🎮 Abrir la vista del invitado
                </a>
            @endif
            <a href="/" class="rounded-xl bg-white/10 border border-white/15 px-4 py-2 text-sm font-bold hover:bg-white/20 transition">
                ← Menú principal
            </a>
        </div>
        <div class="text-xs text-white/40">Canal: <code>battleship-channel</code></div>
    </div>

    {{-- Estado --}}
    <div class="mb-4 rounded-xl border px-4 py-3 text-sm {{ $isHost ? 'bg-blue-500/10 border-blue-400/30' : 'bg-amber-500/10 border-amber-400/30' }}">
        <span class="{{ $isHost ? 'text-blue-300' : 'text-amber-300' }}">Estado:</span>
        <span class="font-bold" id="bs-message">Conectando…</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
        {{-- Tu tablero --}}
        <div class="rounded-2xl border-2 p-4 {{ $isHost ? 'bg-blue-500/10 border-blue-400/30' : 'bg-amber-500/10 border-amber-400/30' }}">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-bold {{ $isHost ? 'text-blue-300' : 'text-amber-300' }}">🛡️ TU FLOTA</h2>
                <span class="rounded-full bg-black/30 px-3 py-1 text-xs font-bold text-white/70" id="bs-myready">⏳ Colocando</span>
            </div>
            <div class="overflow-x-auto">
                <div class="board-wrap" id="bs-own"></div>
            </div>
            <div class="mt-3 flex flex-wrap justify-center gap-2 text-[11px]">
                <span class="legend"><i class="sw sw-ship"></i>Tu barco</span>
                <span class="legend"><i class="sw sw-hit"></i>Te dispararon</span>
                <span class="legend"><i class="sw sw-miss"></i>Te erraron</span>
                <span class="legend"><i class="sw sw-sunk"></i>Hundido</span>
            </div>
        </div>

        {{-- Tablero enemigo --}}
        <div class="rounded-2xl border-2 border-rose-400/40 bg-rose-500/10 p-4">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-lg font-bold text-rose-300">🎯 FLOTA ENEMIGA</h2>
                <span class="rounded-full bg-black/30 px-3 py-1 text-xs font-bold text-white/70" id="bs-enemyready">⏳ Colocando</span>
            </div>
            <div class="overflow-x-auto">
                <div class="board-wrap" id="bs-enemy"></div>
            </div>
            <div class="mt-3 flex flex-wrap justify-center gap-2 text-[11px]">
                <span class="legend"><i class="sw sw-hit"></i>Impacto</span>
                <span class="legend"><i class="sw sw-miss"></i>Agua</span>
                <span class="legend"><i class="sw sw-sunk"></i>Hundido</span>
            </div>
        </div>
    </div>

    {{-- Colocación --}}
    <div class="mt-6 rounded-2xl border-2 p-4 {{ $isHost ? 'bg-blue-500/10 border-blue-400/30' : 'bg-amber-500/10 border-amber-400/30' }}">
        <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold {{ $isHost ? 'text-blue-300' : 'text-amber-300' }}">⚓ COLOCACIÓN</h2>
            <div class="flex flex-wrap gap-2">
                <button id="bs-rotate" class="btn"><span id="bs-orient">➡️ Horizontal</span> <span class="opacity-50">(H)</span></button>
                <button id="bs-undo" class="btn disabled:cursor-not-allowed disabled:opacity-40">↩️ Deshacer</button>
                <button id="bs-clear" class="btn">🗑️ Limpiar</button>
                <button id="bs-random" class="btn">🎲 Aleatorio</button>
                <button id="bs-confirm" class="rounded-xl bg-emerald-500 px-4 py-2 text-sm font-bold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-40">✅ Confirmar flota</button>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 text-sm" id="bs-fleetlist"></div>
        <p class="mt-3 text-xs text-white/50">
            Se coloca <b>un solo barco por click</b>: pasá el mouse para ver la previa y hacé click.
            Si te equivocaste, <b>hacé click sobre el barco puesto</b> para sacarlo, o usá <b>↩️ Deshacer</b>.
        </p>
    </div>

    {{-- Controles --}}
    <div class="mt-4 flex flex-wrap items-center gap-3">
        <button id="bs-start" class="hidden rounded-xl bg-sky-500 px-5 py-2.5 text-sm font-bold text-slate-950 transition hover:bg-sky-400">🚀 Iniciar partida</button>
        <span id="bs-turnmsg" class="hidden rounded-xl border px-5 py-2.5 text-sm font-bold"></span>
        {{-- Solo se muestra cuando NO hay ningún anfitrión conectado --}}
        <button id="bs-taketover" class="hidden rounded-xl border border-amber-400/40 bg-amber-500/20 px-5 py-2.5 text-sm font-bold text-amber-200 transition hover:bg-amber-500/30">
            👑 Tomar el lugar del anfitrión
        </button>
        <button id="bs-reset" class="rounded-xl border border-rose-400/30 bg-rose-500/20 px-4 py-2.5 text-sm font-bold transition hover:bg-rose-500/30">🔄 Reiniciar</button>
        <button id="bs-finish" class="rounded-xl border border-sky-400/40 bg-sky-500/20 px-4 py-2.5 text-sm font-bold text-sky-200 transition hover:bg-sky-500/30">🔚 Terminar partida</button>
        <button id="bs-surrender" class="rounded-xl border border-white/15 bg-white/10 px-4 py-2.5 text-sm font-bold transition hover:bg-white/20">🏳️ Rendirme</button>
    </div>

    {{-- Cómo se gana --}}
    <div class="mx-auto mt-5 max-w-3xl rounded-2xl border border-white/10 bg-black/25 p-4 text-xs text-white/60">
        <div class="mb-2 flex items-center gap-2">
            <span class="text-base">📖</span>
            <b class="text-sm text-white/85">Cómo se termina la partida</b>
        </div>
        <ul class="list-disc space-y-1 pl-5">
            <li>Gana quien <b>hunda las 20 casillas</b> de la flota rival (5+4+3+3+3+2).</li>
            <li>Un barco queda <b>🔥 hundido</b> cuando le pegaste a todas sus casillas.</li>
            <li><b>💥 Impacto</b> = repetís turno · <b>💨 Agua</b> = pasa el turno.</li>
            <li>Para cortar antes: <b>🔚 Terminar partida</b> gana quien más impactos tenga, y <b>🏳️ Rendirme</b> le da la victoria al rival.</li>
            <li><b>🔄 Reiniciar</b> deja todo en cero para empezar de nuevo.</li>
        </ul>
    </div>

    <div class="mx-auto mt-3 max-w-3xl">
        <div class="flex items-center gap-3 rounded-xl border border-white/10 bg-black/25 px-4 py-3">
            <div class="flex-1">
                <div class="mb-1 flex justify-between text-[11px] font-bold text-white/60">
                    <span>Tu progreso (impactos al rival)</span>
                    <span id="bs-myhits">0 / 20</span>
                </div>
                <div class="h-2.5 overflow-hidden rounded-full bg-white/10">
                    <div id="bs-mybar" class="h-full w-0 rounded-full bg-gradient-to-r from-sky-400 to-emerald-400 transition-all duration-500"></div>
                </div>
            </div>
            <div class="text-right text-[11px] text-white/50">
                <div>Impactos recibidos</div>
                <div id="bs-enemyhits" class="text-base font-bold text-rose-300">0 / 20</div>
            </div>
        </div>
    </div>

    {{-- Pantalla de resultado: aparece al terminar la partida --}}
    <div id="bs-result" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/80 p-6 backdrop-blur-sm">
        <div id="bs-result-box" class="w-full max-w-md rounded-3xl border-2 p-8 text-center shadow-2xl">
            <div id="bs-result-icon" class="text-7xl">🏆</div>
            <h2 id="bs-result-title" class="mt-3 text-4xl font-extrabold">¡GANASTE!</h2>
            <p id="bs-result-text" class="mt-2 text-sm text-white/70"></p>

            <div class="mt-5 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white/10 px-4 py-3">
                    <div class="text-[11px] uppercase tracking-wide text-white/50">Tus impactos</div>
                    <div id="bs-result-mine" class="text-3xl font-extrabold text-sky-300">0</div>
                </div>
                <div class="rounded-2xl bg-white/10 px-4 py-3">
                    <div class="text-[11px] uppercase tracking-wide text-white/50">Impactos recibidos</div>
                    <div id="bs-result-theirs" class="text-3xl font-extrabold text-rose-300">0</div>
                </div>
            </div>

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                <button id="bs-result-stay" class="rounded-xl bg-white/10 border border-white/15 px-5 py-2.5 text-sm font-bold hover:bg-white/20 transition">
                    Ver el tablero final
                </button>
                <a href="/" class="rounded-xl bg-white px-5 py-2.5 text-sm font-bold text-slate-900 transition hover:bg-white/90">
                    Volver al menú
                </a>
            </div>
            <p class="mt-4 text-xs text-white/40">
                Volviendo al menú en <span id="bs-countdown">10</span> s…
            </p>
        </div>
    </div>

    <p class="mt-4 text-center text-xs text-white/30">
        Sos el <span class="font-bold {{ $isHost ? 'text-blue-300' : 'text-amber-300' }}">{{ $isHost ? 'ANFITRIÓN' : 'INVITADO' }}</span>
    </p>
</div>

<style>
    /* ---------- Estructura ---------- */
    .board-wrap {
        position: relative;
        display: grid;
        grid-template-columns: 22px repeat(10, minmax(0, 1fr));
        gap: 2px;
        min-width: 400px;
        padding: 10px;
        border-radius: 14px;
        background:
            radial-gradient(1200px 400px at 50% -10%, rgba(56,189,248,.22), transparent 70%),
            linear-gradient(180deg, #0b2f57 0%, #062844 55%, #041b30 100%);
        box-shadow: inset 0 1px 0 rgba(255,255,255,.08), 0 8px 24px -12px rgba(0,0,0,.9);
    }
    /* Capa donde se dibujan los barcos como una ilustración continua */
    .ships-layer {
        position: absolute;
        inset: 0;
        pointer-events: none;
        z-index: 2;
        overflow: visible;
    }
    .ships-layer svg { position: absolute; overflow: visible; filter: drop-shadow(0 2px 3px rgba(0,0,0,.55)); }
    .bs-coord {
        display: flex; align-items: center; justify-content: center;
        font-size: 11px; font-weight: 800; color: rgba(148,197,255,.65);
        letter-spacing: .04em; user-select: none;
    }

    /* ---------- Celdas ---------- */
    .bs-cell {
        position: relative;
        aspect-ratio: 1;
        border-radius: 4px;
        cursor: pointer;
        user-select: none;
        display: grid;
        place-items: center;
        background: rgba(125,211,252,.13);
        box-shadow: inset 0 0 0 1px rgba(125,211,252,.18);
        transition: background .12s ease, box-shadow .12s ease, transform .1s ease;
    }
    .bs-cell.own:hover { background: rgba(125,211,252,.16); }
    .bs-cell.enemy.aim:hover {
        background: rgba(248,113,113,.32);
        box-shadow: inset 0 0 0 2px rgba(248,113,113,.85);
        transform: scale(1.07);
    }
    .bs-cell.locked { cursor: default; opacity: .82; }
    .bs-cell.aim { background: rgba(248,113,113,.12); }

    /* ---------- Barcos ---------- */
    /* Las celdas marcan la posición; el dibujo real es el <svg> que se ancla
       dentro de la primera celda de cada barco. */
    .bs-cell.ship { background: rgba(59,130,246,.14); box-shadow: inset 0 0 0 1px rgba(96,165,250,.35); }
    .ship-svg { z-index: 3; }

    /* ---------- Disparos ---------- */
    .bs-cell.miss { background: rgba(148,163,184,.30); }
    .bs-cell.miss::before {
        content: ''; position: absolute; inset: 18%;
        border: 2px solid rgba(186,230,253,.85);
        border-radius: 999px;
        animation: bs-ripple .9s ease-out;
    }
    @keyframes bs-ripple {
        from { transform: scale(.25); opacity: 1; }
        to   { transform: scale(1.5); opacity: 0; }
    }

    .bs-cell.hit, .bs-cell.sunk { background: rgba(15,23,42,.35); }
    .bs-cell.hit::before, .bs-cell.sunk::before {
        content: ''; position: absolute; inset: 6%;
        border-radius: 999px;
        background: radial-gradient(circle at 50% 55%, #fff7ae 0%, #fb923c 32%, #ef4444 62%, #7f1d1d 100%);
        box-shadow: 0 0 12px rgba(251,146,60,.85);
        animation: bs-boom .45s cubic-bezier(.2,1.4,.4,1);
    }
    .bs-cell.sunk::before {
        background: radial-gradient(circle at 50% 60%, #6b7280 0%, #374151 45%, #111827 100%);
        box-shadow: 0 0 14px rgba(107,114,128,.6);
    }
    .bs-cell.sunk::after {
        content: '🔥'; position: absolute; inset: 0; z-index: 2;
        display: grid; place-items: center; font-size: 13px;
        filter: drop-shadow(0 0 4px rgba(249,115,22,.9));
        animation: bs-flame 1.1s ease-in-out infinite alternate;
    }
    @keyframes bs-boom { 0% { transform: scale(.3); opacity: .2; } 60% { transform: scale(1.25); } 100% { transform: scale(1); opacity: 1; } }
    @keyframes bs-flame { from { transform: translateY(0) scale(1); } to { transform: translateY(-2px) scale(1.12); } }

    /* ---------- Previsualización ---------- */
    .bs-cell.ghost::before,
    .bs-cell.ghost-bad::before {
        content: ''; position: absolute; inset: 0;
        border-radius: 5px; border: 2px dashed;
        animation: none; box-shadow: none;
        background: rgba(56,189,248,.22);
        border-color: rgba(125,211,252,.9);
    }
    .bs-cell.ghost-bad::before {
        background: rgba(248,113,113,.24);
        border-color: rgba(252,165,165,.95);
    }

    /* ---------- Leyenda ---------- */
    .legend {
        display: inline-flex; align-items: center; gap: 6px;
        border: 1px solid rgba(255,255,255,.12); background: rgba(0,0,0,.28);
        border-radius: 999px; padding: 3px 10px; color: rgba(255,255,255,.78);
    }
    .sw { width: 13px; height: 13px; border-radius: 3px; display: inline-block; }
    .sw-ship { background: linear-gradient(180deg,#7dd3fc,#1d4ed8); }
    .sw-hit  { background: radial-gradient(circle,#fb923c,#7f1d1d); }
    .sw-miss { background: rgba(148,163,184,.5); border: 1px solid rgba(186,230,253,.8); }
    .sw-sunk { background: radial-gradient(circle,#6b7280,#111827); }

    .btn {
        border: 1px solid rgba(255,255,255,.15);
        background: rgba(255,255,255,.10);
        border-radius: 12px; padding: 8px 16px;
        font-size: 14px; font-weight: 700;
        transition: background .12s ease;
    }
    .btn:hover:not(:disabled) { background: rgba(255,255,255,.2); }

    .bs-pop { animation: bs-pop .35s ease-out; }
    @keyframes bs-pop { 0% { transform: scale(.5); opacity: 0; } 60% { transform: scale(1.2); } 100% { transform: scale(1); opacity: 1; } }
</style>

<script>
(function () {
    const FLEET = @json($fleet);
    const SIZE = 10;
    const root = document.getElementById('bs');
    const ME = root.dataset.role;
    const ENEMY = ME === 'host' ? 'guest' : 'host';
    const CSRF = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const URL = @json(route('batalla.emitir'));
    const RELEASE_URL = @json(route('batalla.liberar'));
    const STATE_URL = @json(route('batalla.estado'));

    let myShips = [];
    let horizontal = true;

    let view = {
        phase: 'placement', turn: 'host', winner: null,
        myShips: [], myReady: false, enemyReady: false,
        myShots: {}, enemyShots: {}, fleet: []
    };

    const $ = (id) => document.getElementById(id);
    const messageEl = $('bs-message');
    const turnMsg = $('bs-turnmsg');

    function say(msg) { messageEl.textContent = msg; }

    async function send(payload) {
        try {
            const res = await fetch(URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) say(data.error || 'No se pudo completar la acción.');

            // El servidor nos promueve: el anfitrión anterior lleva más de 1
            // minuto sin señal, así que ahora mandamos nosotros.
            if (data.promoted) {
                say('👑 El anfitrión se desconectó. Ahora mandás vos.');
                setTimeout(() => { location.href = @json(route('batalla.emisor')); }, 1200);
            }

            return data;
        } catch (e) {
            say('Error de conexión con el servidor.');
            return {};
        }
    }

    // ---------- Geometría ----------
    function cellsOf(r, c, size, horiz) {
        const out = [];
        for (let i = 0; i < size; i++) out.push(horiz ? [r, c + i] : [r + i, c]);
        return out;
    }

    function occupies(r, c, size, horiz, ships) {
        const want = cellsOf(r, c, size, horiz);
        for (const [wr, wc] of want) {
            if (wr < 0 || wr >= SIZE || wc < 0 || wc >= SIZE) return true;
            for (const s of ships) {
                for (const [sr, sc] of cellsOf(s.r, s.c, s.size, s.horizontal)) {
                    if (sr === wr && sc === wc) return true;
                }
            }
        }
        return false;
    }

    function randomFleet() {
        for (let attempt = 0; attempt < 500; attempt++) {
            const ships = [];
            let ok = true;
            for (const f of FLEET) {
                let placed = false;
                for (let t = 0; t < 200 && !placed; t++) {
                    const r = Math.floor(Math.random() * SIZE);
                    const c = Math.floor(Math.random() * SIZE);
                    const h = Math.random() > 0.5;
                    if (!occupies(r, c, f.size, h, ships)) {
                        ships.push({ r, c, size: f.size, horizontal: h });
                        placed = true;
                    }
                }
                if (!placed) { ok = false; break; }
            }
            if (ok) return ships;
        }
        return [];
    }

    // ---------- Tableros e índice de celdas ----------
    const cellIndex = {};

    function buildBoard(el) {
        el.innerHTML = '';
        for (let c = 0; c < SIZE; c++) {
            const h = document.createElement('div');
            h.className = 'bs-coord';
            h.textContent = String.fromCharCode(65 + c);
            el.appendChild(h);
        }
        for (let r = 0; r < SIZE; r++) {
            const rowLabel = document.createElement('div');
            rowLabel.className = 'bs-coord';
            rowLabel.textContent = r + 1;
            el.appendChild(rowLabel);
            for (let c = 0; c < SIZE; c++) {
                const cell = document.createElement('div');
                cell.className = 'bs-cell';
                cell.dataset.r = r; cell.dataset.c = c;
                el.appendChild(cell);
                cellIndex[el.id + ':' + r + ',' + c] = cell;
            }
        }
    }

    const ownGrid = $('bs-own');
    const enemyGrid = $('bs-enemy');
    buildBoard(ownGrid);
    buildBoard(enemyGrid);

    const cellAt = (grid, r, c) => cellIndex[grid.id + ':' + r + ',' + c] || null;

    // Mapa "r,c" -> { pos, size, horizontal } para dibujar el casco continuo.
    let shipMap = { own: {}, enemy: {} };

    function rebuildShipMap() {
        const own = {};
        for (const s of (view.myShips.length ? view.myShips : myShips)) {
            cellsOf(s.r, s.c, s.size, s.horizontal).forEach(([r, c], i) => {
                own[r + ',' + c] = { pos: i, size: s.size, horizontal: s.horizontal };
            });
        }
        const enemy = {};
        for (const s of (view.fleet || [])) {
            cellsOf(s.r, s.c, s.size, s.horizontal).forEach(([r, c], i) => {
                enemy[r + ',' + c] = { pos: i, size: s.size, horizontal: s.horizontal };
            });
        }
        shipMap = { own, enemy };
    }

    // Clase CSS de una celda: estado + forma del barco.
    function classesFor(r, c, grid) {
        const key = r + ',' + c;
        const shots = grid === 'own' ? view.enemyShots : view.myShots;
        const res = shots[key];

        if (res) return grid + ' ' + res;               // sunk | hit | miss
        if (grid === 'own' && shipMap.own[key]) return 'own ship ' + shape(shipMap.own[key]);
        if (grid === 'enemy' && view.phase === 'finished' && shipMap.enemy[key]) return 'enemy ship ' + shape(shipMap.enemy[key]);
        return grid;
    }

    function shape(s) {
        const cls = s.horizontal ? 'h' : 'v';
        let out = '';
        if (s.pos === 0) out += ' s-first';
        if (s.pos === s.size - 1) out += ' s-last';
        return cls + out;
    }

    // ---------- Dibujos de los buques ----------
    // Cada tipo tiene su propio dibujo, en un lienzo de (largo x 1) celdas.
    function shipMarkup(type, length, uid) {
        const L = length * 20;                       // ancho lógico del casco
        // IDs únicos: hay un dibujo por barco y viven en dos capas (propia y enemiga)
        const g = (n) => 'g-' + n + '-' + uid;
        const hulls = {
            // Portaaviones: cubierta de vuelo ancha con pista
            carrier: `<defs>
                    <linearGradient id="g-car-${uid}" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0" stop-color="#94a3b8"/><stop offset=".55" stop-color="#64748b"/><stop offset="1" stop-color="#334155"/>
                    </linearGradient></defs>
                <path d="M4,10 C4,5.5 7,3 12,3 L${L - 5},3 C${L - 1},3 ${L - 1},17 ${L - 5},17 L12,17 C7,17 4,14.5 4,10 Z" fill="url(#${g('car')})" stroke="#1e293b" stroke-width=".8"/>
                <rect x="10" y="5.5" width="${L - 20}" height="9" rx="1.5" fill="#334155" stroke="#0f172a" stroke-width=".6"/>
                <line x1="14" y1="10" x2="${L - 12}" y2="10" stroke="#fbbf24" stroke-width="1" stroke-dasharray="4 3"/>
                <rect x="${L - 26}" y="4" width="7" height="5" rx="1" fill="#e2e8f0" stroke="#0f172a" stroke-width=".6"/>
                <circle cx="9" cy="14.5" r="1.4" fill="#fbbf24"/><circle cx="${L - 9}" cy="14.5" r="1.4" fill="#fbbf24"/>`,

            // Acorazado: proa fina y dos torres con cañones
            battleship: `<defs>
                    <linearGradient id="g-bat-${uid}" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0" stop-color="#a5b4c4"/><stop offset=".5" stop-color="#5b6b7d"/><stop offset="1" stop-color="#2b3644"/>
                    </linearGradient></defs>
                <path d="M3,10 C3,6 6,3.5 11,3.5 L${L - 4},3.5 C${L - 1},3.5 ${L - 1},16.5 ${L - 4},16.5 L11,16.5 C6,16.5 3,14 3,10 Z" fill="url(#${g('bat')})" stroke="#1f2937" stroke-width=".8"/>
                <rect x="${L * 0.28}" y="8" width="9" height="4" rx="1" fill="#cbd5e1" stroke="#0f172a" stroke-width=".6"/>
                <rect x="${L - 20}" y="8" width="9" height="4" rx="1" fill="#cbd5e1" stroke="#0f172a" stroke-width=".6"/>
                <rect x="${L * 0.28 + 8}" y="9" width="9" height="1.6" rx=".6" fill="#e2e8f0"/>
                <rect x="${L - 12}" y="9" width="9" height="1.6" rx=".6" fill="#e2e8f0"/>
                <rect x="${L * 0.44}" y="6" width="12" height="3" rx="1" fill="#94a3b8" stroke="#0f172a" stroke-width=".5"/>`,

            // Crucero: esbelto, una torre
            cruiser: `<defs>
                    <linearGradient id="g-cru-${uid}" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0" stop-color="#bfdbfe"/><stop offset=".5" stop-color="#60a5fa"/><stop offset="1" stop-color="#1e40af"/>
                    </linearGradient></defs>
                <path d="M3,10 C3,6.5 6,4 11,4 L${L - 5},4 C${L - 1},4 ${L - 1},16 ${L - 5},16 L11,16 C6,16 3,13.5 3,10 Z" fill="url(#${g('cru')})" stroke="#1e3a8a" stroke-width=".8"/>
                <rect x="${L * 0.42}" y="7.5" width="12" height="5" rx="1.2" fill="#e2e8f0" stroke="#1e3a8a" stroke-width=".6"/>
                <rect x="${L * 0.42 + 11}" y="9" width="8" height="1.8" rx=".6" fill="#f1f5f9"/>
                <rect x="8" y="12" width="${L - 18}" height="1.6" rx=".8" fill="#1d4ed8" opacity=".5"/>`,

            // Submarino: casco redondeado amarillo con torre y periscopio
            submarine: `<defs>
                    <linearGradient id="g-sub-${uid}" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0" stop-color="#fde68a"/><stop offset=".5" stop-color="#eab308"/><stop offset="1" stop-color="#a16207"/>
                    </linearGradient></defs>
                <rect x="3" y="5" width="${L - 6}" height="10" rx="5" fill="url(#${g('sub')})" stroke="#713f12" stroke-width=".8"/>
                <rect x="${L / 2 - 6}" y="1.5" width="12" height="5" rx="1.5" fill="#78350f" stroke="#451a03" stroke-width=".5"/>
                <rect x="${L / 2 - 1}" y="-2.5" width="2" height="6" rx="1" fill="#ca8a04"/>
                <circle cx="${L / 2}" cy="-3" r="1.6" fill="#facc15"/>
                <circle cx="${L - 8}" cy="7" r="1" fill="#fef3c7"/><circle cx="${L - 8}" cy="13" r="1" fill="#fef3c7"/>`,

            // Destructor: chiquito y rápido
            destroyer: `<defs>
                    <linearGradient id="g-des-${uid}" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0" stop-color="#cbd5e1"/><stop offset=".5" stop-color="#7c8ea1"/><stop offset="1" stop-color="#3f4c5b"/>
                    </linearGradient></defs>
                <path d="M2,10 C2,6.5 5,4.5 10,4.5 L${L - 4},4.5 C${L - 1},4.5 ${L - 1},15.5 ${L - 4},15.5 L10,15.5 C5,15.5 2,13.5 2,10 Z" fill="url(#${g('des')})" stroke="#1f2937" stroke-width=".8"/>
                <rect x="${L * 0.35}" y="7" width="9" height="4" rx="1" fill="#e2e8f0" stroke="#1f2937" stroke-width=".5"/>
                <rect x="${L * 0.35 + 8}" y="8.2" width="7" height="1.5" rx=".6" fill="#f8fafc"/>`,
        };

        return hulls[type] || hulls.cruiser;
    }

    /**
     * Dibuja la flota anclando cada barco a SU primera celda y dimensionándolo
     * con CSS (n celdas de ancho). Así no hay que medir píxeles del DOM y el
     * dibujo se mantiene aunque el tablero cambie de tamaño.
     */
    function drawShips(grid, ships) {
        grid.querySelectorAll('.ship-svg').forEach(el => el.remove());

        ships.forEach((s, i) => {
            const first = cellAt(grid, s.r, s.c);
            if (!first) return;

            const type = ['carrier', 'battleship', 'cruiser', 'cruiser', 'submarine', 'destroyer'][i] || 'cruiser';
            const n = s.size;
            const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
            svg.setAttribute('class', 'ship-svg');
            svg.setAttribute('preserveAspectRatio', 'none');
            svg.innerHTML = shipMarkup(type, n, type + i);

            // El casco mide "n celdas" de largo por 1 de alto.
            const length = 'calc(' + n + ' * 100% + ' + (n - 1) * 2 + 'px)';
            const thickness = 'calc(100% + 2px)';

            if (s.horizontal) {
                svg.setAttribute('viewBox', '0 0 ' + n * 20 + ' 20');
                svg.style.cssText = 'position:absolute;left:-1px;top:-1px;width:' + length +
                    ';height:' + thickness + ';overflow:visible;pointer-events:none;' +
                    'filter:drop-shadow(0 2px 3px rgba(0,0,0,.55));';
            } else {
                // Vertical: se gira DENTRO del svg (no con CSS) para que ocupe
                // 1 celda de ancho por n de alto, proa arriba y sin deformarse.
                svg.setAttribute('viewBox', '0 0 20 ' + n * 20);
                svg.innerHTML = '<g transform="translate(20,0) rotate(90)">' + svg.innerHTML + '</g>';
                svg.style.cssText = 'position:absolute;left:-1px;top:-1px;width:' + thickness +
                    ';height:' + length + ';overflow:visible;pointer-events:none;' +
                    'filter:drop-shadow(0 2px 3px rgba(0,0,0,.55));';
            }

            first.appendChild(svg);
        });
    }

    // ---------- Pantalla de resultado ----------
    let countdownTimer = null;
    let resultHandled = false;   // ya la mostramos, o el jugador la cerró

    function showResult() {
        if (view.phase !== 'finished' || resultHandled) return;
        resultHandled = true;

        const won = view.winner === ME;
        const draw = view.winner === null;
        const box = $('bs-result-box');

        if (draw) {
            $('bs-result-icon').textContent = '🤝';
            $('bs-result-title').textContent = 'EMPATE';
            $('bs-result-title').className = 'mt-3 text-4xl font-extrabold text-amber-300';
            box.className = 'w-full max-w-md rounded-3xl border-2 border-amber-400/60 bg-amber-500/10 p-8 text-center shadow-2xl bs-pop';
            $('bs-result-text').textContent = 'Ninguno llegó a hundir la flota del otro. ¡Buena partida!';
        } else if (won) {
            $('bs-result-icon').textContent = '🏆';
            $('bs-result-title').textContent = '¡GANASTE!';
            $('bs-result-title').className = 'mt-3 text-4xl font-extrabold text-emerald-300';
            box.className = 'w-full max-w-md rounded-3xl border-2 border-emerald-400/60 bg-emerald-500/10 p-8 text-center shadow-2xl bs-pop';
            $('bs-result-text').textContent = 'Hundiste toda la flota enemiga.';
        } else {
            $('bs-result-icon').textContent = '💀';
            $('bs-result-title').textContent = 'PERDISTE';
            $('bs-result-title').className = 'mt-3 text-4xl font-extrabold text-rose-300';
            box.className = 'w-full max-w-md rounded-3xl border-2 border-rose-400/60 bg-rose-500/10 p-8 text-center shadow-2xl bs-pop';
            $('bs-result-text').textContent = view.winner
                ? 'El rival hundió tu flota entera.'
                : 'Perdiste: el rival te impactó más veces.';
        }

        $('bs-result-mine').textContent = view.myHits || 0;
        $('bs-result-theirs').textContent = view.enemyHits || 0;

        const overlay = $('bs-result');
        overlay.classList.remove('hidden');
        overlay.classList.add('flex');

        // Cuenta regresiva y cierre automático hacia el menú principal.
        let left = 10;
        $('bs-countdown').textContent = left;
        clearInterval(countdownTimer);
        countdownTimer = setInterval(() => {
            left--;
            $('bs-countdown').textContent = left;
            if (left <= 0) {
                clearInterval(countdownTimer);
                location.href = '/';
            }
        }, 1000);
    }

    function hideResult() {
        clearInterval(countdownTimer);
        const overlay = $('bs-result');
        overlay.classList.add('hidden');
        overlay.classList.remove('flex');
    }

    $('bs-result-stay').addEventListener('click', hideResult);

    // ---------- Render ----------
    function render() {
        rebuildShipMap();
        ghostEls.length = 0;

        // Al terminar la partida se muestra la pantalla de resultado.
        if (view.phase === 'finished') showResult();

        const canShoot = view.phase === 'playing' && view.turn === ME && !view.winner;

        for (let r = 0; r < SIZE; r++) {
            for (let c = 0; c < SIZE; c++) {
                const own = cellAt(ownGrid, r, c);
                const enemy = cellAt(enemyGrid, r, c);
                if (own) own.className = 'bs-cell ' + classesFor(r, c, 'own');
                if (enemy) {
                    let cl = 'bs-cell ' + classesFor(r, c, 'enemy');
                    if (canShoot && !view.myShots[r + ',' + c]) cl += ' aim';
                    else if (!canShoot) cl += ' locked';
                    enemy.className = cl;
                }
            }
        }

        drawShips(ownGrid, view.myShips.length ? view.myShips : myShips);
        drawShips(enemyGrid, view.phase === 'finished' ? (view.fleet || []) : []);

        // El botón de tomar el lugar depende de si hay anfitrión conectado.
        showTakeOver(view.hasHost === false);

        const placedCount = view.myShips.length ? view.myShips.length : myShips.length;
        const list = $('bs-fleetlist');
        list.innerHTML = '';
        FLEET.forEach((f, i) => {
            const done = i < placedCount;
            const isNext = i === placedCount;
            const div = document.createElement('div');
            div.className = 'rounded-lg border px-3 py-1.5 font-bold text-sm ' + (
                done ? 'border-emerald-400/50 bg-emerald-500/20 text-emerald-200'
                     : (isNext ? 'border-white/50 bg-white/20 text-white ring-2 ring-white/25'
                              : 'border-white/10 bg-black/25 text-white/40'));
            div.textContent = (done ? '✅ ' : (isNext ? '👉 ' : '· ')) + f.name + ' (' + f.size + ')';
            list.appendChild(div);
        });

        $('bs-myready').textContent = view.myReady ? '✅ Listo' : '⏳ Colocando';
        $('bs-enemyready').textContent = view.enemyReady ? '✅ Listo' : '⏳ Colocando';
        $('bs-orient').textContent = horizontal ? '➡️ Horizontal' : '⬇️ Vertical';
        $('bs-confirm').disabled = placedCount !== FLEET.length;
        $('bs-undo').disabled = !myShips.length;

        // Progreso: cuántas casillas le pegué al rival y cuántas me pegaron a mí
        const total = view.totalCells || 20;
        const mine = view.myHits || 0;
        const theirs = view.enemyHits || 0;
        $('bs-myhits').textContent = mine + ' / ' + total;
        $('bs-mybar').style.width = Math.min(100, (mine / total) * 100) + '%';
        $('bs-enemyhits').textContent = theirs + ' / ' + total;

        const startBtn = $('bs-start');
        if (ME === 'host' && view.phase === 'placement' && view.myReady && view.enemyReady) {
            startBtn.classList.remove('hidden');
        } else {
            startBtn.classList.add('hidden');
        }

        turnMsg.classList.add('hidden');
        if (view.phase === 'playing') {
            turnMsg.classList.remove('hidden');
            if (view.turn === ME) {
                turnMsg.textContent = '🎯 ¡Tu turno! Hacé click en la flota enemiga';
                turnMsg.className = 'rounded-xl border border-emerald-400/40 bg-emerald-500/20 px-5 py-2.5 text-sm font-bold text-emerald-200';
            } else {
                turnMsg.textContent = '⏳ Esperando turno del rival…';
                turnMsg.className = 'rounded-xl border border-white/15 bg-white/10 px-5 py-2.5 text-sm font-bold text-white/60';
            }
        }
        if (view.phase === 'finished') {
            turnMsg.classList.remove('hidden');
            const win = view.winner === ME;
            turnMsg.textContent = win ? '🏆 ¡GANASTE! Hundiste toda la flota enemiga.' : '💀 Perdiste. Te hundieron la flota.';
            turnMsg.className = 'rounded-xl border px-5 py-2.5 text-sm font-bold ' + (win
                ? 'border-emerald-400/40 bg-emerald-500/20 text-emerald-200'
                : 'border-rose-400/40 bg-rose-500/20 text-rose-200');
        }
    }

    function applyView(v) {
        view = v;

        // Si arranca una partida nueva, la pantalla de resultado se vuelve a mostrar.
        if (v.phase !== 'finished') {
            resultHandled = false;
            hideResult();
        }

        // Solo se adopta la flota del servidor si YA tiene barcos guardados.
        // Si no, se pisa lo que el jugador viene acomodando: si no, un evento
        // de estado que llega tarde (heartbeat del rival, carga inicial)
        // borraría la flota recién colocada.
        if (v.phase === 'placement' && v.myShips && v.myShips.length) {
            myShips = v.myShips;
        }

        render();
    }

    /**
     * Si la flota ya estaba confirmada en el servidor y la modificamos, hay que
     * reenviarla: si no, el servidor sigue con la flota anterior y al siguiente
     * evento de estado vuelve a aparecer la vieja.
     */
    async function syncPlacement() {
        if (!view.myReady || view.phase !== 'placement') return;
        const res = await send({ type: 'place', ships: myShips });
        if (res.ok) {
            await send({ type: 'ready' });
            say('Flota actualizada en el servidor.');
        }
    }

    // ---------- Previsualización liviana ----------
    const ghostEls = [];

    function clearGhost() {
        for (const el of ghostEls) el.classList.remove('ghost', 'ghost-bad');
        ghostEls.length = 0;
    }

    function paintGhost(r, c) {
        clearGhost();
        if (view.phase !== 'placement' || myShips.length >= FLEET.length) return;

        const next = FLEET[myShips.length];
        const bad = occupies(r, c, next.size, horizontal, myShips);

        for (const [sr, sc] of cellsOf(r, c, next.size, horizontal)) {
            if (shipMap.own[sr + ',' + sc]) continue;
            const el = cellAt(ownGrid, sr, sc);
            if (!el) continue;
            el.classList.add(bad ? 'ghost-bad' : 'ghost');
            ghostEls.push(el);
        }
    }

    ownGrid.addEventListener('mouseover', (e) => {
        const cell = e.target.closest('.bs-cell');
        if (!cell || view.phase !== 'placement') return;
        paintGhost(+cell.dataset.r, +cell.dataset.c);
    });
    ownGrid.addEventListener('mouseleave', clearGhost);

    // ---------- Colocación ----------
    ownGrid.addEventListener('click', (e) => {
        const cell = e.target.closest('.bs-cell');
        if (!cell) return;
        if (view.phase !== 'placement') { say('La partida ya empezó.'); return; }

        const r = +cell.dataset.r, c = +cell.dataset.c;

        const placed = myShips.findIndex(s =>
            cellsOf(s.r, s.c, s.size, s.horizontal).some(([sr, sc]) => sr === r && sc === c));

        if (placed !== -1) {
            const name = FLEET[placed].name;
            myShips.splice(placed, 1);
            clearGhost();
            render();
            say('Quitaste: ' + name + '. Podés volver a colocarlo.');
            syncPlacement();
            return;
        }

        if (myShips.length >= FLEET.length) { say('Ya colocaste todos los barcos.'); return; }

        const next = FLEET[myShips.length];
        if (occupies(r, c, next.size, horizontal, myShips)) {
            say('No entra ' + next.name + ' (' + next.size + ') ahí. Elegí otra casilla.');
            return;
        }

        myShips.push({ r, c, size: next.size, horizontal });
        clearGhost();
        render();
        say('✅ ' + next.name + ' colocado. Quedan ' + (FLEET.length - myShips.length) + ' barcos.');
        syncPlacement();
    });

    // ---------- Disparos ----------
    enemyGrid.addEventListener('click', (e) => {
        const cell = e.target.closest('.bs-cell');
        if (!cell) return;
        if (view.phase !== 'playing') { say('La partida todavía no empezó.'); return; }
        if (view.turn !== ME) { say('No es tu turno.'); return; }

        const r = +cell.dataset.r, c = +cell.dataset.c;
        if (view.myShots[r + ',' + c]) { say('Ya disparaste en esa casilla.'); return; }

        say('Disparando en ' + String.fromCharCode(65 + c) + (r + 1) + '…');
        send({ type: 'shot', r, c }).then(data => {
            if (data.error) say(data.error);
            else if (data.sunk) say('🔥 ¡Barco hundido!');
            else if (data.hit) say('💥 ¡Impacto! Repetís turno.');
            else say('💨 Solo agua. Pasa el turno.');
        });
    });

    // ---------- Controles ----------
    $('bs-rotate').addEventListener('click', () => { horizontal = !horizontal; render(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'h' || e.key === 'H') { horizontal = !horizontal; render(); }
    });

    $('bs-undo').addEventListener('click', () => {
        if (!myShips.length) { say('No hay barcos que sacar.'); return; }
        const removed = myShips.pop();
        clearGhost();
        render();
        say('↩️ Sacaste el barco de ' + String.fromCharCode(65 + removed.c) + (removed.r + 1) + '.');
        syncPlacement();
    });

    $('bs-clear').addEventListener('click', () => {
        myShips = [];
        clearGhost();
        render();
        say('Flota borrada. Volvé a colocar.');
        syncPlacement();
    });

    $('bs-random').addEventListener('click', () => {
        myShips = randomFleet();
        clearGhost();
        render();
        say('Flota colocada al azar. Revisala y confirmá.');
        syncPlacement();
    });

    $('bs-confirm').addEventListener('click', async () => {
        if (myShips.length !== FLEET.length) { say('Te faltan barcos.'); return; }
        say('Enviando tu flota…');
        const res = await send({ type: 'place', ships: myShips });
        if (res.ok) { await send({ type: 'ready' }); say('¡Flota confirmada! Esperando al rival.'); }
    });

    $('bs-start').addEventListener('click', async () => {
        say('Iniciando partida…');
        const res = await send({ type: 'start' });
        if (res.error) say(res.error);
    });

    // Terminar la partida ahora: gana el que más impactos tenga.
    $('bs-finish').addEventListener('click', async () => {
        if (view.phase === 'finished') { say('La partida ya terminó.'); return; }
        const ok = confirm('¿Terminar la partida?\n\nGana el jugador con más casillas impactadas.');
        if (!ok) return;
        say('Terminando la partida…');
        const res = await send({ type: 'finish' });
        if (res.error) say(res.error);
    });

    // Rendirse: le regala la victoria al rival.
    $('bs-surrender').addEventListener('click', async () => {
        if (view.phase !== 'playing') { say('No hay ninguna partida en curso.'); return; }
        if (!confirm('¿Te rendís? Gana el rival.')) return;
        say('Te estás rindiendo…');
        const res = await send({ type: 'surrender' });
        if (res.error) say(res.error);
    });

    $('bs-reset').addEventListener('click', async () => {
        myShips = [];
        clearGhost();
        await send({ type: 'reset' });
        say('Partida reiniciada. Colocá tu flota.');
    });

    $('bs-taketover').addEventListener('click', async () => {
        say('Tomando el lugar del anfitrión…');
        const res = await send({ type: 'claim-host' });
        if (res.ok) location.href = @json(route('batalla.emisor'));
        else say(res.error || 'No se pudo tomar el lugar.');
    });

    // ---------- Tiempo real ----------
    document.addEventListener('DOMContentLoaded', () => {
        try {
            window.Echo.channel('battleship-channel').listen('.BattleshipUpdate', (e) => {
                if (e.type === 'state') {
                    applyView(e.payload.views[ME]);
                    say(e.payload.message);
                } else if (e.type === 'message') {
                    say('📣 ' + e.payload.message);
                } else if (e.type === 'host-left') {
                    say('👋 El anfitrión cerró su partida.');
                    showTakeOver(true);
                } else if (e.type === 'heartbeat' && e.payload.player === 'host') {
                    lastHostBeat = Date.now();
                    showTakeOver(false);        // el anfitrión sigue vivo
                }
            });
        } catch (err) {
            say('⚠️ No se pudo conectar al WebSocket.');
        }

        fetch(STATE_URL, { headers: { 'Accept': 'application/json' } })
            .then(r => r.json())
            .then(d => { if (d.state) applyView(d.state); })
            .catch(() => {});

        setInterval(() => send({ type: 'heartbeat', player: ME }), 5000);

        // Si el anfitrión deja de mandar su latido (se colgó, cerró de golpe o
        // se fue sin avisar), mostramos el botón para tomar su lugar.
        let lastHostBeat = Date.now();
        setInterval(async () => {
            if (ME === 'host') return;
            if (Date.now() - lastHostBeat < 20000) return;

            try {
                const res = await fetch(STATE_URL, { headers: { 'Accept': 'application/json' } });
                const d = await res.json();
                if (d.state) { applyView(d.state); showTakeOver(d.state.hasHost === false); }
            } catch (e) {}
        }, 10000);

        window.addEventListener('pagehide', () => {
            if (ME !== 'host') return;
            const blob = new Blob([], { type: 'application/x-www-form-urlencoded' });
            navigator.sendBeacon && navigator.sendBeacon(RELEASE_URL, blob);
        });
    });

    // Botón para tomar el lugar: SOLO se muestra si no hay anfitrión conectado.
// El servidor lo informa con hasHost=false, y además avisa por WebSocket
// con el evento `host-left` cuando el anfitrión cierra la pestaña.
    function showTakeOver(sinAnfitrion) {
        const btn = $('bs-taketover');
        if (!btn) return;

        if (ME === 'host' || !sinAnfitrion) {
            btn.classList.add('hidden');
            return;
        }

        btn.classList.remove('hidden');
        btn.textContent = '👑 Tomar el lugar del anfitrión (no hay ninguno conectado)';
    }

    render();
})();
</script>