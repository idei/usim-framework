# 🚀 Tutorial: Crear un Gestor de Posts con Laravel y USIM Framework

En este tutorial aprenderás a construir desde cero una aplicación **Gestor de Posts** 100% **SPA (Single Page Application)** utilizando **Laravel** y **USIM Framework** (Server-Driven UI en PHP).

---

## 🎯 ¿Qué vamos a construir?

Construiremos una aplicación didáctica pensada para enseñar los conceptos esenciales de USIM a un desarrollador Laravel:

1. **Una única Unidad Organizacional (`lobby`)**: Simplificamos la estructura multi-unidad dejando únicamente `lobby` en `config/usim.php`.
2. **Cuatro estados/roles de usuario con comportamientos bien diferenciados**:
   - **`guest` (Invitado / No autenticado)**: Ve la pantalla `Home` con los últimos posts publicados y botones para iniciar sesión o registrarse mediante modales SPA.
   - **`registered` (Recién registrado)**: Ve **únicamente** la pantalla `Registered` con el mensaje *"Se ha registrado en el sistema, en breve se le asignará un rol"*, desde donde puede editar su perfil y cambiar su **avatar** (usando `UI::uploader()` y `UI::image()`).
   - **`publisher` (Publicador)**: Ve los últimos posts en `Home` y puede acceder a la pantalla `PublishPost` para publicar nuevos posts con imagen de portada (`UI::uploader()`).
   - **`admin` (Administrador)**: Además de publicar, puede **eliminar posts** directamente desde `Home` y acceder al **Gestor de Usuarios (`UsersManager`)** para aprobar a los usuarios `registered` asignándoles el rol `publisher` o `admin`.
3. **Componentes Multimedia**: Uso práctico de `UI::uploader()` para subir imágenes temporales y confirmarlas, y `UI::image()` para renderizar portadas de posts y avatares.
4. **Navegación 100% SPA**: Toda transición entre pantallas y apertura de formularios ocurre mediante `$this->showInto(...)`, `$this->openModal(...)` y `MenuBuilder::screenShow(...)`, **sin utilizar `$this->redirect()` ni recargas de página**.
5. **Idioma único (Español)**: Configurado directamente en español (`es`) sin complejidad multi-idioma.

---

## 🛠️ Paso 1: Creación del Proyecto e Instalación de USIM

### 1.1 Crear el proyecto Laravel e instalar el paquete

```bash
composer create-project laravel/laravel gestor-posts
cd gestor-posts

# Instalar el paquete USIM
composer require idei/usim

# Ejecutar el instalador interactivo/automático de USIM
php artisan usim:install --force --no-interaction
```

### 1.2 Configurar el idioma Español en `.env`

Edita tu archivo `.env` para fijar el idioma de la aplicación en español (`es`):

```env
APP_NAME="USIM Gestor de Posts"
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
```

---

## ⚙️ Paso 2: Configurar `config/usim.php` (Unidad `lobby` y Roles)

Reemplaza el contenido de `config/usim.php` para definir:
- **Una sola unidad**: `lobby`.
- **Roles del sistema**: `root`, `admin`, `publisher` y `registered` (el rol por defecto al registrarse).
- **Pantallas de inicio (`home_screen`) por rol**:
  - `registered` $\rightarrow$ `\App\UI\Screens\Registered::class`
  - `publisher`, `admin`, `root` $\rightarrow$ `\App\UI\Screens\Home::class`
- **Idioma español (`es`)** como único idioma activo.

 Edita `config/usim.php`:

