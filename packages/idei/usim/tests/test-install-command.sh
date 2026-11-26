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

echo "➡️  Probando usim:install..."
# Limpiar cambios previos
git checkout . > /dev/null 2>&1
git clean -fd > /dev/null 2>&1

# Ejecutar instalación
php artisan usim:install --force

# Verificar archivos clave
for required_file in \
    "app/UI/Layouts/MainLayout.php" \
    "app/UI/Layouts/WorkbenchLayout.php" \
    "app/UI/Layouts/workbench-layout.layout.yaml" \
    "app/UI/Navigation/Menus/MainMenu.php" \
    "app/UI/Screens/Concerns/HandlesShellActions.php" \
    "app/UI/Screens/Home.php" \
    "app/UI/Screens/Menu.php" \
    "app/UI/Screens/SidebarMenu.php" \
    "app/UI/Screens/SidebarSubmenuPanel.php" \
    "app/UI/Screens/StatusBar.php" \
    "app/UI/Screens/Admin/UsersManager.php" \
    "app/UI/Components/DataTable/UserTableModel.php" \
    "app/UI/Components/Modals/EditUserDialog.php" \
    "app/UI/Screens/Auth/Login.php" \
    "config/users.php" \
    "resources/legal/terms.en.md" \
    "resources/legal/terms.es.md"
do
    if [ ! -f "$required_file" ]; then
        echo "❌ Error: $required_file no se creó."
        exit 1
    fi
done

# Verificar sintaxis PHP
echo "🔍 Verificando sintaxis PHP en archivos generados..."
find app/UI -name "*.php" -print0 | xargs -0 -n1 php -l > /dev/null
find config -name "users.php" -print0 | xargs -0 -n1 php -l > /dev/null
php -l app/Models/User.php > /dev/null

# Verificar duplicados en .env
echo "🔍 Verificando duplicados en .env..."
DUPLICATES=$(grep "ADMIN_EMAIL" .env | wc -l)
if [ "$DUPLICATES" -gt 1 ]; then
    echo "❌ Error: Se encontraron duplicados en .env (ADMIN_EMAIL aparece $DUPLICATES veces)."
    grep -n "ADMIN_EMAIL" .env
    exit 1
fi

echo "✅ Instalación USIM OK"

echo "🎉 Todas las pruebas de instalación pasaron correctamente."
# La limpieza final se realiza automáticamente gracias al 'trap cleanup EXIT' definido al inicio
