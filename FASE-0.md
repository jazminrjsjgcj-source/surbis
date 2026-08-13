# TASK-003 · Fase 0 — Base técnica sobre Docker

**Fecha:** 13 de agosto de 2026
**Sustituye a:** la versión sin Docker del mismo día. Esa se retira, no se
guarda al lado.

```text
VERIFICADO POR MÍ
  bin/check.sh          ejecutado contra un repositorio de prueba en los dos
                        sentidos: detecta las tres infracciones y no produce
                        falsos positivos sobre utilidades válidas.
  docker-compose.yml    sintaxis YAML validada; los cinco servicios y los dos
                        volúmenes se leen correctamente.
  scripts .sh           `bash -n` limpio.
  archivos .php         balance de llaves y paréntesis ignorando cadenas,
                        comentarios y heredocs.
  contrastes de color   calculados, no estimados.

NO VERIFICADO
  Que las imágenes construyan. Que los contenedores levanten. Que Laravel 13
  instale. Que las migraciones corran. Que las pruebas pasen.
  No hay Docker ni PHP en mi entorno y Packagist responde 403.
  Todo lo de abajo está razonado, no ejecutado.
```

---

## 1. Por qué este montaje y no Laravel Sail

Sail es la respuesta del framework y sería una orden en lugar de seis. Aun así
no lo uso, por un motivo concreto:

```text
RNF-GEN-018   En Linux solamente public/ debe exponerse mediante Nginx.
```

Con Sail la aplicación se sirve por otro camino y esa regla no se puede
comprobar hasta el día del despliegue. Con Nginx delante y PHP-FPM detrás,
`http://localhost:8080/.env` devuelve 404 desde el primer día y eso es una
prueba, no una intención.

El coste es cuatro archivos de configuración más. Si en algún momento pesan
más de lo que aportan, migrar a Sail es reversible; lo contrario —descubrir en
producción que la raíz web estaba mal— no lo es.

---

## 2. Requisitos del equipo

```bash
docker --version           # 24 o superior
docker compose version     # v2. Si responde "docker-compose", está anticuado
```

Nada más. No hace falta PHP, ni Composer, ni PostgreSQL, ni Node instalados en
el equipo: todo vive en contenedores. DBeaver se conecta desde fuera al puerto
publicado.

**En Linux**, antes de nada:

```bash
id -u && id -g
```

Anota esos dos números. Si no son 1000, hay que ponerlos en el `.env` o los
archivos que cree Docker pertenecerán a otro usuario y el editor no podrá
escribirlos. En macOS y Windows con Docker Desktop, deja 1000.

---

## 3. Montaje, paso a paso

### 3.1 Carpeta y contenido del paquete

```bash
mkdir encuestas && cd encuestas
tar -xf encuestas-fase-0.tar
ls -a
```

Debes ver:

```text
.env.example          docker-compose.yml     docker/
archivos/             FASE-0.md              CONTEXTO-2026-08-13.md
```

`archivos/` no es parte del proyecto: es la carpeta de la que copiaremos en el
paso 3.6. Al terminar se borra.

### 3.2 Entorno

```bash
cp .env.example .env
```

Abre `.env` y rellena **solo** `DB_PASSWORD`. Cualquier cosa sirve en local;
elígela tú, porque en este paquete no viaja ninguna. En Linux, corrige también
`UID` y `GID` con los números del paso 2.

Si el 5432 de tu equipo ya está ocupado por otro PostgreSQL, cambia
`FORWARD_DB_PORT` a 5433 en lugar de parar el otro servicio.

### 3.3 Construir la imagen de PHP

```bash
docker compose build app
```

Tarda unos minutos la primera vez. Instala PHP 8.4 con `pdo_pgsql`, `redis`,
`intl`, `zip`, `bcmath`, `gd`, `exif`, `pcntl` y `opcache`.

### 3.4 Levantar base de datos y Redis

```bash
docker compose up -d pgsql redis
docker compose ps
```

`pgsql` debe aparecer como `healthy`. Si se queda en `starting` o reinicia,
mira el motivo antes de continuar:

```bash
docker compose logs pgsql
```

