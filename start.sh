#!/bin/bash
# Levanta el proyecto emisor-reseptor (Laravel + MariaDB + MongoDB + Nginx).
# Uso: ./start.sh [build|up|down|restart|logs|install|migrate|test|shell|help]
set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$PROJECT_DIR"

DC="docker compose"

show_help() {
    echo "Uso: ./start.sh [comando]"
    echo ""
    echo "  build     - Reconstruir imagenes (php, reverb, queue)"
    echo "  up        - Levantar contenedores (por defecto)"
    echo "  down      - Detener y eliminar contenedores"
    echo "  restart   - Reiniciar contenedores"
    echo "  logs      - Ver logs (follow)"
    echo "  install   - composer install + npm install (host) + build assets"
    echo "  migrate   - Permisos + migraciones + storage:link"
    echo "  test      - Verificacion completa: instala, migra, tests, HTTP 200"
    echo "  shell     - Entrar al contenedor PHP"
    echo "  help      - Esta ayuda"
}

wait_for_db() {
    echo "⏳ Esperando MariaDB..."
    for i in $(seq 1 30); do
        if $DC exec -T mariadb mariadb-admin ping -hjorge -p123456789 --silent 2>/dev/null | grep -q "alive\|OK"; then
            echo "✅ MariaDB lista"
            break
        fi
        # fallback: probar con root
        if $DC exec -T mariadb mariadb-admin ping -hlocalhost -uroot -p123456789 --silent 2>/dev/null | grep -qi "alive"; then
            echo "✅ MariaDB lista"
            break
        fi
        sleep 2
        if [ "$i" = "30" ]; then echo "⚠️  MariaDB no responde, sigo igual"; fi
    done
}

fix_perms() {
    echo "🔧 Arreglando permisos storage/bootstrap/cache..."
    chmod -R 777 app/storage app/bootstrap/cache 2>/dev/null || true
}

do_build() {
    echo "🔨 Construyendo imagenes (php, reverb, queue)..."
    $DC build
}

do_up() {
    echo "🚀 Levantando contenedores..."
    $DC up -d
    wait_for_db
    fix_perms
    echo ""
    echo "📋 URLs:"
    echo "   - App:              http://localhost:8343"
    echo "   - Emisor:           http://localhost:8343/emisor"
    echo "   - Receptor:         http://localhost:8343/receptor"
    echo "   - Trivia:           http://localhost:8343/juego"
    echo "   - Trivia Emisor:    http://localhost:8343/juego-emisor"
    echo "   - Trivia Receptor:  http://localhost:8343/juego-receptor"
    echo "   - Batalla Naval:    http://localhost:8343/batalla"
    echo "   - Batalla Emisor:   http://localhost:8343/batalla-emisor"
    echo "   - Batalla Receptor: http://localhost:8343/batalla-receptor"
    echo "   - Reverb (WS):      ws://localhost:8080"
    echo "   - Adminer:          http://localhost:5436"
    echo "   - Mongo Express:    http://localhost:5323 (admin/admin123)"
}

do_install() {
    fix_perms
    if [ ! -f app/.env ]; then
        echo "📝 Creando app/.env desde .env.example..."
        cp app/.env.example app/.env
        $DC exec -T php php artisan key:generate
    fi
    echo "📦 composer install (dentro de php)..."
    $DC exec -T php composer install --no-interaction
    if command -v npm >/dev/null 2>&1; then
        echo "📦 npm install + build (en host, carpeta app)..."
        (cd app && npm install && npm run build)
    else
        echo "⚠️  npm no encontrado en el host: emisor/receptor necesitan assets compilados"
        exit 1
    fi
}

do_migrate() {
    fix_perms
    echo "🗄️  Limpiando cache de config..."
    $DC exec -T php php artisan config:clear
    echo "🗄️  Migrando..."
    $DC exec -T php php artisan migrate --force
    echo "🔗 storage:link..."
    $DC exec -T php php artisan storage:link || true
    $DC exec -T php php artisan config:clear
}

do_test() {
    if ! $DC ps --status running | grep -q "emisor"; then
        echo "⚠️  Contenedores apagados, levantando..."
        do_up
    fi
    do_install
    do_migrate
    echo "🧪 php artisan test..."
    $DC exec -T php php artisan test
    echo "🌐 Chequeo HTTP http://localhost:8343/emisor ..."
    sleep 2
    CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8343/emisor --max-time 15 || echo "000")
    if [ "$CODE" = "200" ]; then
        echo "✅ App responde HTTP 200 en http://localhost:8343/emisor"
    else
        echo "❌ App devolvio HTTP $CODE (esperado 200). Logs:"
        $DC logs --tail=30 php nginx
        exit 1
    fi
    echo "📡 Chequeo Reverb: GET /broadcast-test + job DONE en queue ..."
    RESP=$(curl -s http://localhost:8343/broadcast-test --max-time 15 || echo "ERROR")
    echo "   Respuesta: $RESP"
    sleep 8
    if $DC logs queue --tail=5 2>/dev/null | grep -q "DONE"; then
        echo "✅ Broadcast procesado por queue (DONE)"
    else
        echo "❌ El job de broadcast no se proceso. Logs:"
        $DC logs --tail=30 queue reverb
        exit 1
    fi
    echo ""
    echo "✅ Todo OK"
}

COMMAND=${1:-up}
case $COMMAND in
    build)   do_build ;;
    up)      do_up ;;
    down)    echo "🛑 Bajando..."; $DC down ;;
    restart) echo "🔄 Reiniciando..."; $DC down; do_up ;;
    logs)    $DC logs -f ;;
    install) do_install ;;
    migrate) do_migrate ;;
    test)    do_test ;;
    shell)   $DC exec php bash ;;
    help|--help|-h) show_help ;;
    *) echo "❌ Comando desconocido: $COMMAND"; show_help; exit 1 ;;
esac
