# TASK-017 · Inertia + React + TypeScript

**Estado:** PENDIENTE DE VERIFICACIÓN en su totalidad. No hay Node ni PHP en
mi entorno: nada de esto se ha ejecutado.

Este paquete convierte **una sola pantalla**, el acceso. Es deliberado: si el
enfoque falla, se pierde una tarde y no una fase.

---

## 1. Retirar Livewire

Comprobado antes de proponerlo: **cero usos** en todo el proyecto. Se instaló
en la Fase 0 y nunca se escribió un componente.

```bash
docker compose exec app composer remove livewire/livewire
rm -f config/livewire.php
```

`check.sh` conserva la comprobación de nombres no ASCII aunque el motivo
original —el emoji que Livewire ponía en los nombres de archivo— desaparezca:
la regla sigue valiendo para cualquier otro caso.

## 2. Instalar

```bash
docker compose exec app composer require inertiajs/inertia-laravel

docker compose run --rm node npm install --save-dev \
  @inertiajs/react react react-dom \
  @types/react @types/react-dom \
  @vitejs/plugin-react typescript
```

Sin versiones fijadas a propósito: no he podido comprobar cuáles resuelven
con Laravel 13 y Vite 8, y fijar un número que no he visto funcionar es
peor que dejar que composer y npm resuelvan.

## 3. Aplicar el paquete

```bash
tar -xf task-017-inertia.tar
```

```text
app/Http/Middleware/HandleInertiaRequests.php   nuevo
app/Http/Controllers/Auth/LoginController.php   create() devuelve Inertia
bootstrap/app.php                               registra el middleware
resources/views/app.blade.php                   plantilla raíz
resources/js/**                                 React
tsconfig.json · vite.config.js                  nuevos / sustituidos
tests/Feature/Auth/LoginPageTest.php            nuevo
```

## 4. Retirar lo que sustituye

```bash
rm -f resources/js/app.js
rm -f resources/views/auth/login.blade.php
rm -f resources/views/components/layout.blade.php
rm -f resources/views/components/auth-shell.blade.php
```

**Ojo:** `layout.blade.php` lo usan todavía las otras 21 vistas. Si lo borras
ahora, todo lo demás deja de renderizar.

Así que **no borres esas dos últimas todavía**. Solo:

```bash
rm -f resources/js/app.js
rm -f resources/views/auth/login.blade.php
```

Las demás se retiran cuando su pantalla esté convertida.

## 5. Compilar y probar

```bash
docker compose run --rm node npm run build
docker compose exec app vendor/bin/pint
docker compose exec app composer check
```

Y mirarlo: `http://localhost:8080/login`

---

## Lo que hay que comprobar con los ojos

```text
[ ] La pantalla se ve igual que antes. El CSS no se ha tocado.
[ ] Un correo incorrecto muestra el error bajo el campo y en el resumen.
[ ] El enlace "Olvidé mi contraseña" lleva a donde debe.
[ ] Al fallar, el correo se conserva y la contraseña se vacía.
[ ] A 320 px la casilla y el enlace se apilan.
```

## Lo que este cambio PIERDE, y hay que decirlo

Las pruebas de accesibilidad de `LoginAccessibilityTest` afirmaban sobre
marcado: `for="email"`, `aria-describedby`, `role="alert"`. Con Inertia, el
servidor ya no devuelve ese HTML —lo genera el navegador— así que esas
pruebas dejan de poder comprobarlo.

**Eso no significa que la accesibilidad esté resuelta.** Significa que su red
desaparece. El componente sigue teniendo las etiquetas y los `aria-*`, pero
nada lo vigila.

Recuperarlo requiere pruebas de navegador —Laravel Dusk o Playwright—, que es
una decisión y una tarea aparte. Queda anotado como deuda, no como resuelto.

## Si el build falla

Lo más probable es un desajuste de versiones entre React, el plugin de Vite y
Vite 8. Pásame el error entero: no lo he podido ejecutar.
