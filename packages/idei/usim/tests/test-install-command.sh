#!/bin/bash
set -e

# Función de limpieza que se ejecuta siempre al finalizar el script
cleanup() {
    # Solo limpiar si NO estamos en la comprobación inicial de git status
    if [ "$1" != "check_only" ]; then
        echo ""
        echo "🧹 Restaurando estado original (cleanup)..."
        git checkout . > /dev/null 2>&1
        git clean -fd > /dev/null 2>&1
    fi
}

# Registrar el trap para ejecutar cleanup al salir (ya sea por éxito, error o Ctrl+C)
trap cleanup EXIT

# Comprobar si hay cambios sin guardar
if [[ -n $(git status --porcelain) ]]; then
  echo "⚠️  Tienes cambios pendientes en git. Por favor haz commit o stash antes de correr este script de prueba destructiva."
  # Desactivamos el trap para este error específico porque no queremos hacer git clean si ya había cosas sucias
  trap - EXIT
  exit 1
fi

echo "➡️  Probando usim:install --preset=minimal..."
# Limpiar cambios previos
git checkout . > /dev/null 2>&1
git clean -fd > /dev/null 2>&1

# Ejecutar instalación mínima
php artisan usim:install --preset=minimal --force

# Verificar archivos clave
if [ ! -f "app/UI/Screens/Home.php" ]; then
    echo "❌ Error: Home.php no se creó."
    exit 1
fi
if [ ! -f "app/UI/Screens/Menu.php" ]; then
    echo "❌ Error: Menu.php no se creó."
    exit 1
else
    # Verificar que es la versión minimal
    if ! grep -q "Menu Service (Minimal)" app/UI/Screens/Menu.php; then
        echo "❌ Error: Menu.php no parece ser la versión Minimal."
        head -n 20 app/UI/Screens/Menu.php # Mostrar las primeras líneas para depurar
        exit 1
    fi
fi

# Verificar sintaxis PHP
echo "🔍 Verificando sintaxis PHP en archivos generados..."
find app/UI -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null

echo "✅ Preset Minimal OK"

echo "➡️  Probando usim:install --preset=full..."
# Limpiar cambios previos
git checkout . > /dev/null 2>&1
git clean -fd > /dev/null 2>&1

# Ejecutar instalación completa
php artisan usim:install --preset=full --force

# Verificar archivos clave adicionales
if [ ! -f "app/UI/Screens/Auth/Login.php" ]; then
    echo "❌ Error: Login.php no se creó."
    exit 1
fi

if grep -q "Menu (Minimal)" app/UI/Screens/Menu.php; then
    echo "❌ Error: Menu.php parece ser la versión Minimal (debería ser Full)."
    exit 1
fi
if [ ! -f "config/users.php" ]; then
    echo "❌ Error: config/users.php no se creó."
    exit 1
fi

# Verificar sintaxis PHP
echo "🔍 Verificando sintaxis PHP en archivos generados..."
find app/UI -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null
find config -name "users.php" -print0 | xargs -0 -n1 php -l > /dev/null
php -l app/Models/User.php > /dev/null

echo "✅ Preset Full OK"

echo "🎉 Todas las pruebas de instalación pasaron correctamente."
# La limpieza final se realiza automáticamente gracias al 'trap cleanup EXIT' definido al inicio