```php
<?php

return [
    'app_name' => env('USIM_APP_NAME', 'USIM Gestor de Posts'),
    'api_prefix' => env('USIM_API_PREFIX', 'api'),

    'storage_disk' => env('USIM_STORAGE_DISK', 'public'),
    'upload' => [
        'temp_ttl_hours' => env('USIM_UPLOAD_TEMP_TTL_HOURS', 1),
    ],
    'headless_mode' => env('USIM_HEADLESS_MODE', false),
    'default_registering_role' => env('USIM_DEFAULT_REGISTERING_ROLE', 'registered'),

    /*
    |--------------------------------------------------------------------------
    | Unidades Organizacionales (Sólo "lobby")
    |--------------------------------------------------------------------------
    */
    'units' => [
        'structure' => [
            'lobby' => [
                'description' => 'Lobby principal de la aplicación',
                'default_translations' => [
                    'es' => 'Lobby',
                ],
                'children' => [],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Usuarios de Semilla (Seed Users)
    |--------------------------------------------------------------------------
    */
    'users' => [
        'root' => [
            'name' => env('USIM_ROOT_USER_NAME', 'Root User'),
            'email' => env('USIM_ROOT_USER_EMAIL', 'root@example.com'),
            'password' => env('USIM_ROOT_USER_PASSWORD', 'password'),
            'units' => ['lobby'],
        ],
        'admin' => [
            'name' => 'Administrador',
            'email' => 'admin@example.com',
            'password' => 'password',
            'units' => ['lobby'],
        ],
        'publisher' => [
            'name' => 'Publicador',
            'email' => 'publisher@example.com',
            'password' => 'password',
            'units' => ['lobby'],
        ],
        'registered' => [
            'name' => 'Usuario Registrado',
            'email' => 'registered@example.com',
            'password' => 'password',
            'units' => ['lobby'],
        ],
    ],

    'devices' => [],

    /*
    |--------------------------------------------------------------------------
    | Roles y Permisos
    |--------------------------------------------------------------------------
    */
    'roles' => [
        'root' => [
            'priority' => 100,
            'home_screen' => \App\UI\Screens\Home::class,
            'permissions' => [
                'system.configure' => [
                    'es' => 'Configurar parámetros del sistema',
                ],
                'manage.roles' => [
                    'es' => 'Gestionar roles de usuario',
                ],
                'manage.permissions' => [
                    'es' => 'Gestionar permisos de usuario',
                ],
                'manage.translations' => [
                    'es' => 'Gestionar traducciones',
                ],
                'manage.devices' => [
                    'es' => 'Gestionar dispositivos',
                ],
                'admin.users_manager.access' => [
                    'es' => 'Acceder a la pantalla de gestión de usuarios',
                ],
                'publish_post.access' => [
                    'es' => 'Publicar nuevos posts',
                ],
                'posts.delete' => [
                    'es' => 'Eliminar posts publicados',
                ],
            ],
            'default_translations' => [
                'es' => [
                    'display_name' => 'Superusuario (Root)',
                    'description' => 'Superusuario con acceso completo al sistema',
                ],
            ],
        ],
        'admin' => [
            'priority' => 90,
            'home_screen' => \App\UI\Screens\Home::class,
            'permissions' => [
                'admin.users_manager.access' => [
                    'es' => 'Acceder a la pantalla de gestión de usuarios',
                ],
                'manage.roles' => [
                    'es' => 'Asignar roles de admin o publisher a usuarios registrados',
                ],
                'publish_post.access' => [
                    'es' => 'Publicar nuevos posts',
                ],
                'posts.delete' => [
                    'es' => 'Eliminar posts publicados',
                ],
            ],
            'default_translations' => [
                'es' => [
                    'display_name' => 'Administrador',
                    'description' => 'Puede eliminar posts y asignar roles (admin o publisher) a usuarios registrados',
                ],
            ],
        ],
        'publisher' => [
            'priority' => 50,
            'home_screen' => \App\UI\Screens\Home::class,
            'permissions' => [
                'publish_post.access' => [
                    'es' => 'Publicar nuevos posts',
                ],
            ],
            'default_translations' => [
                'es' => [
                    'display_name' => 'Publicador',
                    'description' => 'Puede crear y publicar posts con imágenes',
                ],
            ],
        ],
        'registered' => [
            'priority' => 10,
            'home_screen' => \App\UI\Screens\Registered::class,
            'permissions' => [
                'profile.view' => [
                    'es' => 'Ver perfil propio',
                ],
                'profile.edit' => [
                    'es' => 'Editar perfil propio',
                ],
            ],
            'default_translations' => [
                'es' => [
                    'display_name' => 'Registrado',
                    'description' => 'Usuario recién registrado en espera de asignación de rol',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Internacionalización (Sólo Español)
    |--------------------------------------------------------------------------
    */
    'i18n' => [
        'default_locale' => 'es',
        'fallback_locale' => 'es',
        'languages' => [
            'es' => [
                'name' => 'Spanish',
                'native_name' => 'Español',
                'active' => true,
            ],
            'en' => [
                'name' => 'English',
                'native_name' => 'English',
                'active' => false,
            ],
        ],
    ],
];
```

---

## 🧩 Paso 3: Adaptar el Contexto de Unidad Única (`lobby`)

Por defecto, los servicios generados por `usim:install` asumen que los usuarios recién registrados están en `lobby` y al ser aprobados pasan a una unidad llamada `main`. Como nuestra aplicación utiliza **únicamente la unidad `lobby`**, haremos tres pequeños ajustes para que la aprobación de usuarios y asignación de roles funcione dentro de `lobby`:

### 3.1 `app/Services/User/UserService.php`

En `app/Services/User/UserService.php`, asegúrate de que el estado "en espera" se determine mediante `$user->isRegisteredInLobby()` (que comprueba si su único rol en `lobby` es `registered`) y que al aprobarse permanezca en `lobby`:

1. En `getPaginatedWithLobbyStatus()`, reemplaza la transformación de `is_in_lobby`:
```php
$paginator->getCollection()->transform(function (User $user) {
    $user->setAttribute('is_in_lobby', $user->isRegisteredInLobby());

    return $user;
});
```

