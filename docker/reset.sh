#!/usr/bin/env bash
# Borra la base de datos y la configuración, y reinstala osTicket desde cero.
set -euo pipefail

cd "$(dirname "$0")/.."

read -r -p "Esto borra TODOS los datos de la base de desarrollo. ¿Continuar? [s/N] " ok
[[ "$ok" =~ ^[sS]$ ]] || exit 1

docker compose down -v
rm -f include/ost-config.php
docker compose up -d --build
./docker/install.sh
