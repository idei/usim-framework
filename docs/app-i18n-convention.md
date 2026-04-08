# Convención i18n para la Aplicación USIM

## Visión General

La aplicación USIM utiliza un sistema i18n (internacionalización) basado en la BD con la capa **TranslationService** de Idei/Usim. Todos los textos visibles para el usuario deben ser internacionalizados siguiendo una convención de nombres jerárquica consistente.

## Convención de Nombres de Slugs

Los slugs de traducción siguen el patrón: `app.<contexto>.<subsistema>.<elemento>.<propiedad>`

### Contextos Principales

| Contexto | Descripción | Ejemplo |
|----------|-------------|---------|
| `app.screen.*` | Pantallas de la aplicación (screens) | `app.screen.auth.login.title` |
| `app.service.*` | Mensajes de servicios de negocio | `app.service.auth.login.success` |
| `app.controller.*` | Respuestas de controladores HTTP | `app.controller.file.processing_error` |
| `app.component.*` | Componentes específicos de la app | `app.component.user_card.title` |
| `app.toast.*` | Notificaciones breves (toasts) | `app.toast.success.saved` |
| `app.validation.*` | Mensajes de validación de formularios | `app.validation.email.required` |
| `app.modal.*` | Diálogos/modales específicos de app | `app.modal.confirm_delete.title` |
| `app.table.*` | Tablas y columnas de datos | `app.table.users.column_name` |
| `app.menu.*` | Elementos de menú | `app.menu.sidebar.dashboard` |

### Subsistemas Comunes (en `app.screen.*`)

- `auth` - Pantallas de autenticación
- `admin` - Pantallas de administración
- `demo` - Pantallas de demostración
- `dashboard` - Panel principal
- `settings` - Configuración

### Estructura Completa de Ejemplo

```
app.screen.auth.login.title                    → "User Login" (en) / "Iniciar Sesión" (es)
app.screen.auth.login.email_label              → "Email" / "Correo"
app.screen.auth.login.email_placeholder        → "Enter your email" / "Ingresa tu correo"
app.screen.auth.login.password_label           → "Password" / "Contraseña"
app.screen.auth.login.button_login             → "Login" / "Iniciar Sesión"
app.screen.auth.login.authenticated_error      → "Unable to resolve authenticated user." / ...
```

## Archivos de Traducción

Las traducciones se organizan en archivos JSON en `/database/translations/`:

```
database/
├── translations/
│   ├── en.json          # Traducciones para inglés (fallback)
│   └── es.json          # Traducciones para español
├── seeders/
│   ├── AppTranslationSeeder.php      # Carga los JSONs en la BD
│   └── ...
```

### Estructura de Archivo JSON

```json
{
  "app.screen.auth.login.title": "User Login",
  "app.screen.auth.login.email_label": "Email",
  "app.screen.auth.login.email_placeholder": "Enter your email",
  "app.screen.auth.login.password_label": "Password",
  "app.screen.auth.login.button_login": "Login"
}
```

## Cómo Usar en Código

### En Screens (AbstractUIService)

```php
// ❌ Antes (hardcoded)
$container->title('Mi Perfil');
$input->label('Email');
$input->placeholder('Tu nombre completo');
$this->toast('Perfil actualizado', 'success');

// ✅ Después (i18n)
$container->title(t('app.screen.auth.profile.title'));
$input->label(t('app.screen.auth.profile.email_label'));
$input->placeholder(t('app.screen.auth.profile.name_placeholder'));
$this->toast(t('app.screen.auth.profile.save_success'), 'success');
```

### En Servicios (Business Logic)

```php
// ❌ Antes
return [
    'status' => 'error',
    'message' => 'Credenciales inválidas',
    'errors' => ['email' => ['Invalid credentials']],
];

// ✅ Después
return [
    'status' => 'error',
    'message' => t('app.service.auth.login.invalid_credentials'),
    'errors' => ['email' => [t('app.service.auth.login.credentials_incorrect')]],
];
```

### Con Parámetros

```php
// Usando placeholders (Laravel style)
t('app.screen.auth.profile.save_error', ['error' => $exception->getMessage()])

// En JSON:
"app.screen.auth.profile.save_error": "Error al guardar el perfil: :error"
```

### En Controllers

```php
return [
    'success' => false,
    'error' => t('app.controller.file.processing_error'),
];
```

## Proceso de Seeding

### 1. Agregar Traducciones

Edita los archivos en `/database/translations/`:

**en.json:**
```json
{
  "app.new_feature.title": "New Feature"
}
```

