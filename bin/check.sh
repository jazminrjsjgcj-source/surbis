#!/usr/bin/env bash
#
# Verificacion mecanica del repositorio.
#
# Tres comprobaciones baratas que solo devuelven cero. Ninguna admite
# excepciones "por ahora": el momento de aceptar la primera es el momento en
# que la comprobacion deja de servir.
#
# Uso:  bin/check.sh
# Sale con codigo 1 si encuentra cualquier infraccion.

set -euo pipefail
cd "$(dirname "$0")/.."

# core.quotepath=off es obligatorio: por defecto git escapa los nombres no
# ASCII a secuencias octales, con lo que la comprobacion 2 nunca encontraria
# nada y la 3 recibiria rutas inexistentes. Se detecto ejecutando el script
# contra un archivo con emoji en el nombre.
GIT=(git -c core.quotepath=off)
# Sin repositorio git, las tres comprobaciones se basan en `git ls-files` y no
# devuelven nada. El `|| true` que necesita grep se traga tambien el error de
# git, la variable queda vacia, y "vacia" se lee como "limpia": el script
# informaba de cero hallazgos habiendo mirado cero archivos.
# Detectado en la primera ejecucion real, 13 ago 2026.
if ! "${GIT[@]}" rev-parse --is-inside-work-tree >/dev/null 2>&1; then
  printf 'check.sh: esto no es un repositorio git.\n'
  printf '  Las tres comprobaciones dependen de git ls-files y no pueden\n'
  printf '  ejecutarse. Ejecuta primero:  git init\n'
  exit 1
fi

fallos=0
titulo() { printf '\n== %s\n' "$1"; }
ok()     { printf '   sin hallazgos\n'; }
fallo()  { printf '   %s\n' "$1"; fallos=$((fallos + 1)); }

# ---------------------------------------------------------------------------
# 1. Nombres que colisionan al ignorar mayusculas
#
# En Windows y macOS, App.php y app.php son el mismo archivo. En el Linux de
# produccion son dos. Un repositorio que contiene ambos se despliega roto y el
# error aparece lejos de su causa.   ANEXO 1 seccion 25
# ---------------------------------------------------------------------------
titulo "Colisiones de nombre ignorando mayusculas"
colisiones=$("${GIT[@]}" ls-files | tr '[:upper:]' '[:lower:]' | sort | uniq -d || true)
if [ -z "$colisiones" ]; then
  ok
else
  fallo "estos nombres colisionan en un sistema de archivos case-insensitive:"
  printf '     %s\n' "$colisiones"
fi

# ---------------------------------------------------------------------------
# 2. Nombres de archivo fuera de ASCII
#
# Livewire 4 prefija por defecto los componentes de vista con un emoji en el
# NOMBRE DEL ARCHIVO. Sobre Linux, Nginx, Git y un despliegue por rsync eso es
# una fuente de problemas gratuita, y ademas contradice la seccion 21.
# Se desactiva en config/livewire.php; esta comprobacion detecta si vuelve.
# ---------------------------------------------------------------------------
titulo "Nombres de archivo fuera de ASCII"
no_ascii=$("${GIT[@]}" ls-files | LC_ALL=C grep -n '[^ -~]' || true)
if [ -z "$no_ascii" ]; then
  ok
else
  fallo "estos nombres contienen caracteres no ASCII:"
  printf '     %s\n' "$no_ascii"
fi

# ---------------------------------------------------------------------------
# 3. Utilidades de direccion fisica en plantillas
#
# El sistema debe poder funcionar en arabe. Una interfaz construida sobre
# izquierda/derecha fija no se traduce: se reescribe.
# Permitido:  ms-* me-* ps-* pe-* start-* end-* text-start text-end
# Prohibido:  ml-* mr-* pl-* pr-* left-* right-* text-left text-right
# ANEXO 1 secciones 50 y 96
#
# El numero permitido es cero y no puede subir.
# ---------------------------------------------------------------------------
titulo "Utilidades de direccion fisica en plantillas"
mapfile -d '' plantillas < <("${GIT[@]}" ls-files -z '*.blade.php' '*.css')
if [ "${#plantillas[@]}" -eq 0 ]; then
  printf '   no hay plantillas todavia\n'
else
  # Utilidades de Tailwind
  fisicas=$(grep -nE '(^|[^a-z-])(ml|mr|pl|pr)-[0-9a-z]|(^|[^a-z-])(left|right)-[0-9]|text-(left|right)([^a-z-]|$)' \
    "${plantillas[@]}" || true)

  # Propiedades CSS. Esta mitad falto en la primera version: la comprobacion
  # solo miraba nombres de utilidad, asi que un `margin-left: 12px` escrito a
  # mano en una hoja de estilos pasaba sin que nadie lo viera. Se anadio al
  # escribir el primer CSS del sistema.
  propiedades=$(grep -nE 'margin-(left|right)[[:space:]]*:|padding-(left|right)[[:space:]]*:|border-(left|right)[[:space:]]*(-[a-z]+)?[[:space:]]*:|(^|[^-a-z])(left|right)[[:space:]]*:|text-align[[:space:]]*:[[:space:]]*(left|right)' \
    "${plantillas[@]}" || true)

  hallazgos=$(printf '%s\n%s' "$fisicas" "$propiedades" | grep -v '^$' || true)

  if [ -z "$hallazgos" ]; then
    ok
  else
    fallo "sustituye por la propiedad o utilidad logica equivalente:"
    printf '     %s\n' "$hallazgos"
  fi
fi

# ---------------------------------------------------------------------------
# 4. Paginas de Inertia fuera del directorio que el paquete busca
#
# Inertia resuelve los componentes en resource_path('js/pages'), en minuscula.
# Un directorio js/Pages funciona en Windows y macOS y NO en el Linux de
# produccion: son dos rutas distintas.
#
# Costo una tanda entera de diagnostico: el archivo existia, el import
# funcionaba, y assertInertia decia que el componente no existia.
# ---------------------------------------------------------------------------
titulo "Paginas de Inertia en el directorio correcto"
if [ -d resources/js ]; then
  if [ -d resources/js/Pages ]; then
    fallo 'existe resources/js/Pages con mayuscula. Inertia busca en js/pages.'
  else
    sueltas=$("${GIT[@]}" ls-files 'resources/js/**/*.tsx' \
      | grep -vE '^resources/js/(pages|Components|Layouts|lib)/' || true)

    if [ -z "$sueltas" ]; then
      ok
    else
      fallo 'estos componentes no estan en pages/, Components/, Layouts/ ni lib/:'
      printf '     %s\n' "$sueltas"
    fi
  fi
else
  printf '   no hay componentes todavia\n'
fi

# ---------------------------------------------------------------------------
printf '\n'
if [ "$fallos" -eq 0 ]; then
  printf 'check.sh: 4 comprobaciones, 0 hallazgos\n'
  exit 0
fi
printf 'check.sh: %s comprobacion(es) con hallazgos\n' "$fallos"
exit 1
