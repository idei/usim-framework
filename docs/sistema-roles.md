# Arquitectura de Control de Acceso y Gestión Organizacional (USIM Framework)

## 1. Visión General del Sistema

El framework USIM implementa un modelo híbrido de Control de Acceso Basado en Roles (RBAC) y Control de Acceso Basado en Atributos (ABAC). Este ecosistema está soportado por la funcionalidad "Teams" del paquete `spatie/laravel-permission`. En lugar de asignar permisos de forma plana y global, el sistema exige la definición de un **contexto de ejecución** obligatorio denominado **Unit** (Unidad Organizacional).

Toda evaluación de permisos (`hasPermissionTo`) y asignación de roles (`assignRole`) se realiza tridimensionalmente: `Usuario -> Unidad -> Rol`. Esto permite estructurar organigramas complejos donde un mismo usuario puede ejercer funciones administrativas en una unidad, operativas en otra, y carecer de acceso en las restantes, centralizando la configuración en un único catálogo global de roles definido en `config/usim.php`.

## 2. Componentes Principales y Palabras Reservadas

El núcleo de USIM se apoya en una nomenclatura estricta para garantizar la integridad del enrutamiento, la inyección de dependencias y el bypass de seguridad.

### 2.1. Palabras Reservadas (Keywords Inmutables)

Para el correcto funcionamiento del framework, existen dos identificadores estructurales que son considerados **keywords de sistema estáticos** y **NO deben ser modificados** por el usuario en el archivo de configuración `usim.php`:

* **`main`**: Es el identificador (slug) absoluto y hardcodeado para la Unidad Organizacional principal o raíz. Incluso si el software final no requiere una estructura de unidades compleja, la unidad `main` debe existir siempre como el contexto base de ejecución.
* **`root`**: Es el identificador (slug) obligatorio para el rol de superusuario y la cuenta administrativa principal. Este rol posee privilegios de bypass globales en el sistema.

### 2.2. Unidades Organizacionales (Units)

Las Unidades representan los límites virtuales, departamentos o entidades dentro del sistema. Se definen estructuralmente en la configuración y se sincronizan con la base de datos mediante el comando CLI `usim:sync`.

* **Jerarquía Recursiva:** Soportan anidamiento mediante un esquema de adyacencia (`parent_id`) para modelar dependencias estructurales estandarizadas.
* **Membresía Desacoplada:** La relación física del usuario con la unidad se gestiona mediante una relación *BelongsToMany* estricta en la tabla pivote `unit_user`. Un usuario debe ser miembro explícito de una unidad para que el framework le permita inicializar dicho contexto.

### 2.3. Catálogo Global de Roles y Permisos

Los roles y permisos mantienen una naturaleza estrictamente global en la base de datos. La segmentación ocurre exclusivamente en la tabla pivote de asignación (`model_has_roles`), donde se inyecta el `unit_id` como clave foránea contextual, instruyendo al motor de autorización a evaluar los privilegios únicamente cuando el contexto de la petición coincide con la unidad asignada.

## 3. Ejemplos de Configuración (`config/usim.php`)

La configuración debe respetar estrictamente la existencia de las palabras reservadas `main` y `root`. A continuación se detalla la estructura canónica:

```php
    /*
    |--------------------------------------------------------------------------
    | Organizational Units (Units)
    |--------------------------------------------------------------------------
    */
    'units' => [
        // 'main' es una palabra reservada del sistema y su asignación es absoluta.
        'default' => 'main',

        'structure' => [
            // Definición inmutable de la unidad base
            'main' => [
                'parent' => null,
                'type' => 'institution',
                'default_translations' => [
                    'en' => ['display_name' => 'Main Organization', 'description' => 'Default system unit.'],
                    'es' => ['display_name' => 'Organización Principal', 'description' => 'Unidad base del sistema.'],
                ],
            ],
            // Unidades adicionales (Opcionales)
            'engineering' => [
                'parent' => 'main',
                'type' => 'department',
                'default_translations' => [
                    'en' => ['display_name' => 'Engineering', 'description' => 'Engineering department.'],
                    'es' => ['display_name' => 'Ingeniería', 'description' => 'Departamento de ingeniería.'],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Super Admin Configuration
    |--------------------------------------------------------------------------
    */
    'users' => [
        // 'root' es una palabra reservada del sistema.
        'root' => [
            'first_name' => env('ROOT_FIRST_NAME', 'Root'),
            'last_name' => env('ROOT_LAST_NAME', 'User'),
            'email' => env('ROOT_EMAIL', 'root@example.com'),
            'password' => env('ROOT_PASSWORD', 'CHANGE_ME'),
            // Asignación tridimensional obligatoria utilizando los keywords
            'unit_roles' => ['main' => ['root']],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Roles Dictionary
    |--------------------------------------------------------------------------
    */
    'roles' => [
        // 'root' es una palabra reservada y debe existir siempre.
        'root' => [
            'default_translations' => [
                'en' => ['display_name' => 'Root', 'description' => 'Total system access.'],
                'es' => ['display_name' => 'Raíz', 'description' => 'Acceso total al sistema.'],
            ],
            'priority' => 1,
            'home_screen' => UsersManager::class,
            'permissions' => ['admin.users_manager.access', 'manage.roles'],
        ],
        // Roles adicionales
        'registered' => [
            'default_translations' => [
                'en' => ['display_name' => 'Registered User', 'description' => 'Basic access.'],
                'es' => ['display_name' => 'Usuario Registrado', 'description' => 'Acceso básico.'],
            ],
            'priority' => 0,
            'home_screen' => Home::class,
            'permissions' => ['home.access'],
        ],
    ],

```