2. En `findWithLobbyStatus()`, usa también `$user->isRegisteredInLobby()`:
```php
if ($user) {
    $user->setAttribute('is_in_lobby', $user->isRegisteredInLobby());
}
```

3. En `approveLobbyRegistration()`, si no existe `main`, usa `lobby` como unidad destino y evita desasociar `lobby` cuando la unidad destino es la misma:
```php
$mainUnit = UsimUnit::where('slug', $targetUnitSlug)->first()
    ?? UsimUnit::where('slug', 'lobby')->first();
$lobbyUnit = UsimUnit::where('slug', 'lobby')->first();

// ...

if ($lobbyUnit && $lobbyUnit->id !== $mainUnit->id) {
    $user->usimUnits()->detach($lobbyUnit->id);
}
```

### 3.2 `app/UI/Screens/Admin/TableModels/UserTableModel.php`

En `app/UI/Screens/Admin/TableModels/UserTableModel.php`, dentro de `formatRow()`, cambia la detección de lobby para usar `$user->isRegisteredInLobby()`:

```php
$isInLobby = (bool) ($user->getAttribute('is_in_lobby') ?? $user->isRegisteredInLobby());
```

### 3.3 `app/UI/Screens/Admin/EditUser.php`

En `app/UI/Screens/Admin/EditUser.php`, define `'publisher'` como rol sugerido por defecto cuando el administrador aprueba a un usuario registrado:

```php
private const DEFAULT_FALLBACK_ROLE = 'publisher';
```

*(Opcionalmente, en `app/Services/Auth/RegisterService.php`, `app/Services/Units/UnitContextResolver.php`, `app/Services/Auth/AuthSessionService.php` y `app/UI/Screens/Admin/Concerns/ResolvesActiveUnitContext.php`, puedes usar `'lobby'` como fallback si `'main'` no existe).*

---

## 🗄️ Paso 4: Migración y Capa de Servicios para Posts (SOLID)

### 4.1 Crear la migración de la tabla `posts`

Crea el archivo `database/migrations/2026_10_16_030000_create_posts_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->text('content');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

### 4.2 Crear el Contrato `app/Contracts/PostServiceInterface.php`

Siguiendo el principio de Inversión de Dependencias (SOLID), nuestras Screens dependerán de una interfaz y no de consultas SQL directas:

```php
<?php

namespace App\Contracts;

use Illuminate\Support\Collection;

interface PostServiceInterface
{
    /**
     * Obtiene los últimos posts ordenados por fecha descendente.
     */
    public function getLatestPosts(): Collection;

    /**
     * Busca un post por su ID.
     */
    public function findById(int $id): ?object;

    /**
     * Crea un nuevo post y devuelve su ID.
     *
     * @param array{user_id: int, title: string, content: string, image: ?string} $data
     */
    public function createPost(array $data): int;

    /**
     * Elimina un post por su ID.
     */
    public function deletePost(int $id): bool;
}
```

### 4.3 Crear la Implementación `app/Services/Post/DatabasePostService.php`

```php
<?php

namespace App\Services\Post;

use App\Contracts\PostServiceInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DatabasePostService implements PostServiceInterface
{
    public function getLatestPosts(): Collection
    {
        return DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select('posts.*', 'users.name as author_name')
            ->orderByDesc('posts.created_at')
            ->orderByDesc('posts.id')
            ->get();
    }

    public function findById(int $id): ?object
    {
        return DB::table('posts')
            ->join('users', 'posts.user_id', '=', 'users.id')
            ->select('posts.*', 'users.name as author_name')
            ->where('posts.id', $id)
            ->first();
    }

    public function createPost(array $data): int
    {
        return DB::table('posts')->insertGetId([
            'user_id' => $data['user_id'],
            'title' => $data['title'],
            'content' => $data['content'],
            'image' => $data['image'] ?? null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function deletePost(int $id): bool
    {
        return DB::table('posts')->where('id', $id)->delete() > 0;
    }
}
```

### 4.4 Registrar el Servicio en `app/Providers/AppServiceProvider.php`

```php
<?php

namespace App\Providers;

use App\Contracts\PostServiceInterface;
use App\Services\Post\DatabasePostService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PostServiceInterface::class, DatabasePostService::class);
    }

    public function boot(): void
    {
        //
    }
}
```

---

## 🖥️ Paso 5: Crear y Modificar las Pantallas (Screens) en USIM

### 5.1 Pantalla de Espera para Usuarios Registrados (`app/UI/Screens/Registered.php`)

Cuando un usuario se registra en el sistema, obtiene el rol `registered`. Esta pantalla le informa que en breve un administrador le asignará un rol (`publisher` o `admin`), muestra su avatar actual con `UI::image()` y le permite abrir el modal `Profile` para cambiar su nombre o subir su avatar con `UI::uploader()`.

Edita `app/UI/Screens/Registered.php`:

```php
<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens;

