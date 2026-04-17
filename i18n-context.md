# i18n Context Handoff (USIM Framework)

Fecha de corte: 2026-04-07
Repo: usim-framework
Branch de trabajo: i18n
Estado del working tree al cierre de este contexto: limpio (sin cambios pendientes)

## 1) Objetivo de esta iteracion
Migrar textos hardcodeados de package USIM (core + stubs) hacia i18n con keys estables usando t('usim...'), y dejar base de traducciones lista para instalacion/seed en apps consumidoras.

## 2) Decisiones tecnicas tomadas
- Se adopto enfoque DB-backed i18n ya existente en USIM (no archivos lang del paquete).
- Se uso helper t(...) como mecanismo principal para textos del framework y scaffolding.
- Se creo una convencion de namespaces para keys, priorizando estabilidad y lectura por dominio.
- Se amplio el seeder de traducciones del paquete para incluir todas las keys nuevas usadas por core/stubs.

## 3) Convencion de keys aplicada
- usim.component.* para defaults de componentes reutilizables.
- usim.dialog.* para textos de dialogos framework.
- usim.time_unit.* para labels de unidades de tiempo.
- usim.common.* para textos comunes en scaffolding.
- usim.auth.* para pantallas/modales auth scaffold.
- usim.admin.* para dashboard/admin scaffold.
- usim.menu.* para menu scaffold.
- usim.service.* para mensajes de servicios scaffold (responses/validaciones).

## 4) Archivos modificados

### Core del package
- [packages/idei/usim/src/Services/Components/Uploader.php](packages/idei/usim/src/Services/Components/Uploader.php)
- [packages/idei/usim/src/Services/Modals/ConfirmDialogService.php](packages/idei/usim/src/Services/Modals/ConfirmDialogService.php)
- [packages/idei/usim/src/Services/Enums/DialogType.php](packages/idei/usim/src/Services/Enums/DialogType.php)
- [packages/idei/usim/src/Services/Enums/TimeUnit.php](packages/idei/usim/src/Services/Enums/TimeUnit.php)

### Stubs de screens/components/services
- [packages/idei/usim/stubs/screens/Menu.php.stub](packages/idei/usim/stubs/screens/Menu.php.stub)
- [packages/idei/usim/stubs/screens/Auth/Login.php.stub](packages/idei/usim/stubs/screens/Auth/Login.php.stub)
- [packages/idei/usim/stubs/screens/Auth/ForgotPassword.php.stub](packages/idei/usim/stubs/screens/Auth/ForgotPassword.php.stub)
- [packages/idei/usim/stubs/screens/Auth/ResetPassword.php.stub](packages/idei/usim/stubs/screens/Auth/ResetPassword.php.stub)
- [packages/idei/usim/stubs/screens/Auth/Profile.php.stub](packages/idei/usim/stubs/screens/Auth/Profile.php.stub)
- [packages/idei/usim/stubs/screens/Auth/EmailVerified.php.stub](packages/idei/usim/stubs/screens/Auth/EmailVerified.php.stub)
- [packages/idei/usim/stubs/screens/Admin/Dashboard.php.stub](packages/idei/usim/stubs/screens/Admin/Dashboard.php.stub)
- [packages/idei/usim/stubs/components/Modals/LoginDialog.php.stub](packages/idei/usim/stubs/components/Modals/LoginDialog.php.stub)
- [packages/idei/usim/stubs/components/Modals/RegisterDialog.php.stub](packages/idei/usim/stubs/components/Modals/RegisterDialog.php.stub)
- [packages/idei/usim/stubs/components/Modals/EditUserDialog.php.stub](packages/idei/usim/stubs/components/Modals/EditUserDialog.php.stub)
- [packages/idei/usim/stubs/components/DataTable/UserApiTableModel.php.stub](packages/idei/usim/stubs/components/DataTable/UserApiTableModel.php.stub)
- [packages/idei/usim/stubs/services/Auth/LoginService.php.stub](packages/idei/usim/stubs/services/Auth/LoginService.php.stub)
- [packages/idei/usim/stubs/services/Auth/RegisterService.php.stub](packages/idei/usim/stubs/services/Auth/RegisterService.php.stub)
- [packages/idei/usim/stubs/services/Auth/PasswordService.php.stub](packages/idei/usim/stubs/services/Auth/PasswordService.php.stub)
- [packages/idei/usim/stubs/services/User/UserService.php.stub](packages/idei/usim/stubs/services/User/UserService.php.stub)

