# USIM — UI Services Implementation Model

A **Server-Driven UI** framework for Laravel. Define your entire user interface in PHP — screens, menus, forms, tables, modals — and let the framework render, diff, and update everything automatically on the client.

## Table of Contents

- [Requirements](#requirements)
- [What Is New Since 0.5.0](#what-is-new-since-050)
- [Installation](#installation)
    - [Quick Start](#quick-start)
- [Core Concepts](#core-concepts)
  - [Screens](#screens)
    - [Screen Operational Model](#screen-operational-model)
  - [UI — The Component Factory](#uibuilder--the-component-factory)
  - [Event Handlers](#event-handlers)
  - [State Management](#state-management)
- [Available Components](#available-components)
- [Screens in Depth](#screens-in-depth)
  - [Creating a Screen](#creating-a-screen)
  - [Screen Discovery](#screen-discovery)
  - [Authorization](#authorization)
  - [Menu Integration](#menu-integration)
  - [Lifecycle Hooks](#lifecycle-hooks)
- [Event System](#event-system)
  - [Handling Button Actions](#handling-button-actions)
  - [Cross-Service Events](#cross-service-events)
- [Built-in UI Helpers](#built-in-ui-helpers)
- [Database Translations](#database-translations)
- [Modals & Dialogs](#modals--dialogs)
- [Data Tables](#data-tables)
- [File Uploads](#file-uploads)
- [Authentication Scaffolding](#authentication-scaffolding)
- [Testing Screens](#testing-screens)
- [Configuration](#configuration)
- [Headless Mode](#headless-mode)
- [API Endpoints](#api-endpoints)
- [Artisan Commands](#artisan-commands)
- [Octane / RoadRunner Support](#octane--roadrunner-support)
- [Directory Structure](#directory-structure)
- [Release & Upgrade Guide](docs/package-update-and-consumer-upgrade-guide.md)
- [License](#license)

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | 10.x / 11.x / 12.x |
| laravel/sanctum | `^3.0` / `^4.0` |
| spatie/laravel-permission | ^6.0 |
| symfony/finder | `^6.0` / `^7.0` |
| nikic/php-parser | ^5.7 |
| symfony/var-dumper | `^6.0` / `^7.0` |
| illuminate/contracts | `^10.0` / `^11.0` / `^12.0` |

---

## What Is New Since 0.6.0 (v0.7.0)

- Container appearance API: `->card()` and `->plain()` fluent helpers on `Container` to switch between card and flat visual variants.
- Carousel and calendar components consume CSS theme tokens for consistent light/dark styling.
- Bug fix: `Screen` now always persists state after `postLoadUI()`, fixing stale cache on `?reset=true` reloads.
- Bug fix: Checkbox `checked` state now syncs correctly from incremental server responses.

For the full release details, see `CHANGELOG.md`.

---

## Installation

```bash
composer require idei/usim
```

Laravel's package auto-discovery will register `UsimServiceProvider` automatically.

### Quick Start

Run the install command to scaffold a complete working application with authentication, profile, menus, seeders, and routes:

```bash
php artisan usim:install
```

Then follow the printed instructions:

```bash
php artisan migrate
php artisan db:seed --class=UsimSeeder        # creates default admin/user from .env
./start.sh [-r]
```

## Starting the Application

The `./start.sh` script uses **RoadRunner** instead of Laravel's `artisan serve` command. This is necessary because `artisan serve` is single-threaded and does not properly support the framework's concurrent execution requirements.

### Usage

Note: 

Visit `http://localhost:8000` — you have a working USIM app.

> Use `--force` to overwrite existing files.

---

## Core Concepts

### Screens

A **Screen** is a PHP class that defines a full page. Each screen extends `Screen` and builds its UI inside `buildBaseUI()`:

### Screen Operational Model

In USIM, treat a Screen as a **stateful backend UI service**, not as a passive template:

- A Screen owns UI structure, interaction rules, and persisted state.
- `buildBaseUI()` defines the initial component tree.
- `getRoutePath()` derives the canonical URL from namespace/class naming.
- Event actions resolve to `on<ActionName>(array $params)` handlers on the same class.
- Request cycle is: restore state -> run handler -> compute diff -> send only delta.
- Authorization is part of the Screen contract (`authorize`, `checkAccess`).
- Menu metadata also belongs to the Screen contract (`getMenuLabel`, `getMenuIcon`, `getRoutePath`).

This model keeps backend as the source of truth and avoids business-logic duplication across client and server.

Important clarification: USIM does not register one Laravel route per Screen class. Instead, it uses a catch-all web route plus a generic `/api/ui/{screen}` loader and resolves `URL <-> Screen class` through naming convention.

```php
<?php

namespace App\UI\Screens;

use Idei\Usim\UI;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\Components\Container;

class HelloScreen extends Screen
{
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->layout(LayoutType::VERTICAL)
            ->padding(20);

        $container->add(
            UI::label('title')
                ->text('Welcome to the Dashboard')
                ->style('h1')
        );

        $container->add(
            UI::button('hello_btn')
                ->label('Hello USIM!')
                ->primary()
                ->action('hello_button_clicked')
        );
    }

    public function onHelloButtonClicked(array $params): void
    {
        $this->toast('Data refreshed!', 'success');
    }
}
```

After creating the file, register it:

```bash
php artisan usim:discover
```

Then visit `/hello-screen` in your browser.

### UI — The Component Factory

`UI` is a static factory that creates component builders. Every builder uses a **fluent API**:

```php
// Labels
UI::label('greeting')->text('Hello World')->style('h2')->center();
UI::label('legal_copy')->html('legal.terms-snippet');

// Buttons
UI::button('save')->label('Save')->style('primary')->action('save_form');
UI::button('floating_help')
    ->label('Help')
    ->style('secondary')
    ->position('BOTTOM_RIGHT')
    ->offsets(24, 24)
    ->action('open_help');

// Inputs
UI::input('email')->label('Email')->type('email')->required(true)->placeholder('you@example.com');

// Containers (layouts)
$row = UI::container('toolbar')
    ->layout(LayoutType::HORIZONTAL)
    ->gap('10px');

$row->add(UI::button('btn_a')->label('A'));
$row->add(UI::button('btn_b')->label('B'));

$container->add($row);
```

### Event Handlers

When a button fires an action (e.g. `->action('save_form')`), the framework calls a handler method on the same screen class. The convention is **`on` + PascalCase action name**:

| Action string | Handler method |
|---|---|
| `save_form` | `onSaveForm(array $params)` |
| `delete_item` | `onDeleteItem(array $params)` |
| `navigate_home` | `onNavigateHome(array $params)` |

The `$params` array contains all current component values (inputs, selects, checkboxes, etc.) from the client.

### State Management

Screen state is **server-side**. The framework automatically:

1. Builds the UI tree on first load
2. Stores the serialized state
3. On events, restores state → runs your handler → diffs old vs new → sends only the **delta** to the client

Properties prefixed with `store_` are persisted across requests and mirrored on the client as part of the USIM storage payload.

Persistence is now plain by default:

- `store_*` values are serialized as regular JSON values so the client can inspect and use them directly.
- Add the `_crypt` suffix only for sensitive values that must be protected before being sent to client storage.
- This makes client-side decisions possible for non-sensitive state such as `store_theme`, and also enables finer-grained storage synchronization because the client can apply only the changed keys instead of replacing one fully encrypted blob each time.

```php
class MyScreen extends Screen
{
    protected string $store_username = '';       // persisted in plain text
    protected int $store_page = 1;              // persisted in plain text
    protected string $store_theme = 'light';    // readable by the client
    protected string $store_token_crypt = '';   // persisted encrypted
    protected string $tempValue = '';           // NOT persisted
}
```

Use `_crypt` only when the value should not be readable from the client's local storage.

---

## Available Components

| Factory Method | Builder Class | Description |
|---|---|---|
| `UI::label()` | `Label` | Text labels, headings, paragraphs |
| `UI::button()` | `Button` | Action buttons with styles |
| `UI::input()` | `Input` | Text, email, password, hidden inputs |
| `UI::select()` | `Select` | Dropdown selects |
| `UI::checkbox()` | `Checkbox` | Checkboxes and toggles |
| `UI::form()` | `Form` | Form grouping |
| `UI::table()` | `Table` | Data tables with pagination |
| `UI::card()` | `Card` | Cards with title, description, actions |
| `UI::container()` | `Container` | Layout container (vertical/horizontal/grid) with `card()` / `plain()` appearance |
| `UI::menuDropdown()` | `MenuDropdown` | Navigation dropdown menus |
| `UI::uploader()` | `Uploader` | File upload with preview and crop |
| `UI::calendar()` | `Calendar` | Calendar/date picker |
| `UI::carousel()` | `Carousel` | Media carousel for image/audio/video with manual/auto modes |

Builders share a fluent API across `UIComponent` and `Container` with common methods like:

```php
->visible(bool $visible)
->width(string $width)
->padding(mixed $padding)
->margin(mixed $margin)
// ... and many more styling options
```

---

## Screens in Depth

### Creating a Screen

1. Create a class in `app/UI/Screens/` (or a subdirectory):

```php
// app/UI/Screens/Products/List.php
namespace App\UI\Screens\Products;

use Idei\Usim\Screen;
use Idei\Usim\Components\Container;
use Idei\Usim\UI;

class List extends Screen
{
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(
            UI::label('title')->text('Products')->style('h1')
        );

        // Add your table, filters, etc.
    }
}
```

2. Run discovery:

```bash
php artisan usim:discover
```

3. Visit `/products/list` — the URL is automatically derived from the namespace path.

### Screen Discovery

USIM uses Symfony Finder to scan the configured screens directory and generates a manifest cache:

```bash
php artisan usim:discover
# Output: Found 12 screens. USIM manifest generated successfully!
```

The manifest maps URL slugs to screen classes using a CRC32-based offset system for deterministic component IDs.

### Authorization

Override `authorize()` to control access:

```php
// Public screen (default)
public static function authorize(): bool
{
    return true;
}

// Authenticated users only
public static function authorize(): bool
{
    return self::requireAuth();
}

// Guests only (e.g. login screen)
public static function authorize(): bool
{
    return !self::requireAuth();
}

// Role-based
public static function authorize(): bool
{
    return self::requireRole('admin');
}

// Permission-based
public static function authorize(): bool
{
    return self::requirePermission('manage-users');
}
```

When authorization fails, the framework automatically redirects to login (for guests) or shows a 403 (for insufficient permissions).

### Menu Integration

Screens integrate with the navigation menu via static methods:

```php
public static function getMenuLabel(): string
{
    return 'My Screen';
}

public static function getMenuIcon(): ?string
{
    return '📊';
}
```

Then in your Menu screen, use `$menu->screen(MyScreen::class)` for automatic linking with permission checks:

```php
$menu->screen(Dashboard::class);                          // auto label + icon
$menu->screen(Products\List::class, 'All Products', '📦'); // custom label + icon
```

### Lifecycle Hooks

| Method | When |
|---|---|
| `buildBaseUI($container)` | Called on first load to build the initial UI tree |
| `postLoadUI()` | Called after state is restored — update components with live data |
| `onResetService()` | Called when `?reset=true` is passed in the URL |

```php
protected function postLoadUI(): void
{
    // Update components with current data after state restoration
    $user = Auth::user();
    $this->input_name->value($user->name);
    $this->input_email->value($user->email);
}
```

> Component auto-injection: If you declare a typed property with the same name as a component ID, USIM automatically injects the builder instance. For example, `protected Input $input_name;` will be populated with the input created as `UI::input('input_name')`.

---

## Event System

### Handling Button Actions

```php
// In buildBaseUI:
$container->add(
    UI::button('btn_save')
        ->label('Save')
        ->action('save_item')    // → calls onSaveItem()
);

// Handler:
public function onSaveItem(array $params): void
{
    $name = $params['input_name'] ?? '';
    $email = $params['input_email'] ?? '';

    // Save to database...

    $this->toast('Item saved!', 'success');
}
```

### Cross-Service Events

Emit events that ALL active screen services receive using `UsimEvent`:

```php
use Idei\Usim\Events\UsimEvent;

// Emit from anywhere:
event(new UsimEvent('user_logged_in', ['user' => $user]));
```

Any screen with a matching handler will react:

```php
// In Menu screen or any other screen:
public function onUserLoggedIn(array $params): void
{
    $user = $params['user'];
    $this->updateMenuForUser($user);
}
```

---

## Built-in UI Helpers

Available inside any `Screen` handler:

```php
// Show a toast notification
$this->toast('Operation successful', 'success');  // types: success, error, info, warning

// Navigate to another URL
$this->redirect('/products');
$this->redirect();           // reload current screen

// Close the currently open modal
$this->closeModal();

// Update modal fields
$this->updateModal([
    'field_name' => ['error' => 'This field is required']
]);

// Show an error page
$this->abort(404, 'Not found');

// Switch theme on the client
$this->changeTheme('dark');
```

Labels can now render raw HTML or an existing Blade view:

```php
UI::label('welcome_copy')
    ->html('emails.verify-email', ['user' => $user]);
```

Most components also support anchor-based positioning helpers for floating or pinned UI:

```php
UI::container('floating_panel')
    ->position('TOP_RIGHT')
    ->positionMode('fixed')
    ->offsets(16, 16);
```

---

## Database Translations

USIM now supports package-level database translations with key-based identifiers and language fallback.

Published migration stubs create these tables:

- `usim_languages`
- `usim_text_keys`
- `usim_text_values`

The `TranslationService` manages CRUD for languages, keys, and values (including optional media fields).

Global utility helper:

```php
t('app.welcome', ['name' => 'Emilio']);
```

Resolution order:

1. current locale in DB
2. fallback locale in DB (`en` by default)
3. key literal

Translation values support placeholders (`:name`, `:count`, etc.) and optional media metadata (`media_url`, `media_meta`).

Auto-key behavior for human-readable text (`t('Some text')`):

- key length limit is configurable via `ui-services.i18n.auto_key_max_length` (env: `USIM_I18N_AUTO_KEY_MAX_LENGTH`, default `20`)
- when truncation is needed, USIM tries to continue to the next separator so the current word is not cut mid-word
- escaped and real line breaks are normalized before key generation and fallback text storage

I18n suggestion logging:

- when a key is auto-generated from human-readable text, USIM emits an i18n warning suggesting to replace the literal text with the generated key
- log context includes generated key, source text, group, file, line, and best-effort character position
- configure channel with `ui-services.i18n.log_channel` (env: `USIM_I18N_LOG_CHANNEL`, default `i18n`)
- enable/disable with `ui-services.i18n.log_autokey_suggestions` (env: `USIM_I18N_LOG_AUTOKEY_SUGGESTIONS`, default `true`)

Recommended key naming for package and scaffolded code:

- `usim.component.*` for reusable package component defaults.
- `usim.dialog.*` and `usim.time_unit.*` for framework-level dialog and timer labels.
- `usim.common.*` for shared scaffold labels/placeholders.
- `usim.auth.*`, `usim.admin.*`, `usim.menu.*` for generated screen/component UI text.
- `usim.service.*` for scaffolded service response and validation messages.

When adding or changing scaffold text, prefer `t('...')` with one of the namespaces above and include the key in `UsimTranslationSeeder`.

---

## Modals & Dialogs

### Quick Confirmation Dialogs

Use `ConfirmDialogService` for standard dialogs:

```php
use Idei\Usim\Modals\ConfirmDialogService;
use Idei\Usim\Enums\DialogType;

public function onDeleteItem(array $params): void
{
    ConfirmDialogService::open(
        type: DialogType::CONFIRM,
        title: 'Delete Item',
        message: 'Are you sure you want to delete this item?',
        confirmAction: 'confirm_delete',
        cancelAction: 'cancel_delete',
        callerServiceId: $this->getServiceComponentId()
    );
}

public function onConfirmDelete(array $params): void
{
    // Perform the delete...
    $this->closeModal();
    $this->toast('Item deleted', 'success');
}
```

Dialog types: `INFO`, `CONFIRM`, `WARNING`, `ERROR`, `SUCCESS`, `CHOICE`, `TIMEOUT`.

### Custom Modals

Build custom modal content using any component and set `->parent('modal')`:

```php
$modal = UI::container('my_modal')
    ->parent('modal')
    ->padding('20px');

$modal->add(UI::input('field_a')->label('Name'));
$modal->add(
    UI::button('btn_submit')
        ->label('Submit')
        ->action('submit_modal')
);
```

---

## Data Tables

For paginated server-side data tables, extend `AbstractDataTableModel`:

```php
use Idei\Usim\DataTable\AbstractDataTableModel;

class ProductsTable extends AbstractDataTableModel
{
    public function getColumns(): array
    {
        return [
            ['name' => 'id', 'type' => 'int'],
            ['name' => 'name', 'type' => 'string'],
            ['name' => 'price', 'type' => 'float'],
        ];
    }

    public function getFormattedPageData(int $currentPage, int $perPage): array
    {
        return Product::paginate($perPage, ['*'], 'page', $currentPage)
            ->items();
    }
}
```

Then use it in your screen:

```php
$table = UI::table('products_table');
$dataModel = new ProductsTable($table);
// configure columns, pagination, etc.
$container->add($table);
```

---

## File Uploads

Use the `Uploader` for file uploads with temporary storage, preview, and image cropping:

```php
$uploader = UI::uploader('avatar')
    ->label('Profile Photo')
    ->allowedTypes(['image/*'])
    ->maxFiles(1)
    ->maxSize(2)       // MB
    ->aspect('1:1')    // crop ratio
    ->size(1);         // display size

$container->add($uploader);
```

In your event handler, confirm the upload to move it from temporary to permanent storage:

```php
public function onSaveProfile(array $params): void
{
    if ($filename = $this->uploader_avatar->confirm($params, 'images', $oldFilename)) {
        $user->avatar = $filename;
        $user->save();
    }
}
```

> Temporary uploads are automatically cleaned up hourly via a scheduled job.

---

## Authentication Scaffolding

`php artisan usim:install` provides a complete authentication and admin system:

| Screen | Path | Description |
|---|---|---|
| `Login` | `/auth/login` | Email/password login with Sanctum tokens |
| `ForgotPassword` | `/auth/forgot-password` | Send password reset link via email |
| `ResetPassword` | `/auth/reset-password` | Reset password form |
| `EmailVerified` | `/auth/email-verified` | Email verification handler |
| `Profile` | `/auth/profile` | User profile (name, photo, password change) |
| `Admin\Dashboard` | `/admin/dashboard` | User management table with CRUD and role assignment (admin only) |

Supporting files:

- **AuthController** — API endpoints for register, login, logout, verify email, reset password
- **UsimUser trait** — Custom notification methods for password reset and email verification
- **UserService** — Full user management: find, get, create, update (with role sync, email validation, notifications)
- **UsimSeeder / UsimRoleSeeder / UsimUserSeeder** — Default roles (admin/user/verified) and seed users from `.env`
- **EventServiceProvider** — App-level event/listener registration scaffold
- **Email view stubs** — Styled Blade views for password reset and email verification emails
- **Terms view** — Blade view for terms and conditions display
- **Migrations** — `temporary_uploads`, `profile_image` column, and `terms_accepted_at` column on users table

### Default Users (via `.env`)

After install, configure your `.env`:

```env
ADMIN_FIRST_NAME=Admin
ADMIN_LAST_NAME=User
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=your-secure-password

USER_FIRST_NAME=Regular
USER_LAST_NAME=User
USER_EMAIL=user@example.com
USER_PASSWORD=your-secure-password
```

Then run `php artisan db:seed`.

---

## Testing Screens

This package ships a self-contained testing guide under `docs/`:

- `docs/SCREEN_TESTING_GUIDE.md` — Human-oriented guide with patterns, helpers, and examples.
- `docs/tests_prompt.md` — Copy/paste prompt template to ask any agent/chat to generate new screen tests.

Recommended workflow:

1. Read the guide to follow the project conventions (`uiScenario`, component-level assertions, response contracts).
2. Use `docs/tests_prompt.md` as a base when delegating test generation to an AI agent.
3. Validate locally with `php artisan test` (or file-level execution first).

Core approach used across this project:

```php
$ui = uiScenario($this, SomeScreen::class, ['reset' => true]);

$ui->component('btn_submit')->expect('action')->toBe('submit_form');

$response = $ui->click('btn_submit', ['field' => 'value']);
$response->assertOk();
expect($response->json('toast.type'))->toBe('success');

$ui->assertNoIssues();
```

---

## Configuration

Publish the config file (done automatically by `usim:install`):

```bash
php artisan vendor:publish --tag=usim-config
```

This creates `config/ui-services.php`:

```php
return [
    'app_id'           => env('APP_ID', 'my-app'),
    'screens_namespace' => 'App\\UI\\Screens',
    'screens_path'      => app_path('UI/Screens'),
    'api_url'           => env('API_BASE_URL', env('APP_URL')),
    'upload_disk'       => env('UPLOAD_DISK', 'local'),
    'i18n'              => [
        'default_locale'  => env('USIM_DEFAULT_LOCALE', env('APP_LOCALE', 'en')),
        'fallback_locale' => env('USIM_FALLBACK_LOCALE', 'en'),
    ],
];
```

| Key | Description | Default |
|---|---|---|
| `app_id` | Unique application identifier used to scope persisted UI storage keys | `my-app` (override via `APP_ID`) |
| `screens_namespace` | PSR-4 namespace where screens live | `App\UI\Screens` |
| `screens_path` | Filesystem path to scan for screens | `app/UI/Screens` |
| `api_url` | Base URL for internal HTTP calls | `APP_URL` |
| `upload_disk` | Laravel filesystem disk for uploaded files | `local` (override via `UPLOAD_DISK`) |
| `i18n.default_locale` | Preferred locale for DB translation lookup | `APP_LOCALE` or `en` |
| `i18n.fallback_locale` | Fallback locale for DB translations | `en` |
---

## Headless Mode

USIM can run in **headless mode**, serving API endpoints only without requiring a web renderer.

### Configuration

Set the environment variable:

```bash
USIM_HEADLESS_MODE=true
```

### Behavior

- When `USIM_HEADLESS_MODE=true`: Requests to the web catch-all route return `406 Not Acceptable` with JSON error
- All clients must consume `/api/ui/{screen}` and `/api/ui-event` endpoints directly
- API endpoints function identically in both modes
- Ideal for backend-driven applications, AI agents, mobile apps, or multi-client architectures

### Example (Node.js/JavaScript Client)

```javascript
// Load a screen
const screenResponse = await fetch('/api/ui/login');
const screen = await screenResponse.json();

console.log(screen);
// {
//   "10": { "_id": 10, "type": "container", "parent": "root", ... },
//   "11": { "_id": 11, "type": "input", "parent": 10, ... },
//   "agent_context": {
//     "purpose": "User login with email/password",
//     "inputs": ["email", "password"],
//     "outputs": ["redirect", "toast", "abort"]
//   }
// }

// Extract agent context (if present)
if (screen.agent_context) {
    console.log('Screen purpose:', screen.agent_context.purpose);
}
```

### Example (PHP Client)

```php
// Use Laravel HTTP client or GuzzleHttp to consume API directly
$response = Http::get(config('ui-services.api_url') . '/api/ui/admin/dashboard');
$screen = $response->json();

if (isset($screen['agent_context'])) {
    // Agent access to metadata
    $context = $screen['agent_context'];
}
```

---

---

## API Endpoints

USIM registers these routes automatically:

| Method | URI | Description |
|---|---|---|
| `GET` | `/api/ui/{screen}` | Load a screen (returns JSON UI tree) |
| `POST` | `/api/ui-event` | Handle an event (returns JSON diff) |
| `POST` | `/api/upload/temporary` | Upload a file to temporary storage |
| `DELETE` | `/api/upload/temporary/{id}` | Remove a temporary upload |
| `GET` | `/files/{path}` | Serve uploaded files |

The client-side JavaScript (`ui-renderer.js`) handles these calls automatically.

---

## Artisan Commands

| Command | Description |
|---|---|
| `php artisan usim:discover` | Scan screens directory and generate manifest cache |
| `php artisan usim:install` | Scaffold a complete USIM application (screens, auth, admin, tests) |
| `php artisan usim:install --force` | Overwrite existing files |

---

## Octane / RoadRunner Support

USIM is compatible with long-running processes. The service provider automatically resets the component ID generator on each request when `laravel/octane` is detected:

```php
// Automatic — no configuration needed
$events->listen(\Laravel\Octane\Events\RequestReceived::class, function () {
    UIIdGenerator::reset();
});
```

The `UIChangesCollector` is registered as a **scoped** singleton, ensuring clean state per request.

---

## Directory Structure

After `usim:install`, your application will have:

```
app/
├── Http/Controllers/Api/
│   └── AuthController.php        # Auth API endpoints
├── Models/
│   └── User.php                  # With UsimUser, HasRoles, HasApiTokens traits
├── Services/
│   ├── Auth/
│   │   ├── AuthSessionService.php
│   │   ├── LoginService.php
│   │   ├── PasswordService.php
│   │   └── RegisterService.php
│   └── User/
│       └── UserService.php       # Full CRUD, role management, email notifications
└── UI/
    ├── Components/
    │   ├── DataTable/
    │   │   └── UserApiTableModel.php  # Paginated user table
    │   └── Modals/
    │       ├── EditUserDialog.php
    │       ├── LoginDialog.php
    │       └── RegisterDialog.php
    └── Screens/
        ├── Home.php              # Landing page
        ├── Menu.php              # Navigation menu (links Dashboard for admins)
        ├── Admin/
        │   └── Dashboard.php     # User management (admin only)
        └── Auth/
            ├── Login.php
            ├── ForgotPassword.php
            ├── ResetPassword.php
            ├── EmailVerified.php
            └── Profile.php
config/
├── ui-services.php               # USIM configuration
└── users.php                     # Default users for seeding
database/
├── migrations/
│   ├── *_create_temporary_uploads_table.php
│   ├── *_add_profile_image_to_users_table.php
│   └── *_add_terms_accepted_at_to_users_table.php
└── seeders/
    ├── UsimSeeder.php           # Entry point — calls role and user seeders
    ├── UsimRoleSeeder.php
    └── UsimUserSeeder.php
providers/
└── EventServiceProvider.php
resources/views/emails/
├── reset-password.blade.php
└── verify-email.blade.php
resources/views/
└── terms.blade.php
routes/
├── api-auth.php                  # Auth API routes
└── web.php                       # + catch-all route for screens
```

The package itself lives in `vendor/idei/usim/` (or `packages/idei/usim/` during development).

---

## License

MIT