La causa casi siempre es `DB_PASSWORD` vacío: PostgreSQL se niega a arrancar
sin contraseña, y eso es correcto.

Al crearse por primera vez, el volumen ejecuta
`docker/postgres/10-create-test-database.sh`, que crea `encuestas_test`. Esa
base existe porque la suite corre sobre PostgreSQL real y `RefreshDatabase`
vacía la base en cada ejecución: compartirla con desarrollo sería borrar tus
datos cada vez que pruebas.

### 3.5 Crear el proyecto Laravel

```bash
docker compose run --rm app bash -lc \
  'composer create-project laravel/laravel:^13.0 /tmp/nuevo --no-interaction \
   && cp -an /tmp/nuevo/. /var/www/html/'
```

Dos detalles que importan:

**Se crea en `/tmp` y se copia.** `composer create-project` exige un directorio
vacío y el nuestro ya tiene el paquete dentro.

**`cp -an` no sobrescribe.** La `n` protege tu `.env` y el `.env.example` de
este paquete. Sin ella, el `.env` recién generado por Laravel —con SQLite, en
inglés y apuntando a `127.0.0.1`— pisaría el tuyo, y el fallo aparecería tres
pasos más tarde con un mensaje que no señala a esta línea.

Luego:

```bash
docker compose run --rm app php artisan key:generate
docker compose run --rm app php artisan --version    # Laravel Framework 13.x
```

Si `--version` no dice 13, para aquí y dime qué dice.

### 3.6 Dependencias y archivos del paquete

```bash
docker compose run --rm app composer require livewire/livewire:"^4.0"
docker compose run --rm app php artisan livewire:publish --config

cp -r archivos/. .
rm -rf archivos
chmod +x bin/check.sh

grep -E 'tailwindcss|@tailwindcss/vite' package.json \
  || docker compose run --rm node npm install -D tailwindcss@"^4.0" @tailwindcss/vite@"^4.0"
docker compose run --rm node npm install
```

Los archivos que acabas de copiar sustituyen o añaden estos cinco:

```text
app/Providers/AppServiceProvider.php            sustituye
resources/css/app.css                           sustituye
tests/Architecture/SchemaConventionsTest.php    nuevo
bin/check.sh                                    nuevo
pint.json                                       nuevo
```

La sección 5 explica qué hace cada uno y por qué.

### 3.7 Ediciones que no puedo entregar como archivo

Son cuatro y están en la sección 6. Hazlas ahora, **antes de migrar**.

### 3.8 Levantar todo y migrar

```bash
docker compose up -d
docker compose run --rm app php artisan migrate
```

### 3.9 Comprobar

```bash
curl -I http://localhost:8080          # 200
curl -I http://localhost:8080/.env     # 404  ← RNF-GEN-018
docker compose run --rm app composer check
```

Pégame la salida completa de los tres, errores incluidos.

---

## 4. DBeaver

Con `docker compose up -d` corriendo:

```text
Tipo              PostgreSQL
Host              localhost
Puerto            5432        (o FORWARD_DB_PORT si lo cambiaste)
Base de datos     encuestas
Usuario           encuestas
Contraseña        la que pusiste en DB_PASSWORD
```

`Test Connection` la primera vez ofrece descargar el driver: acepta.

Dos avisos que ahorran una tarde:

**El host es `localhost`, no `pgsql`.** `pgsql` solo existe dentro de la red de
Docker. DBeaver corre en tu equipo y ve el puerto publicado.

**Añade también `encuestas_test`** como segunda conexión, o al menos ten
presente que existe. Cuando una prueba falle y quieras mirar qué quedó en la
tabla, es esa y no la otra.

Para ver que las fechas quedaron bien —lo que comprueba la prueba de
arquitectura, pero mirándolo tú—:

```sql
select table_name, column_name, data_type
  from information_schema.columns
 where table_schema = 'public'
   and data_type like 'timestamp%'
 order by table_name, column_name;
```

Todas deben decir `timestamp with time zone`. Ni una `without`.

---

## 5. Archivos que se entregan completos

### 5.1 `app/Providers/AppServiceProvider.php`

