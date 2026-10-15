# emisor-reseptor

Aplicacion Laravel con Docker: PHP 8.5-FPM + Nginx + MariaDB + MongoDB.
Incluye Adminer (administracion MySQL) y Mongo Express (administracion MongoDB).

## 📚 Documentación

| Documento | Qué contiene |
|---|---|
| **[DOCUMENTACION.md](DOCUMENTACION.md)** | Qué construimos: arquitectura, reglas de cada juego, mapa de archivos, bitácora de bugs resueltos |
| **[DEPLOY.md](DEPLOY.md)** | Cómo levar el proyecto a **otro servidor**: requisitos, despliegue, dominio/HTTPS, backups, solución de problemas |

## Juegos en vivo (emisor → Reverb → receptor)

| Juego | Descripción | URLs |
|-------|-------------|------|
| ⚡ Trivia Relámpago | 12 preguntas, timer, rachas, confetti, auto-revelado | `/juego`, `/juego-emisor`, `/juego-receptor` |
| 🚢 Batalla Naval | 2 jugadores, colocación de barcos, turnos, hundir flota | `/batalla`, `/batalla-emisor`, `/batalla-receptor` |

Ambos juegos usan la misma arquitectura: el **emisor** (anfitrión) controla el juego y el **receptor** (jugador) ve todo en tiempo real vía WebSocket (Reverb :8080).

### Roles automáticos (anfitrión / invitado)

El rol se asigna solo al entrar por `/juego` o `/batalla`: **el primero que llega es
anfitrión** y los demás son invitados. No hay que elegir nada a mano.

Como la sesión es propia de cada navegador, el "lugar de anfitrión" se guarda en la
**cache de la base de datos** (`App\Services\HostSlot`), que sí es compartida entre
navegadores. Mientras el anfitrión tiene la pestaña abierta manda un latido cada
5 segundos que renueva la reserva. Si deja de mandarlos durante **1 minuto**
(cerró la pestaña, se colgó o se fue internet), el invitado que esté conectado
**toma el control automáticamente**. También se puede tomar a mano con el botón
**"👑 Tomar el lugar del anfitrión"**, que solo aparece cuando no hay anfitrión.

### Batalla Naval: el servidor manda

A diferencia del Trivia, en Batalla Naval **el estado vive en el servidor**
(tabla `battleship_games`). Eso es fundamental: el impacto de cada disparo lo
resuelve el servidor comparando contra la flota del defensor, y recién ahí se
difunde el resultado por WebSocket. Si el impacto lo calculara cada navegador, el
jugador que dispara nunca sabría si acertó y la partida quedaría trabada.

Reglas:

- Tablero 10×10, 6 barcos = **20 casillas** (5+4+3+3+3+2).
- Impacto = repetís turno; agua = pasa el turno.
- Un barco se marca 🔥 cuando todas sus casillas están impactadas.
- Gana quien hunde las 20 casillas del rival.
- La flota del rival **no se envía** al navegador mientras la partida está en curso;
  solo se revela al terminar (evita hacer trampa con el devtools).

### Trivia Relámpago ⚡
- El anfitrión lanza preguntas con timer (10/15/20/30s).
- Los jugadores eligen opción (A/B/C/D) contra reloj.
- Puntos: 10 base + bonus velocidad + bonus racha.
- Auto-revelado si el anfitrión no revela a tiempo.
- Categorías: Web, Laravel, Docker, Ciencia, General, Backend.

### Batalla Naval 🚢
- Tablero 10×10, 6 barcos (5+4+3+3+3+2 = **20 casillas**).
- Colocación por click con vista previa del barco, o botón 🎲 Aleatorio; rotación con H.
- Turnos alternados: impacto = disparo extra, agua = pasa el turno.
- Gana quien hunde las 20 casillas enemigas primero.
- Canal propio: `battleship-channel`.

## Servicios y puertos

