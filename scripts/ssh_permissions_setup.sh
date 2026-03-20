#!/bin/bash

set -e

# Copiar el directorio .ssh al contenedor Docker
# docker cp ./Downloads/ssh_idecom/.ssh [container_id]:/root

SSH_DIR="/root/.ssh"

echo "Fixing SSH directory permissions..."

# Asegurar ownership correcto
chown -R root:root "$SSH_DIR"

# Permisos del directorio
chmod 700 "$SSH_DIR"

# Permisos de archivos privados (claves)
chmod 600 "$SSH_DIR"/id_ed25519_*

# Permisos de claves públicas
chmod 644 "$SSH_DIR"/*.pub

# Permisos de config
chmod 600 "$SSH_DIR/config"

# Permisos de known_hosts
chmod 644 "$SSH_DIR/known_hosts" "$SSH_DIR/known_hosts.old"

echo "SSH permissions fixed successfully."
