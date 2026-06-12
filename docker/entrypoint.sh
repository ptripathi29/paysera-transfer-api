#!/bin/sh
set -e
mkdir -p var/cache var/log config/jwt
chmod -R 777 var 2>/dev/null || true
if [ ! -f config/jwt/private.pem ]; then
    openssl genrsa -out config/jwt/private.pem -aes256 -passout pass:${JWT_PASSPHRASE:-paysera_jwt_passphrase} 4096 2>/dev/null
    openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:${JWT_PASSPHRASE:-paysera_jwt_passphrase} 2>/dev/null
fi
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