**Antes** — lo que genera Laravel:

```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
```

Un `boot()` vacío. No está mal: está sin decidir. Cuatro decisiones que valen
más aquí que repetidas en cada módulo.

**Después** — el archivo completo está en el paquete. Los cuatro cambios:

**`Model::shouldBeStrict(! isProduction())`**
Sin esto, escribir `$survey->organization->name` sin haber cargado la relación
funciona: Eloquent lanza una consulta extra por fila. Con cien filas, cien
consultas. La pantalla se ve bien y tarda 4 segundos en lugar de 200 ms. Es la
definición exacta del mecanismo que no da error. Activado, eso lanza una
excepción y la prueba se pone roja en el momento de escribirlo. Solo fuera de
producción: allí convertiría un problema de rendimiento en una pantalla caída.
Sirve a RNF-GEN-010.

También bloquea asignar atributos que no existen en la tabla, que es como un
campo de formulario acaba sin consumidor sin que nadie lo note.

**`Date::use(CarbonImmutable::class)`**
Con Carbon mutable, `$inicio->addDays(30)` modifica `$inicio`. Si esa fecha
venía de un modelo compartido, la modificación viaja a donde nadie la espera.
Inmutable, esa línea devuelve una fecha nueva y deja la original intacta. En un
sistema donde las fechas son un requisito con ID propio, no es una preferencia.
Sirve a RNF-GEN-013.

**`DB::prohibitDestructiveCommands(isProduction())`**
Bloquea `db:wipe`, `migrate:fresh`, `migrate:refresh` y `migrate:reset` cuando
`APP_ENV=production`. El ANEXO 1 sección 28 lo prohíbe por escrito; esto lo
hace imposible en lugar de prohibido. Cuesta una línea.

**`URL::forceScheme('https')` en producción**
Detrás de Nginx, PHP recibe la petición por HTTP y genera URLs `http://`, que
el navegador bloquea como contenido mixto. Sirve a RNF-GEN-003.

### 5.2 `resources/css/app.css`

**Antes:** una línea, `@import 'tailwindcss';`.

**Después:** el bloque `@theme` con los tokens del prototipo. El archivo
documenta su propio razonamiento; lo que importa aquí es en qué **se aparta**
del prototipo, porque no es una copia.

Cinco colores del prototipo no cumplen WCAG 2.2 AA para texto. Medido sobre las
tres superficies del sistema:

```text
                      #ffffff   #f8eef1   #f7e8ec    AA texto = 4.5
accent    #e6637a      3.27      2.88      2.75      no cumple
positive  #2f9e6b      3.37      2.97      2.85      no cumple
neutral   #c08a1e      3.05      2.68      2.57      no cumple
negative  #c14d4d      4.73      4.17      3.99      no cumple sobre canvas
ink-3     #9c7c85      3.73      3.28      3.14      no cumple
```

`ink-3` es el que importa: el prototipo lo usa para la clase `.muted`, es decir
para todo el texto secundario de la interfaz. Un texto secundario a 3.28:1 es
texto que parte de los usuarios no lee.

La solución no es repintar la marca. Los tokens base se conservan tal cual para
rellenos, gráficas, bordes y fondos —que solo necesitan 3:1— y se añaden
variantes `-text` para texto e iconos, todas por encima de 4.50 en las tres
superficies:

```text
accent    → --color-accent-text    #ce2140    5.36 / 4.72 / 4.52
positive  → --color-positive-text  #247952    5.34 / 4.70 / 4.50
neutral   → --color-neutral-text   #8b6416    5.34 / 4.70 / 4.50
negative  → --color-negative-text  #ba4141    5.36 / 4.72 / 4.52
ink-3     → --color-ink-subtle     #82626b    5.37 / 4.73 / 4.53
```

`ink-3` se sustituye en vez de duplicarse, porque su único uso es texto. La
diferencia visual es de un 6 % de luminosidad.

Esto toca la marca, así que es P-001 en el documento de contexto: lo propongo,
no lo decido. Si se rechaza, la alternativa es no usar esos colores para texto
en ninguna pantalla, que es más restrictivo, no menos.