| Servicio      | Imagen         | Puerto host | URL / acceso                          |
|---------------|----------------|-------------|---------------------------------------|
| App (Nginx)   | nginx:alpine   | 8343        | http://localhost:8343                 |
| PHP-FPM       | build local    | -           | interno, puerto 9000                  |
| Reverb (WS)   | build local    | 8080        | ws://localhost:8080                   |
| Queue worker  | build local    | -           | procesa jobs y broadcasts             |
| MariaDB       | mariadb:11     | -           | interno `mariadb:3306`                |
| MongoDB       | mongo:8        | -           | interno `mongodb:27017`               |
| Adminer       | adminer        | 5436        | http://localhost:5436                 |
| Mongo Express | mongo-express  | 5323        | http://localhost:5323 (admin/admin123)|

Credenciales de base de datos (desarrollo, ver `docker-compose.yml`):

- MariaDB: base `emisor-reseptor-db`, usuario `jorge`, clave `123456789`, root `123456789`.
- MongoDB: usuario `jorge`, clave `123456789`.

## Requisitos

- Docker Engine 24+ con plugin `docker compose` (`docker compose version` debe responder).
- Node.js + npm en el host (solo para compilar los assets con Vite).
- Puertos libres: 8343, 8080 (Reverb), 5436, 5323.
- No se necesita PHP ni Composer instalados: corren dentro del contenedor `php`.

## Inicio rapido (recomendado)

```bash
./start.sh up     # levanta todo y muestra las URLs
./start.sh test   # instalacion + migraciones + tests + chequeo HTTP 200
```

Comandos disponibles en `start.sh`:

| Comando           | Que hace                                                        |
|-------------------|-----------------------------------------------------------------|
| `./start.sh up`   | Levanta los contenedores (comando por defecto)                  |
| `./start.sh test` | Verificacion completa: dependencias, migraciones, tests, HTTP   |
| `./start.sh build`| Reconstruye la imagen PHP                                       |
| `./start.sh down` | Detiene y elimina los contenedores (los datos persisten)        |
| `./start.sh restart` | Reinicia todo                                                |
| `./start.sh logs` | Muestra los logs en vivo                                        |
| `./start.sh install` | Instala dependencias (composer + npm + build)                |
| `./start.sh migrate` | Ejecuta migraciones y crea el link de storage                |
| `./start.sh shell` | Abre una terminal dentro del contenedor PHP                    |

## Paso a paso manual

Si preferis no usar `start.sh`, estos son los pasos que ejecuta por dentro.

### 1. Clonar y entrar al proyecto

```bash
git clone <url-del-repo>
cd emisor-reseptor
```

### 2. Crear el `.env` y la clave de la app

`.env`, `vendor/`, `node_modules/` y `public/build/` no se suben al repo
(ver `app/.gitignore`), asi que en un clon fresco hay que generarlos:

```bash
cp app/.env.example app/.env
docker compose up -d php
docker compose exec php php artisan key:generate
```

`./start.sh install` tambien hace esto solo si falta `app/.env`.
`app/.env.example` ya trae los valores correctos para Docker
(MariaDB, MongoDB y Reverb); no hace falta editar nada para desarrollo.

### 3. Levantar los contenedores

```bash
docker compose up -d
```

La primera vez construye las imagenes PHP (puede tardar varios minutos porque
compila las extensiones `mongodb` y `pcntl`). Las siguientes veces usa cache
y es rapido.

Verificar que esten corriendo y saludables:

```bash
docker compose ps
```

`mariadb`, `mongodb` y `reverb` deben decir `(healthy)`.

### 4. Permisos de Laravel

Los directorios `app/storage` y `app/bootstrap/cache` deben ser escribibles
por el usuario `www-data` (con el que corre php-fpm dentro del contenedor).
El contenedor lo arregla solo al arrancar (ver `docker/php/entrypoint.sh`),
pero si ves un error 500, forzalo asi:

```bash
chmod -R 777 app/storage app/bootstrap/cache
```

### 5. Variables de entorno

El archivo `app/.env` (creado en el paso 2 desde `.env.example`) ya viene
configurado para Docker:

```ini
APP_URL=http://localhost:8343

DB_CONNECTION=mysql
DB_HOST=mariadb
DB_PORT=3306
DB_DATABASE=emisor-reseptor-db
DB_USERNAME=jorge
DB_PASSWORD=123456789

MONGODB_URI=mongodb://jorge:123456789@mongodb:27017
MONGODB_DATABASE=laravel
```