use App\Models\User;
use App\UI\Screens\Auth\Profile;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Image;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class Registered extends Screen
{
    protected Image $registered_avatar;
    protected Label $registered_greeting;

    public static function authorize(): bool
    {
        return self::requireAuth();
    }

    public static function getMenuLabel(): string
    {
        return 'Inicio';
    }

    public static function getMenuIcon(): ?string
    {
        return '⏳';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $user = Auth::user();
        $userName = $user instanceof User ? $user->name : '';

        $message = 'Se ha registrado en el sistema, en breve se le asignará un rol. Mientras tanto, puede actualizar su perfil y su foto de avatar.';

        $container
            ->layout(LayoutType::VERTICAL)
            ->plain()
            ->justifyContent('start')
            ->alignItems('center')
            ->padding(Spacing::px(30))
            ->minHeight(Size::vh(80));

        $container->add(
            UI::label('registered_icon')
                ->text('⏳')
                ->style('h1')
                ->center()
                ->fontSize('64px')
        );

        // Vista previa del avatar usando el componente UI::image()
        $this->registered_avatar = UI::image('registered_avatar')
            ->alt($userName)
            ->width(Size::px(96))
            ->height(Size::px(96))
            ->circular()
            ->objectFit('cover')
            ->shadow(true)
            ->visible(false);

        $container->add($this->registered_avatar);

        $container->add(
            UI::label('registered_title')
                ->text('Cuenta Pendiente de Asignación de Rol')
                ->style('h2')
                ->center()
                ->fontSize('26px')
                ->fontWeight('bold')
                ->color('#00d4aa')
                ->marginTop(Spacing::px(12))
        );

        $this->registered_greeting = UI::label('registered_greeting')
            ->text("¡Hola, {$userName}!")
            ->style('h3')
            ->center()
            ->fontSize('18px')
            ->color('#7a8499')
            ->marginTop(Spacing::px(6));

        $container->add($this->registered_greeting);

        $container->add(
            UI::card('registered_card')
                ->title('Estado de su cuenta')
                ->description($message)
                ->theme('info')
                ->maxWidth(Size::px(650))
                ->marginTop(Spacing::px(24))
                ->addAction('👤 Editar Mi Perfil / Avatar', 'go_to_profile', [], 'primary')
        );

        $container->add(
            UI::label('registered_message')
                ->text('Un administrador revisará su solicitud y le asignará el rol de Publicador o Administrador.')
                ->style('p')
                ->center()
                ->maxWidth(Size::px(650))
                ->fontSize('15px')
                ->marginTop(Spacing::px(16))
                ->color('#7a8499')
        );
    }

    protected function postLoadUI(): void
    {
        $user = Auth::user();
        if ($user instanceof User) {
            $this->refreshUserView($user);
        }
    }

    /**
     * Abre el modal de Perfil en modo SPA.
     */
    public function onGoToProfile(array $params): void
    {
        $this->openModal(Profile::class);
    }

    /**
     * Escucha el evento 'updated_profile' emitido por la pantalla Profile
     * para actualizar reactivamente el nombre y el avatar sin recargar la página.
     */
    public function onUpdatedProfile(array $params): void
    {
        $user = $params['user'] ?? Auth::user();
        if ($user instanceof User) {
            $this->refreshUserView($user);
        }
    }

    private function refreshUserView(User $user): void
    {
        $this->registered_greeting->text("¡Hola, {$user->name}!");

        if (\is_string($user->profile_image) && $user->profile_image !== '') {
            $imageUrl = UploadService::fileUrl("uploads/images/{$user->profile_image}") . '?t=' . time();
            $this->registered_avatar
                ->src($imageUrl)
                ->alt($user->name)
                ->visible(true);
        } else {
            $this->registered_avatar->visible(false);
        }
    }
}
```

---

### 5.2 Pantalla para Publicar Posts (`app/UI/Screens/PublishPost.php`)

Esta pantalla utiliza `UI::uploader()` para subir la imagen de portada del post y `self::requireRole(['publisher', 'admin'])` para autorizar únicamente a publicadores y administradores. Al guardar el post, navega en modo SPA a `Home::class` usando `$this->showInto(Home::class, force: true, updateBrowserUrl: true)`.

Crea el archivo `app/UI/Screens/PublishPost.php`:

```php
<?php

namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Uploader;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class PublishPost extends Screen
{
    protected Input $post_title;
    protected Input $post_content;
    protected Uploader $post_image;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
    }

    public static function authorize(): bool
    {
        return self::requireRole(['publisher', 'admin']);
    }

    public static function getMenuLabel(): string
    {
        return 'Publicar Post';
    }

    public static function getMenuIcon(): ?string
    {
        return '✍️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->plain()
            ->width(Size::full())
            ->maxWidth(Size::px(720))
            ->centerHorizontal()
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $header = UI::container('publish_header')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero())
            ->gap(Spacing::px(16));

        $header->add(
            UI::label('lbl_publish_title')
                ->text('✍️ Publicar Nuevo Post')
                ->style('h2')
                ->color('primary')
        );

        $header->add(
            UI::button('btn_back_home')
                ->label('← Volver al Inicio')
                ->action('cancel_post')
                ->style('secondary')
        );

        $container->add($header);

        $formCard = UI::container('publish_form_card')
            ->card()
            ->width(Size::full())
            ->layout(LayoutType::VERTICAL)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $this->post_title = UI::input('post_title')
            ->label('Título del Post')
            ->placeholder('Escribe un título atractivo...')
            ->required(true)
            ->width(Size::full());

        $this->post_content = UI::input('post_content')
            ->label('Contenido')
            ->placeholder('Desarrolla el contenido de tu publicación...')
            ->required(true)
            ->width(Size::full());

        // Componente Uploader para subir la imagen de portada
        $this->post_image = UI::uploader('post_image')
            ->images()
            ->maxFiles(1)
            ->maxSize(5)
            ->aspect('16:9')
            ->size(2)
            ->label('Imagen de Portada (opcional)');

        $actionsRow = UI::container('publish_actions_row')
            ->width(Size::full())
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::END)
            ->gap(Spacing::px(12))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $actionsRow->add(
            UI::button('btn_cancel_post')
                ->label('Cancelar')
                ->action('cancel_post')
                ->style('secondary')
        );

        $actionsRow->add(
            UI::button('btn_save_post')
                ->label('Publicar Post')
                ->action('save_post')
                ->style('primary')
        );

        $formCard->add($this->post_title);
        $formCard->add($this->post_content);
        $formCard->add($this->post_image);
        $formCard->add($actionsRow);

        $container->add($formCard);
    }

    public function onSavePost(array $params): void
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            $this->toast('Debes iniciar sesión para publicar.', 'error');
            return;
        }

        $rawTitle = $params['post_title'] ?? '';
        $rawContent = $params['post_content'] ?? '';

        $title = is_string($rawTitle) ? trim($rawTitle) : '';
        $content = is_string($rawContent) ? trim($rawContent) : '';

        $this->post_title->error(null);
        $this->post_content->error(null);

        $hasErrors = false;
        if ($title === '') {
            $this->post_title->error('El título es obligatorio.');
            $hasErrors = true;
        }

        if ($content === '') {
            $this->post_content->error('El contenido es obligatorio.');
            $hasErrors = true;
        }

        if ($hasErrors) {
            $this->toast('Por favor completa los campos obligatorios.', 'warning');
            return;
        }

        // Confirmar y mover la imagen temporal a storage/app/public/uploads/images/
        $confirmedImage = $this->post_image->confirm($params, 'images');
        $imageFilename = null;
        if (is_string($confirmedImage) && $confirmedImage !== '') {
            $imageFilename = $confirmedImage;
        } elseif (is_array($confirmedImage) && isset($confirmedImage[0]) && is_string($confirmedImage[0])) {
            $imageFilename = $confirmedImage[0];
        }

        $this->postService->createPost([
            'user_id' => (int) $user->id,
            'title' => $title,
            'content' => $content,
            'image' => $imageFilename,
        ]);

        $this->toast('¡Post publicado correctamente!', 'success');

        // Limpiar snapshot en caché de Home y navegar vía SPA forzando recarga de los últimos posts
        Screen::make(Home::class)->clearCachedScreenSnapshot();
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }

    public function onCancelPost(array $params): void
    {
        Screen::make(Home::class)->clearCachedScreenSnapshot();
        $this->showInto(Home::class, force: true, updateBrowserUrl: true);
    }
}
```

---

### 5.3 Pantalla Principal de Últimos Posts (`app/UI/Screens/Home.php`)

La pantalla `Home` es pública (`Visibility::PUBLIC`) y adapta su interfaz según quién la visite:
- Si el usuario autenticado sólo tiene el rol `registered`, embebe únicamente `Registered::class`.
- Si es `guest`, muestra los últimos posts y botones para abrir `Login` o `Register` en modal.
- Si es `publisher` o `admin`, muestra el botón **Publicar Post** (`showInto(PublishPost::class)`).
- Si es `admin`, muestra además el botón **Gestionar Usuarios** (`showInto(UsersManager::class)`) y el botón **🗑️ Eliminar** en cada post.
- Cada post con imagen utiliza `UI::image()` para renderizar su portada.
- Implementa `postLoadUI()` para refrescar siempre el contenedor y volver a consultar los últimos posts desde la base de datos aunque el snapshot de `Home` se recupere de la caché de USIM.

Edita `app/UI/Screens/Home.php`:

```php
<?php
// @usim: feature="admin", type="screen"
namespace App\UI\Screens;

