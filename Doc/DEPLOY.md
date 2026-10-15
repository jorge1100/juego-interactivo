# Guía de despliegue y migración

Cómo levar este proyecto a **otro servidor** (otra máquina, otro dominio, con o sin
Docker) y cómo resolver los problemas típicos.

---

## 1. Requisitos del servidor

| Necesario | Versión | Nota |
|---|---|---|
| Docker Engine | 24+ | Con el plugin `docker compose` |
| Docker Compose | v2 | `docker compose version` debe responder |
| Node.js + npm | 20+ | **Solo en el host**, para compilar los assets con Vite |
| Puertos libres | 8343, 8080, 5436, 5323 | Ver abajo si querés cambiarlos |
| RAM | 2 GB mínimo | La primera build de la imagen PHP compila extensiones |
| Disco | 5 GB | Incluye `vendor/` y `node_modules/` |

**No hace falta** PHP ni Composer instalados en el host: corren dentro del contenedor.

Verificar antes de empezar:

```bash
docker --version
docker compose version
node -v && npm -v
```

---

## 2. Despliegue rápido (con Docker)

### 2.1 Conseguir el código

Si el proyecto está en un repositorio git:

```bash
git clone <url-del-repo> juego-interactivo
cd juego-interactivo
```

Si querés moverlo **sin git**, copiá la carpeta excluyendo lo pesado:

```bash
# Desde la máquina de origen
rsync -avz --exclude 'app/vendor' --exclude 'app/node_modules' \
      --exclude 'app/.env' --exclude 'app/public/build' \
      --exclude 'app/storage/framework/*' --exclude '.git' \
      juego-interactivo/ usuario@servidor:/ruta/juego-interactivo/
```

> `app/.env` **no** se copia a propósito: cada servidor genera sus propias claves.

### 2.2 Levantar todo

```bash
./start.sh up
```

Eso hace: levanta los contenedores, espera a que MariaDB esté sana, arregla
permisos y muestra las URLs. La primera vez tarda varios minutos porque compila
la imagen de PHP (extensiones `mongodb` y `pcntl`).

### 2.3 Verificar que funciona

```bash
./start.sh test
```

Comprueba dependencias, migraciones, los 27 tests, que la app responde 200 y que
el broadcast se procesa. Debe terminar con `✅ Todo OK`.

### 2.4 Abrir en el navegador

| Qué | Dónde |
|---|---|
| Menú | `http://localhost:8343/` |
| Adminer (MySQL) | `http://localhost:5436` (admin / admin123) |
| Mongo Express | `http://localhost:5323` (admin / admin123) |

Para jugar entre dos máquinas, reemplazá `localhost` por la IP del servidor.

---

## 3. Configuración: el archivo `.env`

`app/.env` **no está en el repo** (está en `.gitignore`). Cada servidor genera
el suyo:

```bash
cp app/.env.example app/.env
docker compose up -d php
docker compose exec php php artisan key:generate
```

`./start.sh install` ya hace estos pasos solos si falta el archivo.

### Variables que hay que ajustar

| Variable | Para qué | Valor por defecto |
|---|---|---|
| `APP_URL` | URL pública de la app | `http://localhost:8343` |
| `VITE_REVERB_HOST` | **A quién se conecta el navegador** | `localhost` |
| `VITE_REVERB_SCHEME` | `https` si hay TLS | `http` |
| `DB_*` | Credenciales de MariaDB | ver abajo |
| `MONGODB_URI` | Credenciales de MongoDB | ver abajo |

> ⚠️ **Ojo con esto:** `REVERB_HOST` es el nombre del servicio **dentro de Docker**
> (`reverb`), y lo usan los contenedores PHP. En cambio `VITE_REVERB_HOST` es lo
> que ve el **navegador** (`localhost` o el dominio/IP público). No son lo mismo.

### Credenciales de base de datos

Por defecto (solo desarrollo) están en `docker-compose.yml`:

```yaml
mariadb:
  environment:
    MYSQL_ROOT_PASSWORD: 123456789
    MYSQL_DATABASE: emisor-reseptor-db
    MYSQL_USER: jorge
    MYSQL_PASSWORD: 123456789
```