## 4. Flujo Operativo: Del Registro a la Administración

### 4.1. Fase de Registro (El Patrón "Lobby")

Por diseño estructural, el sistema no expone la selección de unidades operativas al público durante el alta. Cuando un actor completa el proceso de registro estándar:

1. Es insertado en la base de datos principal de identidades (`users`).
2. Es afiliado automáticamente a la unidad keyword por defecto `main`.
3. El framework inyecta el contexto de `main` en el `PermissionRegistrar` y le asigna el rol base estipulado (`default_registering_role`, comúnmente `registered`).

En este estado (Lobby), el usuario posee una sesión válida y es capaz de renderizar la pantalla inicial, pero carece de visibilidad o privilegios sobre los dominios funcionales operativos de la organización.

### 4.2. Anexión y Delegación de Autoridad

La incorporación de un usuario a un área operativa (ej. `engineering`) opera bajo un modelo de delegación estricta *Top-Down*:

1. **Afiliación:** Un administrador con autoridad sobre el área (o el usuario Root) localiza la identidad del usuario en el sistema global y genera la vinculación explícita (`syncWithoutDetaching`) en la tabla `unit_user` asociada a la unidad destino.
2. **Asignación Contextual:** Tras la afiliación, el administrador establece el rol operativo dentro de ese contexto específico, persistiendo el privilegio en la tabla pivote de Spatie amarrado al `unit_id` correspondiente.

## 5. Niveles de Autorización y Aislamiento

El framework distingue dos esferas de administración para garantizar la integridad de la estructura organizacional y el aislamiento de datos:

### 5.1. Autoridad Local (Administrador de Área)

Un usuario con el rol de administración dentro de una unidad específica opera estrictamente dentro de las fronteras de dicho `unit_id`.

* Sus consultas a la base de datos y su acceso a la interfaz de gestión (`UsersManager`) son filtradas para visualizar y mutar únicamente a los miembros de su propia unidad.
* Posee la capacidad de afiliar nuevos miembros desde el "Lobby" hacia su área, asignándoles roles de jerarquía inferior o igual a la suya, definidos por políticas de autorización (*Policies*).

### 5.2. Autoridad Global (Super Administrador / Root)

El perfil definido en la configuración bajo el keyword `root` opera mediante un patrón arquitectónico de **Bypass de Contexto**.

* **Implementación:** A través de un interceptor lógico `Gate::before` en el `AuthServiceProvider`, el sistema verifica si la identidad posee el rol `root` analizando directamente la relación de Eloquent, eludiendo deliberadamente el filtro contextual de unidad.
* **Capacidades:** Otorga acceso absoluto e irrestricto a todas las Unidades, interfaces y métodos del sistema sin requerir afiliación explícita (`unit_user`) en cada área. Es la única entidad capaz de mutar la arquitectura base y nombrar nuevos Administradores de Área.

## 6. Manejo del Estado, Middleware y Pantallas (Screens)

La arquitectura base de USIM emplea un sistema de componentes (*Screens*) donde la interfaz se almacena en caché y se rehidrata de forma *stateful* por usuario. Para integrar la autorización contextual con este diseño, el framework ejecuta un Middleware de UI global:

1. Intercepta la petición HTTP y resuelve la unidad activa solicitada (vía payload, encabezado o estado previo). Si no se provee ninguna, el sistema inyecta la unidad keyword `main` por defecto.
2. Verifica la membresía activa del usuario en `unit_user`.
3. Inyecta el identificador resuelto en el contexto global de autorización de Spatie.
4. Si el usuario solicita un cambio de contexto (cambio de área), el Middleware invalida la porción correspondiente de la caché, garantizando que la rehidratación de los componentes de la interfaz opere bajo las nuevas restricciones de permisos aplicables a la nueva unidad activa.
