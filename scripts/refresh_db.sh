#!/usr/bin/env bash

# ask the following file exists, if it does, remove it and run the migrations
if [ -f "database/database.sqlite" ]; then
    echo "✓ Removing existing SQLite database"
    rm -f database/database.sqlite
else
    echo "✓ No existing SQLite database found, skipping removal"
fi

php artisan migrate --force
