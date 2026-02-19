#!/bin/bash

# Crear enlaces simbólicos si los archivos existen
if [ -f /workspaces/usim-framework/mainsync.sh ]; then
    ln -sf /workspaces/usim-framework/mainsync.sh /usr/local/bin/mainsync
    chmod +x /workspaces/usim-framework/mainsync.sh
fi

if [ -f /workspaces/usim-framework/migrate.sh ]; then
    ln -sf /workspaces/usim-framework/migrate.sh /usr/local/bin/migrate
    chmod +x /workspaces/usim-framework/migrate.sh
fi
