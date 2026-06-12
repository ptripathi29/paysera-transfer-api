#!/bin/sh
set -e
mkdir -p config/jwt
PASSPHRASE="${JWT_PASSPHRASE:-paysera_jwt_passphrase}"
openssl genrsa -out config/jwt/private.pem -aes256 -passout pass:"$PASSPHRASE" 4096
openssl rsa -pubout -in config/jwt/private.pem -out config/jwt/public.pem -passin pass:"$PASSPHRASE"
echo "JWT keys generated in config/jwt/"
