# Documentación técnica

Juegos en vivo sobre Laravel + WebSockets, para jugar entre dos personas
en la misma red.

---

## 1. Qué hace el proyecto

Una aplicación web con **dos juegos multijugador en tiempo real**:

| Juego | Qué es |
|---|---|
| ⚡ **Trivia Relámpago** | El anfitrión lanza preguntas con tiempo límite; el invitado responde en su pantalla. Points, rachas, bonus por velocidad. |
| 🚢 **Batalla Naval** | Clásico de 2 jugadores: cada uno coloca su flota, disparan por turnos hasta hundir al rival. |

Ambasoan **juegos de rol**: una persona es **anfitrión** (controla) y la otra es
**invitada** (juega). El rol se asigna solo según quién entra primero.

---

## 2. Arquitectura

```
┌──────────────┐   POST /juego-emitir      ┌──────────────┐
│  ANFITRIÓN   │ ────────────────────────► │              │
│  (control)   │                            │   Laravel    │
└──────────────┘                            │     PHP     │
                                            │              │
┌──────────────┐   WebSocket Reverb :8080   │              │
│  INVITADO    │ ◄──────────────────────── │              │
│  (jugador)   │   GameUpdate /             └──────────────┘
└──────────────┘   BattleshipUpdate              │
        ▲                                        │
        │                              ┌─────────▼─────────┐
        │        estado del juego       │ MariaDB (juego)   │
        └──────────────────────────────│ MongoDB (admin)   │
                 (HTTP + WS)            └───────────────────┘
```

**Piezas:**

| Pieza | Función |
|---|---|
| **Nginx + PHP-FPM** | Sirven la app en el puerto 8343 |
| **Reverb** | Servidor WebSockets (puerto 8080) |
| **Queue worker** | Procesa los eventos `ShouldBroadcast` (van a la cola `database`) |
| **MariaDB** | Sesiones, caché, colas y **estado de las partidas** |
| **MongoDB** | Disponible para datos de ejemplo; Adminer y Mongo Express para administrarlos |

### Flujo de un evento

1. El anfitrión hace un POST a `/juego-emitir` o `/batalla-emitir`.
2. El controlador valida, **resuelve la acción** y guarda el estado en MariaDB.
3. Se crea un evento `ShouldBroadcast` → Laravel lo encola.
4. El **queue worker** lo procesa y lo publica en Reverb.
5. Los navegadores conectados al canal lo reciben por WebSocket y se redibujan.

---

## 3. Mapa de archivos

### Archivos nuevos (creados para los juegos)

| Archivo | Responsabilidad |
|---|---|
| `app/app/Events/GameUpdate.php` | Eventos de la Trivia por el canal `game-channel` |
| `app/app/Events/BattleshipUpdate.php` | Eventos de Batalla Naval por `battleship-channel` |
| `app/app/Http/Controllers/GameController.php` | Trivia: 12 preguntas, roles, emisión de eventos |
| `app/app/Http/Controllers/BattleshipController.php` | Batalla Naval: turnos, impactos, victoria, roles |
| `app/app/Models/BattleshipGame.php` | Estado de la partida (tabla `battleship_games`) |
| `app/app/Services/HostSlot.php` | Reparto de roles anfitrión/invitado compartido |
| `app/database/migrations/2026_10_14_..._create_battleship_games_table.php` | Tabla del estado de partida |
| `app/resources/views/menu.blade.php` | Menú principal con los dos juegos |
| `app/resources/views/juego.blade.php` | Landing del proyecto original (menú de dos tarjetas) |
| `app/resources/views/juego-emisor.blade.php` | Panel de control de la Trivia |
| `app/resources/views/juego-receptor.blade.php` | Pantalla del jugador de la Trivia |
| `app/resources/views/battleship.blade.php` | Landing de Batalla Naval |
| `app/resources/views/battleship-emisor.blade.php` | Vista anfitrión de Batalla Naval |
| `app/resources/views/battleship-receptor.blade.php` | Vista invitado de Batalla Naval |
| `app/resources/views/partials/battleship-board.blade.php` | Tablero compartido por ambos bandos (el corazón del juego) |

### Archivos modificados

