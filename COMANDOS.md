# TASK-018 · Playwright

**PENDIENTE DE VERIFICACIÓN.** No hay Node ni navegadores en mi entorno: nada
de esto se ha ejecutado.

## Alcance real, y lo que no cubre

Se acordaron cuatro flujos. **Hoy solo existe uno**: el acceso. Los otros tres
quedan como archivos anotados con su fase, porque una prueba contra una
pantalla que no existe no puede pasar nunca.

```text
login.spec.ts           8 pruebas · ACTIVAS
publication.spec.ts     Fase 4  · anotada
kiosk.spec.ts           Fase 8  · anotada
public-survey.spec.ts   Fase 13 · anotada
```

## 1. Añadir el servicio a docker-compose.yml

Dentro de `services:`

```yaml
  playwright:
    build:
      context: ./docker/playwright
      args:
        UID: '${UID:-1000}'
        GID: '${GID:-1000}'
    profiles: ['e2e']
    volumes:
      - ./e2e:/e2e
    working_dir: /e2e
    environment:
      E2E_BASE_URL: 'http://web'
    depends_on:
      - web
    networks:
      - encuestas
```

`profiles: ['e2e']` hace que no arranque con `docker compose up`. Se invoca
con `run --rm`, igual que `node`. Recuerda T-028: con perfil, `exec` no lo
encuentra.

## 2. La base de datos

El script `docker/postgres/20-create-e2e-database.sh` crea `encuestas_e2e` al
inicializar el volumen. Como el volumen ya existe, hay que crearla a mano:

```bash
docker compose exec pgsql psql -U encuestas -d encuestas \
  -c 'CREATE DATABASE encuestas_e2e OWNER encuestas;'
```

Separada de `encuestas_test` a propósito: `RefreshDatabase` vacía esa base en
cada prueba de PHPUnit, así que compartirla significaría que una suite borra
los datos de la otra a mitad de ejecución.

## 3. Instalar y ejecutar

```bash
docker compose run --rm playwright npm install
docker compose run --rm playwright npm test
```

## 4. Preparar los datos antes de ejecutar

Las pruebas entran con `admin@example.test`, que crea `DevelopmentSeeder`. Si
la base está vacía:

```bash
docker compose exec app php artisan migrate:fresh --seed
```

**Ojo:** eso siembra `encuestas`, no `encuestas_e2e`. Ahora mismo Playwright
prueba contra la aplicación real en el puerto 8080, con la base de desarrollo.

Hacer que apunte a `encuestas_e2e` requiere una segunda instancia de la
aplicación con otro `.env`, y eso es una tarea aparte. **Queda anotado, no
resuelto**: hoy las pruebas de navegador ensucian la base con la que estás
trabajando.

## 5. Comando propio, fuera de `composer check`

En `composer.json`, dentro de `scripts`:

```json
"e2e": [
    "Composer\\Config::disableProcessTimeout",
    "@php -r \"passthru('docker compose run --rm playwright npm test');\""
]
```

Fuera de `check` a propósito: dentro, cada entrega tardaría minutos más y
acabaríais saltándoosla. Obligatorias antes de cerrar una fase, no en cada
tarea.

## Si falla

Lo más probable es un desajuste entre la versión de la imagen de Playwright y
la de `@playwright/test`. Deben coincidir: `v1.56.0` en las dos. Si no
coinciden, Playwright lo dice claramente al arrancar.
