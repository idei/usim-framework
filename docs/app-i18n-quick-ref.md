# i18n Quick Reference - App Translations

## Suma: Convención de Slugs

```
app.<tipo>.<subsistema>.<elemento>.<propiedad>
    ↓        ↓            ↓         ↓
  screen   auth        login      title
```

## Tipos Principales

| Tipo | Ubicación | Ejemplo |
|------|-----------|---------|
| `screen` | `app/UI/Screens/` | `app.screen.auth.login.title` |
| `service` | `app/Services/` | `app.service.auth.login.success` |
| `controller` | `app/Http/Controllers/` | `app.controller.file.error` |

## Workflow Rápido

### 1️⃣ Encuentra texto hardcodeado

```php
$this->toast('Perfil actualizado', 'success');  // ❌ Hardcoded
```

### 2️⃣ Diseña un slug

Siguiendo: `app.screen.auth.profile<...>`

```
app.screen.auth.profile.save_success
```

### 3️⃣ Agrega en JSONs

**`database/translations/en.json`:**
```json
"app.screen.auth.profile.save_success": "Profile updated"
```

**`database/translations/es.json`:**
```json
"app.screen.auth.profile.save_success": "Perfil actualizado"
```

### 4️⃣ Actualiza el código

```php
$this->toast(t('app.screen.auth.profile.save_success'), 'success');  // ✅
```

### 5️⃣ Seed & Verify

```bash
php artisan db:seed --class=AppTranslationSeeder
php artisan tinker
>>> t('app.screen.auth.profile.save_success', locale: 'es')
# => "Perfil actualizado"
```

## Patrones Comunes

### Input Fields

```php
// ❌
->label('Full Name')
->placeholder('Enter your name')

// ✅
->label(t('app.screen.auth.profile.name_label'))
->placeholder(t('app.screen.auth.profile.name_placeholder'))
```

### Buttons

```php
// ❌
->label('Save')

// ✅
->label(t('app.screen.auth.profile.save_button'))
```

### Toasts/Messages

```php
// ❌
$this->toast('Success!', 'success');

// ✅
$this->toast(t('app.screen.auth.profile.save_success'), 'success');
```

### Errors en Servicios

```php
// ❌
return ['status' => 'error', 'message' => 'Invalid credentials'];

// ✅
return ['status' => 'error', 'message' => t('app.service.auth.login.invalid_credentials')];
```

### Con Parámetros

```php
// ❌
$this->toast("Error: {$error}", 'error');

// ✅
->placeholder(t('app.screen.auth.profile.save_error', ['error' => $error->getMessage()]))

// JSON:
"app.screen.auth.profile.save_error": "Error saving profile: :error"
```

## Jerarquía de Formatos

```
app.screen.auth.login       ← Subsistema
           .email_label     ← Elemento + propiedad
           .placeholder     
           .button
           .error
```

## Convención por Contexto

### Auth Screens

```
app.screen.auth.login.*
app.screen.auth.register.*
app.screen.auth.profile.*
app.screen.auth.forgot_password.*
app.screen.auth.reset_password.*
app.screen.auth.email_verified.*
```

### Admin Screens

```
app.screen.admin.dashboard.*
app.screen.admin.users.*
app.screen.admin.settings.*
```

### Demo Screens

```
app.screen.demo.carousel.*
app.screen.demo.table.*
app.screen.demo.ui_components.*
```

### Services

```
app.service.auth.login.*       → LoginService messages
app.service.auth.register.*    → RegisterService messages
app.service.auth.password.*    → PasswordService messages
app.service.user.*             → UserService messages
```

### Controllers

```
app.controller.file.*          → FileController messages
app.controller.log_viewer.*    → LogViewerController messages
app.controller.documentation.* → DocumentationController messages
```

## ⚠️ Common Mistakes

| ❌ Incorrecto | ✅ Correcto | Por qué |
|------------|---------|---------|
| `auth.login.title` | `app.screen.auth.login.title` | Necesita prefijo `app` y contexto `screen` |
| `screen.profile.name` | `app.screen.auth.profile.name_label` | Necesita subsistema y propiedad |
| `A`.`random`.`string` | `app.screen.auth.demo.item` | Usa minúsculas + puntos como separadores |
| `t('hardcoded')` | `t('app.service.auth.....')` | Siempre usa slug (no texto literal) |

## Archivos que Editar

```
database/
├── translations/
│   ├── en.json              👈 Editar aquí (traductora)
│   └── es.json              👈 Editar aquí (traductora)
├── seeders/
│   └── AppTranslationSeeder.php  (NO modificar - automático)

app/
├── UI/Screens/
│   └── Auth/Login.php            👈 Editar aquí (developer)
├── Services/
│   └── Auth/LoginService.php      👈 Editar aquí (developer)
└── Http/Controllers/
    └── ...                        👈 Editar aquí (developer)

docs/
└── app-i18n-convention.md         👈 Guía completa
```

## Testing i18n

```bash
# Verificar que la clave existe
php artisan tinker
>>> t('app.screen.auth.login.title')

# Verificar idioma específico
>>> t('app.screen.auth.login.title', locale: 'es')

# Verificar en la app
# 1. Cambiar appwide locale en settings o config
# 2. Recargar la pantalla
# 3. Verificar que el texto cambia
```

## Cuando Commitear JSONs

Siempre que agregues traducciones nuevas:

```bash
git add database/translations/en.json
git add database/translations/es.json
git add docs/app-i18n-convention.md  # Si actualizaste guía
git commit -m "i18n: add translations for new feature"
```

El `AppTranslationSeeder.php` NO necesita cambios (solo los JSONs).

---

**Para más detalles**: [`docs/app-i18n-convention.md`](./app-i18n-convention.md)
