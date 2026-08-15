# Imagen para pruebas de navegador.
#
# La oficial de Playwright trae los navegadores ya instalados. Construirla
# sobre node:22 y descargarlos en cada arranque anadiria varios minutos y
# cientos de megas a cada ejecucion.
#
# La version DEBE coincidir con la de @playwright/test en e2e/package.json.
# Si divergen, Playwright se niega a arrancar diciendolo claramente.
#
# ---------------------------------------------------------------------------
# Aqui NO se toca ningun usuario, y costo tres intentos llegar a eso.
#
# La imagen trae pwuser con uid 1001, y la carpeta e2e del host pertenece a
# 1000. El primer planteamiento fue renombrar pwuser a 1000 con usermod. Es
# imposible: la imagen YA tiene un usuario `node` con uid 1000 y gid 1000,
# heredado de Ubuntu. groupmod fallo con "GID already exists" y usermod con
# "UID already exists".
#
# El planteamiento estaba equivocado. No hace falta modificar a nadie: basta
# con que el contenedor CORRA como 1000:1000, que es un usuario que ya existe.
# Eso se declara en docker-compose.yml con `user:`, no aqui.
#
# Dejarlo escrito porque el arreglo intuitivo —volver a usermod— es el que no
# funciona, y sin esta nota alguien lo intentaria otra vez.
# ---------------------------------------------------------------------------
FROM mcr.microsoft.com/playwright:v1.56.0-noble

WORKDIR /e2e