Importante: `DB_HOST` es `mariadb` (nombre del servicio en Docker), no
`localhost`. Si copiaste `.env.example`, ajusta estos valores.

### 6. Instalar dependencias PHP

```bash
docker compose exec php composer install
```

### 7. Migraciones y storage

```bash
docker compose exec php php artisan config:clear
docker compose exec php php artisan migrate --force
docker compose exec php php artisan storage:link
```

### 8. Assets frontend (obligatorio para emisor/receptor)

Las vistas `emisor` y `receptor` usan `@vite` directamente: sin assets
compilados devuelven error 500. Compilar en el host (la imagen PHP no
incluye Node):

```bash
cd app && npm install && npm run build
```

Esto requiere `laravel-echo` y `pusher-js`, que ya estan en
`app/package.json`. Si `npm install` falla con `EACCES` sobre
`node_modules/`, es porque esa carpeta quedo con dueño `root` (paso con
un `npm` corrido con sudo); devolverla a tu usuario asi:

```bash
docker compose exec -T php chown -R $(id -u):$(id -g) /var/www/html/node_modules
```

Nota: `npm` se ejecuta en el host, no dentro del contenedor `php`.

### 9. Verificar que funciona

```bash
docker compose exec php php artisan test
curl -s -o /dev/null -w "%{http_code}\n" http://localhost:8343/emisor
curl -s http://localhost:8343/broadcast-test
docker compose logs queue --tail=3
```

Esperado: tests en verde (`4 passed`), `200` en `/emisor`,
`{"ok":true,"channel":"style-channel"}` y el job en `DONE` (no `FAIL`).
Abrir http://localhost:8343/emisor y http://localhost:8343/receptor en el
navegador: al enviar desde el emisor, el receptor se actualiza al instante.

Atajo: `./start.sh test` hace los pasos 6, 7 (install), 9 y el chequeo de
Reverb de una sola vez.

## Broadcasting con Reverb (tiempo real)

El proyecto usa [Laravel Reverb](https://laravel.com/docs/reverb) para
WebSockets: el **emisor** (`/emisor`) publica el evento `RemoteStyleUpdate`
en el canal publico `style-channel` y el **receptor** (`/receptor`) lo escucha
con Laravel Echo y actualiza el DOM.

Servicios involucrados:

- `reverb`: servidor WebSocket (`php artisan reverb:start`, puerto 8080).
- `queue`: worker (`php artisan queue:work`) que procesa los jobs de
  broadcast (los eventos `ShouldBroadcast` van a la cola `database`).

Variables clave en `app/.env` (ya configuradas):

```ini
REVERB_HOST=reverb        # red Docker: asi llega el servidor PHP a Reverb
REVERB_PORT=8080
REVERB_SCHEME=http
VITE_REVERB_APP_KEY=putziopmklgcyda1gg98
VITE_REVERB_HOST=localhost  # navegador: valores explicitos (Vite no expande ${...})
VITE_REVERB_PORT=8080
VITE_REVERB_SCHEME=http
```

Si cambias `app/.env`, reconstruir assets y limpiar config:

```bash
cd app && npm run build
docker compose exec php php artisan config:clear
docker compose up -d --force-recreate reverb
```

Probar el flujo completo:

1. Abrir http://localhost:8343/receptor en el navegador (debe decir
   "Esperando mensaje...").
2. Abrir http://localhost:8343/emisor, escribir texto y enviar.
3. El receptor se actualiza al instante sin recargar.

Prueba automatizada (sin navegador):

```bash
curl -s http://localhost:8343/broadcast-test
# {"ok":true,"channel":"style-channel"}
docker compose logs queue --tail=3   # el job debe decir DONE, no FAIL
```

Si el job dice `FAIL`, ver el motivo:

```bash
docker compose exec php php artisan queue:flush  # limpia fallidos viejos
docker compose exec php php artisan tinker --execute='$j = DB::table("failed_jobs")->latest("failed_at")->first(); echo $j ? $j->exception : "sin fallidos";'
```

