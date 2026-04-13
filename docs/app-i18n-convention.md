# Convención i18n para la Aplicación USIM

## Visión General

La aplicación USIM utiliza un sistema i18n (internacionalización) basado en la BD con la capa **TranslationService** de Idei/Usim. Todos los textos visibles para el usuario deben ser internacionalizados siguiendo una convención de nombres jerárquica consistente.

## Convención de Nombres de Slugs

Los slugs de traducción siguen el patrón: `<contexto>.<subsistema>.<elemento>.<propiedad>`

### Contextos Principales

| Contexto | Descripción | Ejemplo |
|----------|-------------|---------|
| `screen.*` | Pantallas de la aplicación (screens) | `screen.auth.login.title` |
| `service.*` | Mensajes de servicios de negocio | `service.auth.login.success` |
| `controller.*` | Respuestas de controladores HTTP | `controller.file.processing_error` |
| `component.*` | Componentes específicos de la app | `component.user_card.title` |
| `toast.*` | Notificaciones breves (toasts) | `toast.success.saved` |
| `validation.*` | Mensajes de validación de formularios | `validation.email.required` |
| `modal.*` | Diálogos/modales específicos de app | `modal.confirm_delete.title` |
| `table.*` | Tablas y columnas de datos | `table.users.column_name` |
| `menu.*` | Elementos de menú | `menu.sidebar.dashboard` |

### Subsistemas Comunes (en `screen.*`)

- `auth` - Pantallas de autenticación
- `admin` - Pantallas de administración
- `demo` - Pantallas de demostración
- `dashboard` - Panel principal
- `settings` - Configuración

### Estructura Completa de Ejemplo

```
screen.auth.login.title                    → "User Login" (en) / "Iniciar Sesión" (es)
screen.auth.login.email_label              → "Email" / "Correo"
screen.auth.login.email_placeholder        → "Enter your email" / "Ingresa tu correo"
screen.auth.login.password_label           → "Password" / "Contraseña"
screen.auth.login.button_login             → "Login" / "Iniciar Sesión"
screen.auth.login.authenticated_error      → "Unable to resolve authenticated user." / ...
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
  "screen.auth.login.title": "User Login",
  "screen.auth.login.email_label": "Email",
  "screen.auth.login.email_placeholder": "Enter your email",
  "screen.auth.login.password_label": "Password",
  "screen.auth.login.button_login": "Login"
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
$container->title(t('screen.auth.profile.title'));
$input->label(t('screen.auth.profile.email_label'));
$input->placeholder(t('screen.auth.profile.name_placeholder'));
$this->toast(t('screen.auth.profile.save_success'), 'success');
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
    'message' => t('service.auth.login.invalid_credentials'),
    'errors' => ['email' => [t('service.auth.login.credentials_incorrect')]],
];
```

### Con Parámetros

```php
// Usando placeholders (Laravel style)
t('screen.auth.profile.save_error', ['error' => $exception->getMessage()])

// En JSON:
"screen.auth.profile.save_error": "Error al guardar el perfil: :error"
```

### En Controllers

```php
return [
    'success' => false,
    'error' => t('controller.file.processing_error'),
];
```

## Proceso de Seeding

### 1. Agregar Traducciones

Edita los archivos en `/database/translations/`:

**en.json:**
```json
{
  "new_feature.title": "New Feature"
}
```

**es.json:**
```json
{
  "new_feature.title": "Nueva Característica"
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
>>> app('Idei\Usim\Services\Support\TranslationService')->getTextKey('screen.auth.profile.title')

# Obtener valor en idioma específico
>>> t('screen.auth.profile.title', locale: 'es')
```

## Grupos Automáticos

El seeder extrae el **grupo** automáticamente de la primera parte del slug:

- `screen.*` → grupo: `screen`
- `service.*` → grupo: `service`
- `controller.*` → grupo: `controller`

Esto facilita la gestión y búsqueda de traducciones en la UI de administración.

## Fallback de Idiomas

Si una traducción no existe para el idioma solicitado, el sistema usa el fallback configurado:

```php
// config/usim.php
'translation_fallback_language' => 'en',  // Fallback por defecto
```

Entonces si solicitas `t('screen.auth.login.title', locale: 'fr')` y 'fr' no existe, devolverá la versión en 'en'.

## Validación de Slugs

El sistema autodetecta si un slug está pre-registrado o es auto-generado:

```php
// En TranslationAutoRegistrar (packages/idei/usim/src/...)
// RegEx: /^(?:[a-z0-9]{2,})(?:[._][a-z0-9]{2,})*$/

// ✅ Válido (slug pre-registrado)
$value = 'screen.auth.login.title'   // Coincide patrón

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
1. Define un slug siguiendo la convención (`<contexto>.<subsistema>.<elemento>.<prop>`)
2. Agrega el texto en `en.json` y su traducción en `es.json`
3. Actualiza el código a usar `t('slug')`

### Paso 3: Validar

```bash
# Ejecutar seeder
php artisan db:seed --class=AppTranslationSeeder

# Verificar en desarrollo
php artisan tinker
>>> t('screen.auth.login.title', locale: 'en')
>>> t('screen.auth.login.title', locale: 'es')
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
- [ ] Diseñar slugs seguiendo `<contexto>.<subsistema>.<elemento>.<prop>`
- [ ] Agregar claves en `en.json` (versión original/fallback)
- [ ] Agregar claves en `es.json` (traducción)
- [ ] Actualizar código en screens/services/controllers a usar `t('slug')`
- [ ] Ejecutar `php artisan db:seed --class=AppTranslationSeeder`
- [ ] Validar en tinker que las traducciones se cargaron
- [ ] Verificar fallback a inglés si es necesario
- [ ] Documentar el nuevo contexto/subsistema en esta guía (si aplica)