Si las cambiás **después** de la primera instalación, no alcanzan: el volumen de
MariaDB ya tiene una contraseña creada. Ver [sección 7](#7-cambiar-claves-o-credenciales-tras-instalar).

---

## 4. Publicar en internet

### 4.1 Abrir los puertos

En el servidor, allow en el firewall:

```bash
sudo ufw allow 8343/tcp    # app web
sudo ufw allow 8080/tcp    # WebSockets (¡no olvidar este!)
```

### 4.2 Con IP

Solo cambiá `VITE_REVERB_HOST` por la IP del servidor y recompilá:

```bash
# Editar app/.env
#   APP_URL=http://192.168.1.50:8343
#   VITE_REVERB_HOST=192.168.1.50
cd app && npm run build
docker compose exec php php artisan config:clear
docker compose up -d --force-recreate php reverb queue
```

### 4.3 Con dominio y HTTPS (recomendado)

El problema: `laravel-echo` **no puede* usar `wss://` con `localhost`**, porque el
certificado no corresponde. Necesitás Reverb en modo producción:

```php
// docker-compose.yml → servicio reverb
command: ["php", "artisan", "reverb:start", "--host=0.0.0.0", "--port=8080"]
```

```ini
# app/.env
REVERB_SCHEME=https
REVERB_HOST=batalla.tudominio.com
REVERB_PORT=443
VITE_REVERB_HOST=batalla.tudominio.com
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=wss
```

Y un proxy inverso (nginx, Caddy o Traefik) que enrute:

| Ruta | Destino |
|---|---|
| `/` | `http://php:80` |
| `/app` (websocket) | `ws://reverb:8080` |
| `/apps` (API) | `http://reverb:8080` |

En ese caso **no** hay que exponer los puertos 8080 ni 8343: solo 80 y 443.

Después:

```bash
cd app && npm run build          # recompila con los valores nuevos
docker compose exec php php artisan config:clear
docker compose exec php php artisan reverb:install   # sin音 cambiar config, genera config/reverb.php
docker compose up -d --force-recreate reverb
```

### 4.4 Desde otra red local (casa,alución)

No hay que cambiar nada: los dos navegadores entran a `http://IP-DEL-SERVIDOR:8343`.
El WebSocket va al mismo servidor. Solo verificá que el firewall permita 8080.

---

## 5. Problemas típicos al migrar

| Síntoma | Causa | Solución |
|---|---|---|
| `Table 'sessions' doesn't exist` | No se corrieron las migraciones | `./start.sh migrate` o `docker compose exec php php artisan migrate --force` |
| `CSRF token mismatch` (error 419) | El navegador tiene el `.env` viejo compilado | `cd app && npm run build` y recargar con Ctrl+F5 |
| `Could not resolve host: reverb` | Los contenedores PHP no ven a Reverb | `docker compose up -d --force-recreate php reverb queue` |
| El navegador no conecta al WebSocket | `VITE_REVERB_HOST` apunta a `localhost` | Apuntar al dominio/IP y recompilar assets |
| `vite build` falla con `EACCES` | `node_modules` o `public/build` quedaron de `root` | `docker compose exec php chown -R $(id -u):$(id -g) /var/www/html/node_modules /var/www/html/public/build` |
| Error 502 después de recrear `php` | Nginx cacheó la IP vieja del contenedor | `docker compose restart nginx` |
| Los tests fallan con error de permisos en logs | `storage/` sin permisos de escritura | `chmod -R 777 app/storage app/bootstrap/cache` |
| Puerto ocupado | Otro proceso lo usa | Cambiar el mapeo en `docker-compose.yml` y `APP_URL` en `.env` |

---

## 6. Datos: qué se pierde y qué sobrevive

### Lo que vive en volúmenes (sobrevive a reinicios)

| Volumen | Contenido |
|---|---|
| `mariadb_data` | Usuarios, sesiones, caché, colas y **estado de las partidas** |
| `mongodb_data` | Datos de MongoDB (si se usan) |

### Lo que NO sobrevive (está ignorado por git)

| Carpeta/Archivo | Por qué | Cómo regenerarlo |
|---|---|---|
| `app/.env` | Contiene secretos | `cp app/.env.example app/.env` + `key:generate` |
| `app/vendor/` | Dependencias PHP | `docker compose exec php composer install` |
| `app/node_modules/` | Dependencias JS | `cd app && npm install` |
| `app/public/build/` | Assets compilados | `cd app && npm run build` |

### Backups

```bash
# MariaDB
docker compose exec -T mariadb mariadb-dump \
  -ujorge -p123456789 emisor-reseptor-db > backup.sql

# Restaurar
docker compose exec -T mariadb mariadb -ujorge -p123456789 emisor-reseptor-db < backup.sql

# MongoDB
docker compose exec -T mongodb mongodump --archive | gzip > mongo-dump.gz
```

Los **assets compilados** se regeneran, no hace falta respaldarlos.

---

## 7. Cambiar claves o credenciales tras instalar

⚠️ Cambiar `MYSQL_PASSWORD` en `docker-compose.yml` **no** cambia la contraseña real
si el volumen ya existe: MariaDB ignora las variables al iniciar sobre datos
previos.

Opción A — recrear la base (se pierden los datos de partidas):

```bash
docker compose down
docker volume rm juego-interactivo_mariadb_data
# editá docker-compose.yml con las credenciales nuevas
docker compose up -d
./start.sh migrate
```

Opción B — cambiar la contraseña dentro de MariaDB:

```bash
docker compose exec mariadb mariadb -uroot -p123456789 -e "
  ALTER USER 'jorge'@'%' IDENTIFIED BY 'NUEVA_CLAVE';
  FLUSH PRIVILEGES;"
# y actualizá DB_PASSWORD en app/.env
```

Las claves de Reverb (`REVERB_APP_KEY` y `VITE_REVERB_APP_KEY`) deben coincidir
entre `.env` y el servidor: cambiá ambas y recompilá los assets.

---

## 8. Despliegue SIN Docker (alternativa)

Si el servidor no tiene Docker:

1. **PHP 8.3+** con extensiones: `pdo_mysql`, `bcmath`, `intl`, `zip`, `pcntl`, `mongodb`.
2. **Node.js 20+** para compilar assets.
3. **MariaDB** y **MongoDB** instalados y accesibles.
4. **Reverb**: `php artisan reverb:start --host=0.0.0.0 --port=8080`.
5. **Cola**: `php artisan queue:work --sleep=3 --tries=3`.
6. **Servidor web** (nginx/Apache) con `root` apuntando a `app/public`.

```bash
cp app/.env.example app/.env
php artisan key:generate
composer install -d app
npm install -C app && npm run build -C app
php artisan migrate --force --env=local -d app
php artisan storage:link -d app
```

En `.env` apuntá `DB_HOST`, `MONGODB_URI` y `REVERB_HOST` a `127.0.0.1`, y
`BROADCAST_CONNECTION=reverb`.

> Esta vía **no incluye** Nginx, ni el healthcheck de los contenedores, ni el
> entrypoint que arregla permisos. Es más manual y más fácil de romper: preferí
> Docker si podés.

---

## 9. Checklist de migración

- [ ] Docker y Docker Compose instalados
- [ ] Node.js y npm disponibles en el host
- [ ] Puertos 8343, 8080, 5436 y 5323 libres
- [ ] Código copiado (por git o rsync, **sin** `.env`)
- [ ] `cp app/.env.example app/.env` y ajustar `APP_URL` / `VITE_REVERB_HOST`
- [ ] `./start.sh up`
- [ ] `./start.sh test` → debe decir `✅ Todo OK`
- [ ] Si hay dominio: configurar el proxy inverso y `VITE_REVERB_*` con `wss`
- [ ] Recargar el navegador con **Ctrl+F5**
- [ ] Cambiar claves y credenciales si va a internet (sección 7)

---

## 10. Operación diaria

```bash
./start.sh up        # levantar
./start.sh down      # parar (los datos se conservan)
./start.sh restart   # reiniciar
./start.sh logs      # ver logs en vivo
./start.sh migrate   # correr migraciones
./start.sh test      # verificación completa
./start.sh shell     # terminal dentro del contenedor PHP
```

Reiniciar sin perder datos:

```bash
docker compose restart
```

Empezar de cero (**borra las partidas**):

```bash
docker compose down -v
./start.sh up
./start.sh migrate
```