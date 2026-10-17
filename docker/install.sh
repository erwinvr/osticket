#!/usr/bin/env bash
# Instala osTicket en el entorno Docker de desarrollo de forma no interactiva.
# Ejecutar desde la raíz del repo después de `docker compose up -d --build`.
set -euo pipefail

cd "$(dirname "$0")/.."

CONFIG=include/ost-config.php
ADMIN_USER=${ADMIN_USER:-ostadmin}
ADMIN_PASS=${ADMIN_PASS:-Admin1234!}
ADMIN_EMAIL=${ADMIN_EMAIL:-admin@example.com}
HELPDESK_EMAIL=${HELPDESK_EMAIL:-support@example.com}
TIMEZONE=${TIMEZONE:-UTC}

if [ -f "$CONFIG" ] && grep -qi "define('OSTINSTALLED',TRUE);" "$CONFIG"; then
    echo "osTicket ya está instalado ($CONFIG). Para reinstalar: ./docker/reset.sh"
    exit 0
fi

cp include/ost-sampleconfig.php "$CONFIG"
chmod 0666 "$CONFIG"

echo "Esperando al servidor web..."
for _ in $(seq 1 30); do
    docker compose exec -T web curl -fs -o /dev/null http://localhost/setup/install.php && break
    sleep 2
done

docker compose exec -T web bash -s <<EOF
set -e
J=/tmp/ost-install.cookies
U=http://localhost/setup/install.php
rm -f \$J
curl -fs -c \$J -b \$J -o /dev/null \$U
curl -fs -c \$J -b \$J -o /dev/null -d s=prereq \$U
curl -fs -c \$J -b \$J -o /dev/null -d s=config \$U
curl -fs -c \$J -b \$J -o /tmp/ost-install.html \$U \
    --data-urlencode s=install \
    --data-urlencode 'name=osTicket Dev' \
    --data-urlencode 'email=$HELPDESK_EMAIL' \
    --data-urlencode lang_id=en_US \
    --data-urlencode fname=Admin \
    --data-urlencode lname=Dev \
    --data-urlencode 'admin_email=$ADMIN_EMAIL' \
    --data-urlencode 'username=$ADMIN_USER' \
    --data-urlencode 'passwd=$ADMIN_PASS' \
    --data-urlencode 'passwd2=$ADMIN_PASS' \
    --data-urlencode prefix=ost_ \
    --data-urlencode dbhost=db \
    --data-urlencode dbname=osticket \
    --data-urlencode dbuser=osticket \
    --data-urlencode dbpass=osticket \
    --data-urlencode 'timezone=$TIMEZONE'
EOF

if ! grep -qi "define('OSTINSTALLED',TRUE);" "$CONFIG"; then
    echo "La instalación falló. Respuesta del instalador:"
    docker compose exec -T web sh -c "grep -io 'class=\"error\"[^<]*<[^<]*' /tmp/ost-install.html || cat /tmp/ost-install.html" | head -40
    exit 1
fi

chmod 0644 "$CONFIG"

PORT=${WEB_PORT:-8081}
cat <<MSG

osTicket instalado.
  Portal clientes : http://localhost:$PORT/
  Panel agentes   : http://localhost:$PORT/scp/   ($ADMIN_USER / $ADMIN_PASS)
  Correos (Mailpit): http://localhost:${MAIL_UI_PORT:-8025}/
MSG
