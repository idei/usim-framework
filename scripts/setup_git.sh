#!/usr/bin/env bash

set -e

echo "---------------------------------------"
echo "USIM - Git repository setup"
echo "---------------------------------------"

# Verificar que estamos en un repo git
if [ ! -d ".git" ]; then
  echo "Error: este directorio no es un repositorio git."
  exit 1
fi

echo ""
echo "Remotos actuales:"
git remote -v || true

echo ""
echo "Configurando remoto usim-public..."

if git remote get-url usim-public >/dev/null 2>&1; then
  echo "El remoto usim-public ya existe."
else
  git remote add usim-public https://github.com/idei/usim.git
  echo "Remoto usim-public agregado."
fi

echo ""
echo "Actualizando información del repositorio..."

git fetch origin --prune --tags
git fetch usim-public --prune --tags

echo ""
echo "Ramas remotas disponibles:"
git branch -r

echo ""
echo "Tags disponibles:"
git tag -l

echo ""
echo "---------------------------------------"
echo "Configuración completada"
echo "---------------------------------------"
