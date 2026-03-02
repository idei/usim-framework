#!/bin/bash

# Iniciar Mailpit en background (SMTP :1025, UI :8025)
if command -v mailpit &> /dev/null; then
    if ! pgrep -x mailpit > /dev/null; then
        nohup mailpit > /dev/null 2>&1 &
        echo "Mailpit iniciado: UI en http://localhost:8025, SMTP en puerto 1025"
    else
        echo "Mailpit ya está corriendo"
    fi
else
    echo "ADVERTENCIA: Mailpit no está instalado"
fi

# Crear enlaces simbólicos si los archivos existen
if [ -f /workspaces/usim-framework/mainsync.sh ]; then
    ln -sf /workspaces/usim-framework/mainsync.sh /usr/local/bin/mainsync
    chmod +x /workspaces/usim-framework/mainsync.sh
fi

if [ -f /workspaces/usim-framework/migrate.sh ]; then
    ln -sf /workspaces/usim-framework/migrate.sh /usr/local/bin/migrate
    chmod +x /workspaces/usim-framework/migrate.sh
fi