**es.json:**
```json
{
  "app.new_feature.title": "Nueva Característica"
}
```

### 2. Ejecutar Seeder

```bash
# Ejecutar solo AppTranslationSeeder
php artisan db:seed --class=AppTranslationSeeder

# O ejecutar todos los seeders (incluyendo AppTranslationSeeder)
php artisan migrate:fresh --seed
```

### 3. Verificar en Database

```bash
php artisan tinker

# Listar claves registradas
>>> app('Idei\Usim\Services\Support\TranslationService')->getTextKey('app.screen.auth.profile.title')

# Obtener valor en idioma específico
>>> t('app.screen.auth.profile.title', locale: 'es')
```

## Grupos Automáticos

El seeder extrae el **grupo** automáticamente de la primera parte del slug:

- `app.screen.*` → grupo: `app.screen`
- `app.service.*` → grupo: `app.service`
- `app.controller.*` → grupo: `app.controller`

Esto facilita la gestión y búsqueda de traducciones en la UI de administración.

## Fallback de Idiomas

Si una traducción no existe para el idioma solicitado, el sistema usa el fallback configurado:

```php
// config/usim.php
'translation_fallback_language' => 'en',  // Fallback por defecto
```

Entonces si solicitas `t('app.screen.auth.login.title', locale: 'fr')` y 'fr' no existe, devolverá la versión en 'en'.

## Validación de Slugs

El sistema autodetecta si un slug está pre-registrado o es auto-generado:

```php
// En TranslationAutoRegistrar (packages/idei/usim/src/...)
// RegEx: /^(?:[a-z0-9]{2,})(?:[._][a-z0-9]{2,})*$/

// ✅ Válido (slug pre-registrado)
$value = 'app.screen.auth.login.title'   // Coincide patrón

// ⚠️ Auto-generado (no-matchea patrón exacto o valores arbitrarios)
$value = 'Some arbitrary text from user'  // No coincide
```

## Migrando Código Existente

### Paso 1: Identificar Textos

```bash
# Buscar toasts
grep -r "->toast(" app/

# Buscar labels
grep -r "->label(" app/

# Buscar placeholders
grep -r "->placeholder(" app/
```

### Paso 2: Crear Slugs y Traducciones

Para cada texto encontrado:
1. Define un slug siguiendo la convención (`app.<contexto>.<subsistema>.<elemento>.<prop>`)
2. Agrega el texto en `en.json` y su traducción en `es.json`
3. Actualiza el código a usar `t('slug')`

### Paso 3: Validar

```bash
# Ejecutar seeder
php artisan db:seed --class=AppTranslationSeeder

# Verificar en desarrollo
php artisan tinker
>>> t('app.screen.auth.login.title', locale: 'en')
>>> t('app.screen.auth.login.title', locale: 'es')
```

## Recomendaciones

1. **Granularidad**: Crea slugs específicos para cada elemento visible (label, placeholder, button, error, etc.)
2. **Consistencia**: Mantén el formato de slug consistente dentro del contexto
3. **Descripción**: El seeder genera descripciones automáticas; son suficientemente legibles
4. **Parametrización**: Usa placeholders (`:nombre`) para valores dinámicos, no concatenación
5. **Versionado**: Ambos `en.json` y `es.json` deben ir al repositorio Git
6. **Generador**: El Seeder no sobrescribe claves existentes (usa `force: false` por defecto)

## Convención USIM vs. App

| Aspecto | USIM Package | App |
|---------|--------------|-----|
| Prefijo | `usim.*` | `app.*` |
| Ubicación Stubs | `packages/idei/usim/stubs/` | `app/` |
| JSONs | En package (si aplica) | `/database/translations/` |
| Seeder | `UsimTranslationSeeder` | `AppTranslationSeeder` |
| Scope | Framework reusable | Aplicación específica |

## Checklist para Agregar Nueva Feature i18n

- [ ] Identificar todos los textos visibles del usuario
- [ ] Diseñar slugs seguiendo `app.<contexto>.<subsistema>.<elemento>.<prop>`
- [ ] Agregar claves en `en.json` (versión original/fallback)
- [ ] Agregar claves en `es.json` (traducción)
- [ ] Actualizar código en screens/services/controllers a usar `t('slug')`
- [ ] Ejecutar `php artisan db:seed --class=AppTranslationSeeder`
- [ ] Validar en tinker que las traducciones se cargaron
- [ ] Verificar fallback a inglés si es necesario
- [ ] Documentar el nuevo contexto/subsistema en esta guía (si aplica)