### Seeder y documentacion
- [packages/idei/usim/stubs/seeders/UsimTranslationSeeder.php.stub](packages/idei/usim/stubs/seeders/UsimTranslationSeeder.php.stub)
- [packages/idei/usim/README.md](packages/idei/usim/README.md)
- [packages/idei/usim/CHANGELOG.md](packages/idei/usim/CHANGELOG.md)

## 5) Validaciones ejecutadas y resultado

1. Install/seed en entorno dev consumidor
- Comando: php artisan usim:install --no-interaction (ejecutado en /workspaces/usim-framework/dev)
- Resultado: OK, scaffolding publicado.

2. Error detectado en seed inicial
- Comando: php artisan db:seed --class=UsimSeeder --no-interaction (dev)
- Resultado: fallo por tablas usim_* no creadas en sqlite dev.
- Accion correctiva: correr migrate.

3. Migraciones + seed reintentado
- Comando: php artisan migrate --no-interaction (dev)
- Resultado: OK, tablas usim_languages/usim_text_keys/usim_text_values creadas.
- Comando: php artisan db:seed --class=UsimSeeder --no-interaction (dev)
- Resultado: OK.

4. Verificacion de key nueva en BD dev
- Comando: php artisan tinker --execute="echo \Idei\Usim\Models\UsimTextKey::where('key','usim.auth.login.title')->exists() ? 'KEY_OK' : 'KEY_MISSING';"
- Resultado: KEY_OK.

5. Integridad key usage vs seeder
- Se corrio script de cobertura para comparar todas las referencias t('usim...') en src/stubs contra keys definidas en UsimTranslationSeeder.
- Resultado: sin faltantes.

## 6) Criterio de cierre alcanzado
- Textos de UI y mensajes de scaffold migrados a keys i18n en package + stubs principales.
- Seeder de traducciones con keyset base amplio para que la app consumidora tenga traducciones iniciales al instalar/seed.
- Documentacion y changelog actualizados para continuidad.

## 7) Riesgos y limites conocidos
- No todos los textos posibles del repo completo fueron forzados a key si no eran claramente user-facing (ejemplos de comentarios, iconos emoji, cadenas vacias).
- Si en otra rama se agregaron nuevos stubs o componentes, hay que repetir barrido de literales.
- El comando de chequeo de cobertura debe correrse con rutas absolutas o desde la raiz correcta para evitar falsos positivos por cwd.

## 8) Checklist para continuar en otro chat
1. Confirmar branch y estado:
- git status --short

2. Revalidar cobertura de keys:
- Buscar referencias t('usim...) en src/stubs.
- Compararlas con keys del seeder [packages/idei/usim/stubs/seeders/UsimTranslationSeeder.php.stub](packages/idei/usim/stubs/seeders/UsimTranslationSeeder.php.stub)

3. Si se agregan textos nuevos:
- Aplicar convencion de namespaces.
- Agregar key al seeder.
- Probar install + migrate + seed en /dev.

4. Antes de release del package:
- Actualizar CHANGELOG y README si hay cambios de contrato o de convencion.
- Validar flujo de instalacion en app consumidora local.

## 9) Prompt sugerido para retomar en otra computadora
Contexto: monorepo Laravel + package local USIM. Ya se migro i18n en core/stubs a t('usim...') y se amplio UsimTranslationSeeder. Quiero que continues con auditoria i18n incremental: detecta nuevos textos hardcodeados user-facing en packages/idei/usim/src y packages/idei/usim/stubs, migralos a keys siguiendo convencion usim.component/usim.dialog/usim.time_unit/usim.*, agrega keys faltantes al seeder, valida install+migrate+seed en /dev, y actualiza docs/changelog si cambian reglas.
