#!/bin/sh
#
# Base de datos para las pruebas de navegador.
#
# Separada de encuestas_test a proposito: RefreshDatabase vacia esa base en
# cada prueba de PHPUnit, asi que compartirla significaria que una suite borra
# los datos de la otra a mitad de ejecucion.
#
# Y separada de encuestas para que las pruebas no ensucien lo que estas
# mirando en el navegador mientras trabajas.

set -e

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
    CREATE DATABASE "${POSTGRES_DB}_e2e" OWNER "${POSTGRES_USER}";
EOSQL

echo "init: base de datos ${POSTGRES_DB}_e2e creada"