use App\Contracts\PostServiceInterface;
use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use App\UI\Screens\Auth\Login;
use App\UI\Screens\Auth\Register;
use Idei\Usim\Components\Container;
use Idei\Usim\Enums\AlignItems;
use Idei\Usim\Enums\JustifyContent;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;
use Illuminate\Support\Facades\Auth;

class Home extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected ?Container $posts_list_container = null;

    public function __construct(
        protected PostServiceInterface $postService
    ) {
    }

    public static function getMenuLabel(): string
    {
        return 'Inicio';
    }

    public static function getMenuIcon(): ?string
    {
        return '🏠';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->renderHomeContent($container);
    }

    protected function postLoadUI(): void
    {
        $container = $this->getContainer();
        if ($container instanceof Container) {
            $container->clear();
            $this->renderHomeContent($container);
        }
    }

    private function renderHomeContent(Container $container): void
    {
        $user = Auth::user();

        // Si el usuario tiene únicamente el rol "registered", sólo debe ver la pantalla Registered
        if ($user instanceof User && $user->isRegisteredInLobby()) {
            $container
                ->plain()
                ->padding(Spacing::zero());
            Screen::make(Registered::class)->clearCachedScreenSnapshot();
            Screen::embedInto(Registered::class, $container);
            return;
        }

        $container
            ->maxWidth(Size::px(900))
            ->centerHorizontal()
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(20))
            ->plain();

        $header = UI::container('home_header')
            ->card()
            ->layout(LayoutType::HORIZONTAL)
            ->justifyContent(JustifyContent::SPACE_BETWEEN)
            ->alignItems(AlignItems::CENTER)
            ->padding(Spacing::px(20))
            ->gap(Spacing::px(16));

        $titleBox = UI::container('home_title_box')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(4))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $titleBox->add(
            UI::label('lbl_home_title')
                ->text('📰 Últimos Posts')
                ->style('h2')
                ->color('primary')
        );

        $subtitleText = $this->resolveRoleSubtitle($user instanceof User ? $user : null);
        $titleBox->add(
            UI::label('lbl_home_subtitle')
                ->text($subtitleText)
                ->style('p')
                ->color('#7a8499')
        );

        $actionsBox = UI::container('home_actions_box')
            ->layout(LayoutType::HORIZONTAL)
            ->alignItems(AlignItems::CENTER)
            ->gap(Spacing::px(10))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        if (!$user instanceof User) {
            $actionsBox->add(
                UI::button('btn_home_login')
                    ->label('🔑 Iniciar Sesión')
                    ->action('open_login')
                    ->style('primary')
            );
            $actionsBox->add(
                UI::button('btn_home_register')
                    ->label('📝 Registrarse')
                    ->action('open_register')
                    ->style('secondary')
            );
        } else {
            if ($this->currentUserHasRole(['publisher', 'admin'])) {
                $actionsBox->add(
                    UI::button('btn_go_publish')
                        ->label('✍️ Publicar Post')
                        ->action('go_to_publish')
                        ->style('primary')
                );
            }

            if ($this->currentUserHasRole('admin')) {
                $actionsBox->add(
                    UI::button('btn_go_users')
                        ->label('👥 Gestionar Usuarios')
                        ->action('go_to_users')
                        ->style('secondary')
                );
            }
        }

        $header->add($titleBox);
        $header->add($actionsBox);
        $container->add($header);

        $this->posts_list_container = UI::container('posts_list_container')
            ->layout(LayoutType::VERTICAL)
            ->gap(Spacing::px(20))
            ->plain()
            ->shadow(false)
            ->padding(Spacing::zero());

        $this->renderPostsList();

        $container->add($this->posts_list_container);
    }

    /**
     * Comprueba si el usuario autenticado posee alguno de los roles indicados.
     *
     * @param string|list<string> $roles
     */
    private function currentUserHasRole(string|array $roles): bool
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return false;
        }

        if ($user->isRoot()) {
            return true;
        }

        $roleList = is_array($roles) ? $roles : [$roles];

        return $user->globalRoles()->whereIn('name', $roleList)->exists();
    }

    private function resolveRoleSubtitle(?User $user): string
    {
        if ($user === null) {
            return 'Modo invitado (guest): visualiza las publicaciones recientes o inicia sesión para participar.';
        }

        if ($this->currentUserHasRole('admin')) {
            return "Conectado como {$user->name} (Administrador): puedes publicar posts, eliminarlos y asignar roles a usuarios registrados.";
        }

        if ($this->currentUserHasRole('publisher')) {
            return "Conectado como {$user->name} (Publicador): puedes compartir nuevos posts con la comunidad.";
        }

        return "Conectado como {$user->name}.";
    }

    private function renderPostsList(): void
    {
        if (!$this->posts_list_container instanceof Container) {
            return;
        }

        $this->posts_list_container->clear();

        $posts = $this->postService->getLatestPosts();

        if ($posts->isEmpty()) {
            $emptyCard = UI::container('posts_empty_card')
                ->card()
                ->padding(Spacing::px(32))
                ->alignItems(AlignItems::CENTER);

            $emptyCard->add(
                UI::label('lbl_no_posts')
                    ->text('Aún no hay posts publicados.')
                    ->style('h3')
                    ->center()
            );

            $this->posts_list_container->add($emptyCard);
            return;
        }

        $isAdmin = $this->currentUserHasRole('admin');

        foreach ($posts as $post) {
            $postCard = UI::container("post_card_{$post->id}")
                ->card()
                ->layout(LayoutType::VERTICAL)
                ->padding(Spacing::px(20))
                ->gap(Spacing::px(12));

            if (!empty($post->image) && is_string($post->image)) {
                $imageUrl = $this->resolvePostImageUrl($post->image);

                // Componente UI::image() para mostrar la portada del post
                $postCard->add(
                    UI::image("post_img_{$post->id}")
                        ->src($imageUrl)
                        ->alt((string) $post->title)
                        ->width(Size::full())
                        ->height(Size::px(280))
                        ->objectFit('cover')
                        ->rounded(8)
                );
            }

            $postHeader = UI::container("post_header_{$post->id}")
                ->layout(LayoutType::HORIZONTAL)
                ->justifyContent(JustifyContent::SPACE_BETWEEN)
                ->alignItems(AlignItems::CENTER)
                ->plain()
                ->shadow(false)
                ->padding(Spacing::zero());

            $postHeader->add(
                UI::label("post_title_{$post->id}")
                    ->text((string) $post->title)
                    ->style('h3')
            );

            if ($isAdmin) {
                $postHeader->add(
                    UI::button("btn_delete_post_{$post->id}")
                        ->label('🗑️ Eliminar')
                        ->action('delete_post', ['post_id' => (int) $post->id])
                        ->style('danger')
                );
            }

            $postCard->add($postHeader);

            $authorName = (string) ($post->author_name ?? 'Autor');
            $createdAt = !empty($post->created_at) ? (string) $post->created_at : '';

            $postCard->add(
                UI::label("post_meta_{$post->id}")
                    ->text("Publicado por {$authorName}" . ($createdAt !== '' ? " • {$createdAt}" : ''))
                    ->style('caption')
                    ->color('#7a8499')
            );

            $postCard->add(
                UI::label("post_content_{$post->id}")
                    ->text((string) $post->content)
                    ->style('p')
            );

            $this->posts_list_container->add($postCard);
        }
    }

    private function resolvePostImageUrl(string $image): string
    {
        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, '/')) {
            return $image;
        }

        return UploadService::fileUrl("uploads/images/{$image}");
    }

    public function onOpenLogin(array $params): void
    {
        $this->openModal(Login::class);
    }

    public function onOpenRegister(array $params): void
    {
        $this->openModal(Register::class);
    }

    public function onGoToPublish(array $params): void
    {
        $this->showInto(PublishPost::class, updateBrowserUrl: true);
    }

    public function onGoToUsers(array $params): void
    {
        $this->showInto(UsersManager::class, updateBrowserUrl: true);
    }

    public function onDeletePost(array $params): void
    {
        if (!$this->currentUserHasRole('admin')) {
            $this->toast('No tienes permisos para eliminar posts.', 'error');
            return;
        }

        $postId = isset($params['post_id']) && is_numeric($params['post_id'])
            ? (int) $params['post_id']
            : 0;

        if ($postId <= 0) {
            return;
        }

        $deleted = $this->postService->deletePost($postId);

        if ($deleted) {
            $this->toast('Post eliminado correctamente.', 'success');
            $this->renderPostsList();
        } else {
            $this->toast('No se encontró el post a eliminar.', 'warning');
        }
    }
}
```

---

### 5.4 Menú Superior 100% SPA y Pantallas de Autenticación (`app/UI/Screens/Menu.php`, `Login.php`, `Register.php`)

Para garantizar que **ninguna acción del menú ni del registro/login provoque una recarga del navegador**, utilizamos `MenuBuilder::screenShow()` en lugar de `MenuBuilder::link()`, y eliminamos el selector de idiomas (ya que nuestra app funciona íntegramente en español) y el selector de unidades múltiples.

1. En `app/UI/Screens/Auth/Login.php`:
   - Configura las etiquetas del menú en español (`Iniciar Sesión` / `🔑`).
   - En `onLoggedUser()`, asegúrate de no hacer `return` prematuro tras `$this->closeModal()`, y limpia el snapshot en caché antes de navegar con `$this->showInto($homeScreen, force: true, updateBrowserUrl: true)`.

2. En `app/UI/Screens/Auth/Register.php`:
   - Configura las etiquetas del menú en español (`Registrarse` / `📝`).
   - Al final de `onSubmitRegister()`, tras `$this->authSessionService->establishSession(...)` y `$this->closeModal()`, navega explícitamente vía SPA a la pantalla inicial del usuario registrado (`Registered::class`):
   ```php
   $homeScreen = $this->authSessionService->resolveHomeScreen($user);
   if (is_string($homeScreen) && class_exists($homeScreen) && is_subclass_of($homeScreen, Screen::class)) {
       Screen::make($homeScreen)->clearCachedScreenSnapshot();
       $this->showInto($homeScreen, force: true, updateBrowserUrl: true);
   }
   ```

3. En `app/UI/Screens/Auth/Profile.php`:
   - Configura `getMenuLabel(): string` para retornar `'Mi Perfil'`.

4. En `app/UI/Screens/Menu.php` (métodos `getMainMenuBuilder()` y `onShowScreen()`):

```php
private function getMainMenuBuilder(): MenuBuilder
{
    $builder = MenuBuilder::make('main_menu')
        ->trigger()
        ->position('bottom-left')
        ->width(Size::px(220));

    $user = Auth::user();
    $isRegisteredOnly = $user instanceof User && $user->isRegisteredInLobby();

    // Navegación 100% SPA mediante screenShow() (sin recarga de página)
    $builder->screenShow(
        Registered::class,
        when: $isRegisteredOnly
    );

    $builder->screenShow(
        Home::class,
        when: !$isRegisteredOnly
    );

    $builder->screenShow(
        PublishPost::class,
        when: PublishPost::checkAccess()['allowed'] == true
    );

    $builder->screenShow(
        UsersManager::class,
        when: UsersManager::checkAccess()['allowed'] == true
    );

    $builder->separator();
    $builder->action(t('screens.menu.items.about'), 'show_about_info', [], 'ℹ️');

    return $builder;
}

