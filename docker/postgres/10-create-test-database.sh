#!/bin/sh
#
# Se ejecuta una unica vez, cuando el volumen de PostgreSQL se crea vacio.
#
# Crea la base de datos de pruebas. Existe porque el ANEXO 1 seccion 68 exige
# que la suite corra sobre PostgreSQL real, y una suite que comparte base con
# el desarrollo la borra en cada ejecucion: RefreshDatabase no distingue.
#
# Si necesitas recrearla:  docker compose down -v  (borra TODOS los datos)

set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE "${POSTGRES_DB}_test" OWNER "${POSTGRES_USER}";
EOSQL

echo "init: base de datos ${POSTGRES_DB}_test creada"