| Archivo | Cambio |
|---|---|
| `app/routes/web.php` | Rutas de los dos juegos + menú |
| `start.sh` | URLs de los juegos en la salida |
| `README.md` | Documentación general |
| `app/tests/Feature/ExampleTest.php` | 27 tests que cubren todo |

### Rutas

| Ruta | Descripción |
|---|---|
| `/` | Menú principal (muestra si el lugar de anfitrión está libre u ocupado) |
| `/juego?rol=host` \| `?rol=guest` | Entrar a la Trivia con el rol elegido |
| `/juego-emisor` / `/juego-receptor` | Control / Pantalla de la Trivia |
| `/juego-emitir` | POST de acciones de la Trivia |
| `/batalla?rol=host` \| `?rol=guest` | Entrar a Batalla Naval con el rol elegido |
| `/batalla-emisor` / `/batalla-receptor` | Control / Pantalla de Batalla Naval |
| `/batalla-emitir` | POST de acciones de Batalla Naval |
| `/batalla-estado` | GET: estado actual de la partida (polling) |
| `/batalla-liberar` | POST: el anfitrión libera su lugar al cerrar (sin CSRF, para `sendBeacon`) |
| `/emisor` / `/receptor` | Ejemplo original, redirigen al menú |

---

## 4. Trivia Relámpago ⚡

### Reglas

- 12 preguntas con 4 opciones (A/B/C/D), 6 categorías.
- El anfitrión elige el tiempo por pregunta: **10, 15, 20 o 30 segundos**.
- El invitado elige una opción y **puede cambiarla** hasta que revele o termine el tiempo.
- **Puntos:** `10 base + 2 por segundo restante + 2 por racha (máx. 10)`.
- Si el anfitrión no revela, el juego **auto-revela** al agotarse el tiempo.
- Puntos y rachas se guardan en el navegador (`localStorage`).

### Flujo

```
Anfitrión                    Servidor                   Invitado
   │  🚀 Lanzar (pregunta 3)      │                          │
   ├────────────────────────────► │  guarda + broadcast      │
   │                              ├────────────────────────► │  muestra la pregunta
   │                              │                          │  (elige B, cambia a C…)
   │  👁 Revelar                  │                          │
   ├────────────────────────────► │  marca correcto + suma   │
   │                              ├────────────────────────► │  pinta bien/mal + confetti
```

---

## 5. Batalla Naval 🚢

### Reglas

- Tablero **10×10**.
- Flota de **6 barcos = 20 casillas**: Portaaviones (5), Acorazado (4), dos Cruceros (3), Submarino (3), Destructor (2).
- Colocación: click a click (con vista previa al pasar el mouse), botón 🎲 Aleatorio, ↩️ Deshacer, o click sobre un barco para sacarlo.
- Rotación horizontal/vertical con el botón o la tecla **H**.
- **Turnos**: 💥 impacto → repetís turno · 💨 agua → pasa el turno.
- Un barco queda 🔥 **hundido** cuando le pegaste a todas sus casillas.
- **Gana** quien hunda las 20 casillas del rival.
- Se puede terminar antes con **🔚 Terminar partida** (gana quien más impactos tenga) o **🏳️ Rendirme** (gana el rival).

### El servidor manda (importante)

A diferencia de la Trivia, **el estado de la Batalla Naval vive en el servidor**
(tabla `battleship_games`). Esto es fundamental:

> Si el impacto lo calculara cada navegador, el jugador que dispara nunca sabría
> si acertó, porque el impacto se resuelve mirando la flota del *defensor*.
> Solo el servidor tiene ambas flotas, así que es el único que puede resolverlo
> y telling the truth to both players at the same time.

El cliente **nunca recibe la flota del rival** mientras la partida está en curso;
solo se revela al terminar (evita hacer trampa con el devtools).

### Barcos dibujados

Cada tipo de buque tiene su propio SVG en
`app/resources/views/partials/battleship-board.blade.php` (función `shipMarkup`):
portaaviones con pista de aterrizaje, acorazado con torres, submarino con
periscopio, etc. Se dibujan anclados a su primera celda y se dimensionan con CSS
para que no dependan de medir píxeles del DOM.

---

## 6. Roles: quién es anfitrión

### El problema que resuelve

La sesión de Laravel es **privada de cada navegador**, así que no sirve para saber
si *otra persona* ya tomó el puesto de anfitrión. `HostSlot` usa la **caché de la
base de datos**, que sí es compartida entre navegadores.