public function onShowScreen(array $params): void
{
    $screen = $params['screen'] ?? null;
    if (!is_string($screen) || !class_exists($screen) || !is_subclass_of($screen, Screen::class)) {
        return;
    }

    Screen::make($screen)->clearCachedScreenSnapshot();
    $this->showInto($screen, force: true, updateBrowserUrl: true);
}
```

---

## 🔄 Paso 6: Ejecutar Migraciones, Sincronizar USIM y Sembrar Datos

Ejecuta los siguientes comandos para crear la tabla `posts`, sincronizar las unidades, roles, permisos y usuarios de `config/usim.php`, y enlazar el almacenamiento público para las imágenes:

```bash
php artisan migrate
php artisan usim:sync
php artisan storage:link
```

*(Si deseas contar con posts de bienvenida pre-cargados, puedes ejecutar `php artisan db:seed`).*

---

## 🧭 Paso 7: Recorrido por los Roles de la Aplicación

Levanta el servidor de desarrollo:

```bash
php artisan serve
```

Abre `http://localhost:8000` en tu navegador y prueba cada flujo sin que la página se recargue en ningún momento:

1. **Como `guest` (Invitado)**:
   - Al entrar a `/`, ves la pantalla `Home` con los últimos posts publicados y sus imágenes (`UI::image()`).
   - No ves botones para publicar ni para eliminar posts.