Además renombré tres grupos de tokens siguiendo el ANEXO 2: `pos`/`neu`/`neg` →
`positive`/`neutral`/`negative`, e `ink-2`/`ink-3` → `ink-muted`/`ink-subtle`.
`--bg` pasa a `--color-canvas` porque en Tailwind v4 es el prefijo `--color-`
lo que genera las utilidades.

### 5.3 `tests/Architecture/SchemaConventionsTest.php`

Dos pruebas que no comprueban una funcionalidad. Vigilan una invariante.

La primera confirma que la suite corre sobre PostgreSQL. Es la más importante
de las dos: sobre SQLite, la segunda consultaría una vista que no existe y el
resultado sería un error confuso o, según la configuración, una lista vacía que
parece un aprobado.

La segunda busca en `information_schema` cualquier columna
`timestamp without time zone`. Debe devolver cero. Si alguien escribe
`$table->timestamps()` en lugar de `timestampsTz()` dentro de dos meses, esta
prueba lo dice con el nombre exacto de la columna.

### 5.4 `bin/check.sh`

**Este sí está verificado.** Ejecutado contra un repositorio de prueba con
infracciones deliberadas y contra el mismo repositorio limpio:

```text
con infracciones → detecta las 3, código de salida 1
limpio           → 0 hallazgos, código de salida 0
```

Comprueba tres cosas, las tres con umbral cero:

1. **Nombres que colisionan al ignorar mayúsculas.** `App.php` y `app.php` son
   el mismo archivo en macOS y dos en el Linux de producción.
2. **Nombres de archivo fuera de ASCII.** Existe por una razón concreta:
   Livewire 4 prefija por defecto los componentes de vista con un emoji en el
   nombre del archivo.
3. **Utilidades de dirección física en plantillas.** `ml-`, `mr-`, `pl-`,
   `pr-`, `left-`, `right-`, `text-left`, `text-right`. Si el sistema debe
   funcionar en árabe, cada una es una línea que habrá que reescribir.

**Encontré un error en mi propia primera versión, y lo cuento porque es
exactamente la trampa que este proyecto ya declaró como la más cara.** La
comprobación 2 no encontraba nada: `git ls-files` escapa por defecto los
nombres no ASCII a secuencias octales, así que el nombre con emoji le llegaba
ya convertido en ASCII puro. El script tenía el defecto que venía a detectar: un
mecanismo plausible que no hacía nada. No se veía leyéndolo. Se vio
ejecutándolo contra un archivo con emoji. Corregido con
`git -c core.quotepath=off`, y el motivo queda escrito dentro del script para
que nadie lo "simplifique" más adelante.

### 5.5 `pint.json`

El preset `laravel` ya está construido sobre PSR-12, así que RNF-GEN-016 queda
cubierto sin tener que elegir entre las dos cosas que pide. Se le añade
`declare_strict_types`, que convierte en error de tipo lo que de otro modo
sería una conversión silenciosa: `"5 encuestas"` pasando como el entero 5.

---

## 6. Ediciones quirúrgicas

Estas cuatro no las entrego como archivo completo, y es deliberado: son
archivos que genera Laravel 13 y que yo no he visto. Si te diera mi versión
completa podría estar borrando en silencio algo que la 13 añadió y yo
desconozco. Te doy la línea exacta.

### E-01 · `config/livewire.php` — desactivar el emoji

Dentro de `make_command`:

```diff
- 'emoji' => true,
+ 'emoji' => false,
```

Verifica el nombre exacto de la clave contra el archivo publicado; la
documentación la llama `make_command.emoji` pero no he podido abrirlo.

Sin esto, `php artisan make:livewire login` crea un archivo cuyo nombre empieza
por un emoji. Sección 21 del ANEXO 1, y además un nombre no ASCII atravesando
Git, rsync y Nginx no aporta nada a cambio.

### E-02 · `database/migrations/0001_01_01_000000_create_users_table.php`

Esta es la edición importante de la Fase 0.

**Antes** (esqueleto de Laravel; contrasta con el tuyo):

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamp('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestamps();
});