### Funcionamiento

1. Quien entra primero a `/batalla` o `/juego` **toma el lugar** de anfitrión
   (`Cache::add`, que es atómico: si hay dos entrando a la vez, solo uno gana).
2. Mientras el anfitrión tiene la pestaña abierta, manda un **latido cada 5 s**
   que renueva la reserva del lugar.
3. La reserva dura **60 segundos**. Si el anfitrión no manda latidos durante ese
   minuto, el lugar queda libre.
4. El invitado que esté **conectado** lo toma automáticamente y la pantalla
   recarga al panel de control. Si hay varios invitados, solo uno gana.
5. Si el anfitrión vuelve a entrar, queda como invitado.

Esto evita el problema de "pestañas viejas que roban el rol": solo un jugador
**vivo y mirando la pantalla** (que manda latidos) puede tomar el puesto.

### Variables

| Método | Qué hace |
|---|---|
| `HostSlot::claim($juego)` | Intenta tomar el lugar (atómico) |
| `HostSlot::isMine($juego)` | ¿El lugar es mío? |
| `HostSlot::refresh($juego)` | Renueva la reserva (latido) |
| `HostSlot::release($juego)` | Libera el lugar al cerrar |

---

## 7. Seguridad (nivel de juego casero)

Este proyecto es **un juego entre amigos**, no una app en producción. Decisiones
tomadas:

| Tema | Qué se hace |
|---|---|
| Reglas del juego | **Validadas en el servidor.** No se puede disparar fuera de turno ni repetir casilla. |
| Flota del rival | **No se envía** al navegador hasta que termina la partida. |
| Bots | `BROADCAST_CONNECTION=null` en tests: el broadcast no hace nada. |
| CSRF | Todas las vistas tienen `<meta name="csrf-token">`. |
| Roles | Un invitado **no** se autopromueve en requests normales, solo con el botón o el relevo automático. |

**Antes de ponerlo en internet:** cambiar todas las claves de Docker y `.env`,
`APP_DEBUG=false`, `APP_ENV=production`, no exponer Adminer ni Mongo Express, y
cachear configuración.

---

## 8. Tests

27 tests que cubren lo importante:

```bash
docker compose exec php php artisan test
```

| Grupo | Qué cubre |
|---|---|
| Menú | Muestra los dos juegos y el estado del lugar de anfitrión |
| Trivia | Los botones de rol funcionan y las vistas tienen el token CSRF |
| Trivia | Las vistas mandan latidos y el latido renueva la reserva |
| Batalla Naval | Turnos, impactos, hundimiento, victoria, revelar la flota al final |
| Batalla Naval | Flota inválida rechazada, no se puede empezar sin ambos listos |
| Batalla Naval | Terminar / rendirse |
| Roles | El invitado se promueve tras 1 min sin anfitrión; solo uno gana |

---

## 9. Problemas resueltos (bitácora)

Documentar esto ayuda a no volver a los mismos errores:

| Síntoma | Causa real |
|---|---|
| La Trivia no avanzaba | Las vistas no tenían el meta del token CSRF → **error 419** en cada POST |
| `/juego?rol=host` daba 500 | El método usaba `$request` sin declararlo en la firma |
| El anfitrión pasaba a invitado a los ~45 s | La Trivia no mandaba latidos, se vencía la reserva |
| Los disparos no mostraban impacto | El impacto lo calculaba solo el defensor; el que disparaba leía un campo que nadie enviaba |
| Nadie podía volver a ser anfitrión | `Cache::add` usa `insertOrIgnore`: una fila vencida bloqueaba el lugar para siempre |
| Los barcos se perdían al acomodarlos | La carga de estado llegaba tarde y pisaba la flota local |
| El rol saltaba entre pestañas | El invitado se autopromovía en cada request |
| Los barcos tardaban al mover el mouse | Cada `mouseover` repintaba las 200 celdas del tablero |

---

## 10. Endpoints de desarrollo

```bash
# Lanzar un evento de prueba
curl http://localhost:8343/broadcast-test

# Ver el estado de la partida de Batalla Naval
curl -H 'Accept: application/json' http://localhost:8343/batalla-estado

# Ver el log de la cola en vivo
docker compose logs -f queue
```