# USIM — UI Services Implementation Model

A **Server-Driven UI** framework for Laravel. Define your entire user interface in PHP — screens, menus, forms, tables, modals — and let the framework render, diff, and update everything automatically on the client.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
  - [Quick Start (Full Preset)](#quick-start-full-preset)
  - [Minimal Preset](#minimal-preset)
- [Core Concepts](#core-concepts)
  - [Screens](#screens)
  - [UIBuilder — The Component Factory](#uibuilder--the-component-factory)
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
- [Modals & Dialogs](#modals--dialogs)
- [Data Tables](#data-tables)
- [File Uploads](#file-uploads)
- [Authentication Scaffolding](#authentication-scaffolding)
- [Configuration](#configuration)
- [API Endpoints](#api-endpoints)
- [Artisan Commands](#artisan-commands)
- [Octane / RoadRunner Support](#octane--roadrunner-support)
- [Directory Structure](#directory-structure)
- [License](#license)

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | ^8.2 |
| Laravel | 10.x / 11.x / 12.x |
| laravel/sanctum | ^3.0 \| ^4.0 |
| spatie/laravel-permission | ^6.0 |
| symfony/finder | ^6.0 \| ^7.0 |

---

## Installation

```bash
composer require idei/usim
```

Laravel's package auto-discovery will register `UsimServiceProvider` automatically.

### Quick Start (Full Preset)

Run the install command and choose the **full** preset to scaffold a complete working application with authentication, profile, menus, seeders, and routes:

```bash
php artisan usim:install --preset=full
```

Then follow the printed instructions:

```bash
php artisan migrate
php artisan db:seed          # creates default admin/user from .env
php artisan serve
```

Visit `http://localhost:8000` — you have a working USIM app.

### Minimal Preset

If you only want a Home screen and a simple navigation menu (no auth):

```bash
php artisan usim:install --preset=minimal
```

> Use `--force` to overwrite existing files.

---

## Core Concepts

### Screens

A **Screen** is a PHP class that defines a full page. Each screen extends `AbstractUIService` and builds its UI inside `buildBaseUI()`:

```php
<?php

namespace App\UI\Screens;

use Idei\Usim\Services\UIBuilder;
use Idei\Usim\Services\Enums\LayoutType;
use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\UIContainer;

class Dashboard extends AbstractUIService
{
    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->layout(LayoutType::VERTICAL)
            ->padding(20);

        $container->add(
            UIBuilder::label('title')
                ->text('Welcome to the Dashboard')
                ->style('h1')
        );

        $container->add(
            UIBuilder::button('refresh')
                ->label('Refresh Data')
                ->style('primary')
                ->action('refresh_data')
        );
    }

    public function onRefreshData(array $params): void
    {
        $this->toast('Data refreshed!', 'success');
    }
}
```

After creating the file, register it:

```bash
php artisan usim:discover
```

Then visit `/dashboard` in your browser.

### UIBuilder — The Component Factory

`UIBuilder` is a static factory that creates component builders. Every builder uses a **fluent API**:

```php
// Labels
UIBuilder::label('greeting')->text('Hello World')->style('h2')->center();

// Buttons
UIBuilder::button('save')->label('Save')->style('primary')->action('save_form');

// Inputs
UIBuilder::input('email')->label('Email')->type('email')->required(true)->placeholder('you@example.com');

// Containers (layouts)
$row = UIBuilder::container('toolbar')
    ->layout(LayoutType::HORIZONTAL)
    ->gap('10px');

$row->add(UIBuilder::button('btn_a')->label('A'));
$row->add(UIBuilder::button('btn_b')->label('B'));

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

Properties prefixed with `store_` are persisted across requests:

```php
class MyScreen extends AbstractUIService
{
    protected string $store_username = '';   // persisted
    protected int $store_page = 1;          // persisted
    protected string $tempValue = '';       // NOT persisted
}
```

---

## Available Components

| Factory Method | Builder Class | Description |
|---|---|---|
| `UIBuilder::label()` | `LabelBuilder` | Text labels, headings, paragraphs |
| `UIBuilder::button()` | `ButtonBuilder` | Action buttons with styles |
| `UIBuilder::input()` | `InputBuilder` | Text, email, password, hidden inputs |
| `UIBuilder::select()` | `SelectBuilder` | Dropdown selects |
| `UIBuilder::checkbox()` | `CheckboxBuilder` | Checkboxes and toggles |
| `UIBuilder::form()` | `FormBuilder` | Form grouping |
| `UIBuilder::table()` | `TableBuilder` | Data tables with pagination |
| `UIBuilder::card()` | `CardBuilder` | Cards with title, description, actions |
| `UIBuilder::container()` | `UIContainer` | Layout container (vertical/horizontal) |
| `UIBuilder::menuDropdown()` | `MenuDropdownBuilder` | Navigation dropdown menus |
| `UIBuilder::uploader()` | `UploaderBuilder` | File upload with preview and crop |
| `UIBuilder::calendar()` | `CalendarBuilder` | Calendar/date picker |

All builders extend `BaseUIBuilder` and share common methods:

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

use Idei\Usim\Services\AbstractUIService;
use Idei\Usim\Services\Components\UIContainer;
use Idei\Usim\Services\UIBuilder;

class List extends AbstractUIService
{
    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container->add(
            UIBuilder::label('title')->text('Products')->style('h1')
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

> Component auto-injection: If you declare a typed property with the same name as a component ID, USIM automatically injects the builder instance. For example, `protected InputBuilder $input_name;` will be populated with the input created as `UIBuilder::input('input_name')`.

---

## Event System

### Handling Button Actions

```php
// In buildBaseUI:
$container->add(
    UIBuilder::button('btn_save')
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

Available inside any `AbstractUIService` handler:

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
```

---

## Modals & Dialogs

### Quick Confirmation Dialogs

Use `ConfirmDialogService` for standard dialogs:

```php
use Idei\Usim\Services\Modals\ConfirmDialogService;
use Idei\Usim\Services\Enums\DialogType;

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
$modal = UIBuilder::container('my_modal')
    ->parent('modal')
    ->padding('20px');

$modal->add(UIBuilder::input('field_a')->label('Name'));
$modal->add(
    UIBuilder::button('btn_submit')
        ->label('Submit')
        ->action('submit_modal')
);
```

---

## Data Tables

For paginated server-side data tables, extend `AbstractDataTableModel`:

```php
use Idei\Usim\Services\DataTable\AbstractDataTableModel;

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
$table = UIBuilder::table('products_table');
$dataModel = new ProductsTable($table);
// configure columns, pagination, etc.
$container->add($table);
```

---

## File Uploads

Use the `UploaderBuilder` for file uploads with temporary storage, preview, and image cropping:

```php
$uploader = UIBuilder::uploader('avatar')
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

The **full** preset (`usim:install --preset=full`) provides a complete authentication system:

| Screen | Path | Description |
|---|---|---|
| `Login` | `/auth/login` | Email/password login with Sanctum tokens |
| `ForgotPassword` | `/auth/forgot-password` | Send password reset link via email |
| `ResetPassword` | `/auth/reset-password` | Reset password form |
| `EmailVerified` | `/auth/email-verified` | Email verification handler |
| `Profile` | `/auth/profile` | User profile (name, photo, password change) |

Supporting files:

- **AuthController** — API endpoints for register, login, logout, verify email, reset password
- **UsimUser trait** — Custom notification methods for password reset and email verification
- **RoleSeeder / UsimUserSeeder** — Default roles (admin/user/verified) and seed users from `.env`
- **Migrations** — `temporary_uploads`, `profile_image` column on users table

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

## Configuration

Publish the config file (done automatically by `usim:install`):

```bash
php artisan vendor:publish --tag=usim-config
```

This creates `config/ui-services.php`:

```php
return [
    'screens_namespace' => 'App\\UI\\Screens',
    'screens_path'      => app_path('UI/Screens'),
    'api_url'           => env('API_BASE_URL', env('APP_URL')),
];
```

| Key | Description | Default |
|---|---|---|
| `screens_namespace` | PSR-4 namespace where screens live | `App\UI\Screens` |
| `screens_path` | Filesystem path to scan for screens | `app/UI/Screens` |
| `api_url` | Base URL for internal HTTP calls | `APP_URL` |

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
| `php artisan usim:install` | Scaffold a new USIM application |
| `php artisan usim:install --preset=minimal` | Scaffold with only Home + Menu |
| `php artisan usim:install --preset=full` | Scaffold with full auth system |
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

After `usim:install --preset=full`, your application will have:

```
app/
├── Http/Controllers/Api/
│   └── AuthController.php        # Auth API endpoints
├── Models/
│   └── User.php                  # With UsimUser, HasRoles, HasApiTokens traits
└── UI/
    ├── Components/
    │   └── Modals/
    │       ├── LoginDialogService.php
    │       └── RegisterDialogService.php
    └── Screens/
        ├── Home.php              # Landing page
        ├── Menu.php              # Navigation menu
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
│   └── *_add_profile_image_to_users_table.php
└── seeders/
    ├── RoleSeeder.php
    └── UsimUserSeeder.php
routes/
├── api-auth.php                  # Auth API routes
└── web.php                       # + catch-all route for screens
```

The package itself lives in `vendor/idei/usim/` (or `packages/idei/usim/` during development).

---

## License

MIT