Causas tipicas: servidor `reverb` apagado (`Could not resolve host: reverb`)
o credenciales `REVERB_APP_*` distintas entre `.env` y el servidor.

## Estructura del repositorio

```text
.
├── start.sh                 # script de ayuda (up/test/build/down/...)
├── docker-compose.yml       # servicios + healthchecks (php, nginx, reverb, queue, mariadb, mongodb, adminer, mongo-express)
├── docker/
│   ├── php/
│   │   ├── Dockerfile       # PHP 8.5-FPM + pdo_mysql, intl, zip, bcmath, exif, pcntl + ext-mongodb con SSL
│   │   └── entrypoint.sh    # fija permisos de storage/cache al arrancar
│   └── nginx/
│       └── default.conf     # vhost: root en public/, PHP via fastcgi a php:9000
└── app/                     # aplicacion Laravel
    ├── .env                 # config local (NO se sube al repo, ver .gitignore)
    ├── .env.example         # plantilla con valores Docker (SI se sube)
    ├── app/Events/RemoteStyleUpdate.php  # evento broadcast (canal style-channel)
    └── resources/js/echo.js # cliente Laravel Echo (reverb, ws localhost:8080)
```

## Solucion de problemas

### Error 500 `tempnam(): file created in the system's temporary directory`

Son los permisos de `storage/` y `bootstrap/cache/` (ver paso 3).
El contenedor `php` corre como `www-data` y no puede escribir si esas
carpetas pertenecen a otro usuario sin permiso de escritura.

### Error 502 Bad Gateway despues de recrear el contenedor `php`

Nginx resuelve el nombre `php` una sola vez al arrancar. Si el contenedor
`php` se recreo (nueva IP), reiniciar Nginx:

```bash
docker compose restart nginx
```

### La app no conecta a MariaDB

Verificar que el servicio este saludable y las credenciales coincidan
con `app/.env`:

```bash
docker compose exec mariadb mariadb-admin ping -hlocalhost -uroot -p123456789
docker compose logs mariadb --tail=20
```

### Error de autenticacion MongoDB desde PHP

La extension `mongodb` debe compilarse con soporte SSL (`libssl-dev`
en el Dockerfile). Si falla con `SCRAM_SHA_256 ... requires ENABLE_SSL`,
reconstruir todo y recrear:

```bash
docker compose build
docker compose up -d --force-recreate php nginx reverb queue
```

### Reverb en loop `Restarting` (`Undefined constant SIGINT`)

Falta la extension `pcntl` en la imagen (Reverb la exige). Ya esta en el
Dockerfile; si ves este error es porque los contenedores usan una imagen
vieja. Importante: `reverb` y `queue` tienen imagen propia, no alcanza con
reconstruir solo `php`:

```bash
docker compose build
docker compose up -d --force-recreate php nginx reverb queue
```

### El mensaje no llega al receptor (job `FAIL` en queue)

Ver el motivo exacto:

```bash
docker compose exec php php artisan tinker --execute='$j = DB::table("failed_jobs")->latest("failed_at")->first(); echo $j ? $j->exception : "sin fallidos";'
docker compose exec php php artisan queue:flush  # limpia fallidos viejos
```

- `Could not resolve host: reverb` → el servidor Reverb esta apagado:
  `docker compose up -d reverb`.
- Tras corregirlo, reintentar dispara de nuevo desde `/emisor` o
  `GET /broadcast-test` (los fallidos viejos no se reintentan solos).

### Puerto ocupado

Si otro proceso usa el 8343/5436/5323, o cambias el puerto en
`docker-compose.yml`, actualiza `APP_URL` en `app/.env` al mismo puerto.

### Empezar de cero (borra tambien los datos)

```bash
docker compose down -v
./start.sh up
./start.sh migrate
```

## Notas para produccion

- Cambiar todas las claves de `docker-compose.yml` y `app/.env`.
- Poner `APP_DEBUG=false` y `APP_ENV=production`.
- No exponer Adminer ni Mongo Express, o protegerlos detras de autenticacion.
- Compilar assets (`npm run build`) y cachear config:
  `php artisan config:cache route:cache view:cache`.
