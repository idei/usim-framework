#!/bin/bash
git fetch --all --prune

for branch in $(git branch --format='%(refname:short)'); do
    upstream=$(git for-each-ref --format='%(upstream:short)' refs/heads/"$branch")
    upstream_track=$(git for-each-ref --format='%(upstream:track)' refs/heads/"$branch")

    if [ -z "$upstream" ]; then
        echo "La rama '$branch' no tiene remoto configurado. Eliminando..."
        # git branch -d "$branch"
    elif [ "$upstream_track" = "[gone]" ]; then
        echo "La rama '$branch' tenía remoto '$upstream' que ya no existe. Eliminando..."
        git branch -d "$branch"
    fi
done
echo "Limpieza de ramas locales completada."