Schema::create('password_reset_tokens', function (Blueprint $table) {
    $table->string('email')->primary();
    $table->string('token');
    $table->timestamp('created_at')->nullable();
});
```

**Después:**

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestampTz('email_verified_at')->nullable();
    $table->string('password');
    $table->rememberToken();
    $table->timestampsTz();
});

Schema::create('password_reset_tokens', function (Blueprint $table) {
    $table->string('email')->primary();
    $table->string('token');
    $table->timestampTz('created_at')->nullable();
});
```

**Qué era y por qué estaba mal.** Sobre PostgreSQL, `timestamp()` genera
`timestamp(0) without time zone`: un instante sin referencia. La base guarda
"14:30" sin saber 14:30 de dónde. Mientras servidor, base y usuario compartan
zona horaria no pasa nada visible. En cuanto una organización esté en otra
—y RNF-GEN-013 obliga a mostrar cada fecha en la zona de su organización— los
informes salen desplazados y no hay forma de saber en qué dirección, porque el
dato original ya perdió esa información.

`timestampTz()` genera `timestamp with time zone`: Postgres guarda el instante
absoluto y convierte al leer.

**Qué NO cambio aquí.** Las columnas de `users` son las del esqueleto y su
diseño real es de la Fase 1: ahí entrarán organización, membresía, estado y
rol. La tabla `sessions` del mismo archivo usa `integer('last_activity')`, que
es un epoch y está bien. No se toca.

**Efecto en otro archivo:** ninguno, más allá de que la prueba de arquitectura
dejará de fallar. Si ya ejecutaste `migrate`,
`docker compose run --rm app php artisan migrate:fresh`.

### E-03 · `database/migrations/0001_01_01_000002_create_jobs_table.php`

```diff
  Schema::create('failed_jobs', function (Blueprint $table) {
      ...
-     $table->timestamp('failed_at')->useCurrent();
+     $table->timestampTz('failed_at')->useCurrent();
  });
```

Mismo motivo. Las columnas `created_at`, `available_at`, `reserved_at`,
`cancelled_at` y `finished_at` de `jobs` y `job_batches` son enteros epoch, no
`timestamp`. No se tocan.

Si me dejé alguna columna en E-02 o E-03, la prueba de arquitectura te dirá
cuál. Ese es su trabajo.

### E-04 · `phpunit.xml` — pruebas sobre PostgreSQL

El esqueleto trae estas dos líneas dentro de `<php>`:

```diff
- <env name="DB_CONNECTION" value="sqlite"/>
- <env name="DB_DATABASE" value=":memory:"/>
+ <env name="DB_CONNECTION" value="pgsql"/>
+ <env name="DB_DATABASE" value="encuestas_test"/>
```

**Por qué.** El ANEXO 1 sección 68 lo pide y no es una formalidad. SQLite no
tiene `timestamptz`, no aplica los `CHECK` igual, no trata las restricciones
únicas del mismo modo y acepta cosas que PostgreSQL rechaza. Una suite verde
sobre SQLite no dice nada sobre RNF-GEN-012. Es más lenta y es la única que
sirve.

La base `encuestas_test` ya existe: la creó el script de inicialización del
paso 3.4.

### E-05 · `composer.json` — un solo comando de verificación

En la sección `scripts`:

```json
"check": [
    "@php vendor/bin/pint --test",
    "bash bin/check.sh",
    "@php artisan test"
]
```

Y entonces, antes de cada entrega y aunque el cambio sea de dos líneas:

```bash
docker compose run --rm app composer check
```

### E-06 · Estructura de capas

```bash
mkdir -p app/Application app/Domain app/Infrastructure
touch app/Application/.gitkeep app/Domain/.gitkeep app/Infrastructure/.gitkeep
```