2. **Como `registered` (`registered@example.com` / `password` o registrando una cuenta nueva)**:
   - Al iniciar sesión o registrarte, USIM te lleva automáticamente en modo SPA a la pantalla `Registered`.
   - Verás el mensaje: *"Se ha registrado en el sistema, en breve se le asignará un rol."*
   - Haz clic en **👤 Editar Mi Perfil / Avatar**: se abrirá el modal `Profile` donde puedes subir una imagen con `UI::uploader()`. Al guardar, tanto el avatar circular en la pantalla `Registered` (`UI::image()`) como el icono del menú superior se actualizan reactivamente.
3. **Como `admin` (`admin@example.com` / `password`)**:
   - En `Home`, cada post muestra el botón **🗑️ Eliminar**, que borra el post y refresca el listado instantáneamente.
   - Haz clic en **👥 Gestionar Usuarios** (`UsersManager`): verás a los usuarios en estado `En espera (Registrado)`. Al hacer clic en **Editar**, podrás aprobar al usuario y asignarle el rol **Publicador (`publisher`)** o **Administrador (`admin`)**.
4. **Como `publisher` (`publisher@example.com` / `password` o el usuario recién promovido)**:
   - En `Home` (o desde el menú principal `☰`), haz clic en **✍️ Publicar Post**.
   - Completa el título, el contenido y sube una imagen de portada con `UI::uploader()`.
   - Al pulsar **Publicar Post**, la imagen temporal se confirma (`$this->post_image->confirm($params, 'images')`) y vuelves mediante SPA a `Home`, donde el nuevo post aparece en primer lugar.