No hace falta tocar el autoload: `App\` ya apunta a `app/`, así que
`App\Domain\Surveys\Survey` funciona sin configurar nada.

**Van vacías, y con esto cambio un criterio que yo mismo había propuesto.** En
el diagnóstico escribí como criterio de cierre "las tres carpetas existen con
un archivo real cada una". Estaba mal. Una clase de ejemplo que nadie instancia
es código inalcanzable, y tu regla 9 —todo lo que se pone tiene que tener un
consumidor demostrable— la prohíbe. Se pueblan en la Fase 1, cuando
`CreateOrganization` tenga quien la llame.

### E-07 · Prototipo y andamiaje de Figma

```bash
mkdir -p docs/prototipo docs/marca
cp "<ruta-del-prototipo>/index.html"         docs/prototipo/
cp "<ruta-del-prototipo>/src/imports/"*.jpeg docs/marca/
mv CONTEXTO-2026-08-13.md FASE-0.md docs/
```

Y **no** trasladar al proyecto: `src/App.tsx`, `src/main.tsx`,
`src/index.css`, el `vite.config.ts` del prototipo, su `package.json`,
`pnpm-lock.yaml`, `.figma/`, `AGENTS.md`, `CLAUDE.md` ni `.mise.toml`.

`src/App.tsx` es la trampa T-001 del documento de contexto: 54 líneas que
dibujan una rejilla de puntos siguiendo el cursor, sin relación con encuestas,
que `index.html` nunca carga. Es código inalcanzable. No se traslada ni se
adapta: se queda fuera.

Las cinco imágenes son piezas gráficas del H. Ayuntamiento de La Paz y son el
origen documentado de la paleta. Van a `docs/marca/` como referencia, no a
`public/`: ninguna pantalla las usa.

---

## 7. Criterios de cierre de la Fase 0

```text
[ ] docker compose ps muestra app, web, pgsql y redis arriba, pgsql healthy
[ ] php artisan --version responde Laravel Framework 13.x
[ ] http://localhost:8080 responde 200
[ ] http://localhost:8080/.env responde 404          ← RNF-GEN-018
[ ] php artisan migrate corre contra PostgreSQL sin errores
[ ] la prueba "ejecuta la suite contra PostgreSQL" pasa
[ ] la prueba "no almacena fechas sin zona horaria" pasa
[ ] bin/check.sh sale con código 0
[ ] vendor/bin/pint --test sale limpio
[ ] composer check ejecuta las tres cosas y sale con código 0
[ ] DBeaver conecta a encuestas y a encuestas_test
[ ] la consulta de information_schema no devuelve ninguna columna
    "timestamp without time zone"
[ ] app/Application, app/Domain y app/Infrastructure existen y están vacías
[ ] config/livewire.php tiene make_command.emoji en false
[ ] el repositorio no contiene src/App.tsx ni el andamiaje de Figma
[ ] docs/CONTEXTO-2026-08-13.md está en el repositorio
```

Retirado del criterio original, con motivo:

```text
- "cambiar el locale invierte el layout sin romperlo"
  No hay ninguna pantalla en la Fase 0 contra la que comprobarlo. Pasa a la
  Fase 1, donde el login será la primera. Mientras tanto lo que protege el
  requisito es bin/check.sh, cuyo umbral de utilidades físicas es cero desde
  antes de que exista la primera plantilla.
```

---

## 8. Lo que la Fase 0 no hace

```text
sin rutas · sin controladores · sin modelos · sin autenticación
sin pantallas · sin cadenas traducibles · sin capas pobladas
```

Cadenas traducibles: `lang/es/` no se crea todavía porque no hay ni una cadena
que traducir. Un archivo de idioma vacío es una carpeta que promete un
mecanismo que aún no existe. Se crea en la Fase 1, con su primer consumidor.

---

## 9. Comandos de uso diario

```bash
docker compose up -d                                   # levantar
docker compose down                                    # parar
docker compose down -v                                 # parar y BORRAR datos
docker compose logs -f app                             # ver errores de PHP

docker compose run --rm app php artisan <lo-que-sea>
docker compose run --rm app composer <lo-que-sea>
docker compose run --rm app composer check             # antes de cada entrega
docker compose run --rm node npm run dev
```

Si un comando falla con un permiso denegado sobre un archivo, casi siempre es
`UID`/`GID` mal puestos en `.env`. Se corrige y se reconstruye la imagen:

```bash
docker compose build app
```
