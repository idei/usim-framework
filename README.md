# Microservicios API - Framework USIM

## Descripción

Este proyecto implementa **USIM (UI Services Implementation Model)**, un innovador framework backend-driven que permite construir interfaces de usuario dinámicas donde el backend controla completamente la estructura y lógica de la UI.

La plataforma está construida con **Laravel 11** y PHP 8.3+, siguiendo las mejores prácticas de desarrollo backend moderno. USIM elimina la necesidad de escribir código frontend, permitiendo que los desarrolladores backend construyan aplicaciones completas usando únicamente PHP.

## 🚀 Framework USIM

### ¿Qué es USIM?

USIM es un framework de UI reactivo donde:

- ✅ **El backend (PHP/Laravel) controla completamente la estructura y lógica de la UI**
- ✅ **El frontend (JavaScript) es un renderizador agnóstico que interpreta instrucciones**
- ✅ **Las actualizaciones son automáticas y optimizadas** (solo se envían cambios, no toda la UI)
- ✅ **Los componentes son reutilizables y type-safe** (con inyección automática)
- ✅ **El estado persiste entre requests** (cacheo inteligente en sesión)
- ✅ **Reducción del 40-60% del código** comparado con stack tradicional (Laravel + React)

### Ventajas Competitivas

| Aspecto | Stack Tradicional | USIM |
|---------|-------------------|------|
| **Archivos necesarios** | Controller + API Resource + React Component + Redux | 1 archivo PHP (Service) |
| **Líneas de código** | ~1050 (450 backend + 600 frontend) | ~140 total |
| **Validación** | Frontend + Backend duplicada | Solo Backend |
| **Estado** | Redux + localStorage manual | Propiedades del servicio (automático) |
| **Testing** | Unit tests backend + E2E frontend | Unit tests PHP únicos |

## Características y Funcionalidades

### Framework USIM
- **16+ Componentes UI** (Button, Input, Table, Uploader, Modal, etc.)
- **Event-Driven Architecture** con `UsimEvent` para comunicación entre servicios
- **Diffing Algorithm optimizado** (solo transmite cambios)
- **Sistema de IDs determinísticos** para componentes estables
- **Inyección automática de componentes** como propiedades
- **Sistema de modales** con `ConfirmDialogService` y múltiples tipos
- **Uploader avanzado** con crop, preview, validación y persistencia automática

### Sistema de Autenticación Completo
- **Registro de usuarios** con validación de datos
- **Autenticación Bearer Token** usando Laravel Sanctum
- **Verificación por email** con enlaces firmados
- **Reset de contraseñas** con tokens seguros
- **Logout seguro** con revocación de tokens

### Sistema de Archivos
- **Upload de archivos** con validación de tipos y tamaños
- **Almacenamiento temporal** con limpieza automática (cronjob)
- **Persistencia optimizada** con método `confirm()` de UploaderBuilder
- **Gestión de archivos** (listado, eliminación)
- **Sistema de attachments polimórficos**

### Herramientas de Desarrollo
- **Pruebas automatizadas** con PestPHP (configurado, en roadmap)
- **Sistema de logs** con visualizador web integrado
- **Tests con colores** para mejor debugging
- **Queue Workers** para procesamiento en background
- **Scheduler** para tareas programadas

## Documentacion de Tests de Screens

Si vas a crear o mantener tests de UI (USIM), usa estos documentos:

- Guia humana paso a paso: [tests/SCREEN_TESTING_GUIDE.md](tests/SCREEN_TESTING_GUIDE.md)
- Plantilla para pedirle a un agente que genere tests: [tests/prompt.md](tests/prompt.md)

Recomendacion: leer primero la guia y luego usar `tests/prompt.md` como base para generar nuevos tests de manera consistente.

### Características Técnicas
- **API RESTful** con respuestas JSON consistentes
- **Backend-driven UI** con renderizador JavaScript agnóstico
- **Validación robusta** centralizada en backend
- **Manejo de errores** consistente
- **Middleware de autenticación** configurado
- **Base de datos** con migraciones y factories

## Requisitos del Sistema

Antes de instalar el proyecto, asegúrate de tener instalado en tu computadora:

### Herramientas Necesarias

1. **PHP 8.2 o superior**
   - Descarga desde: https://www.php.net/downloads
   - Verifica la instalación: `php --version`

2. **Composer (Gestor de dependencias de PHP)**
   - Descarga desde: https://getcomposer.org/download/
   - Verifica la instalación: `composer --version`

3. **Git**
   - Descarga desde: https://git-scm.com/downloads
   - Verifica la instalación: `git --version`

4. **Un editor de código** (recomendado: VS Code)

## Instalación en Windows - Guía Detallada

### Paso 1: Instalar PHP en Windows

1. **Descargar PHP**:
   - Ve a https://windows.php.net/download/
   - Descarga la versión "Non Thread Safe" de PHP 8.2 o superior
   - Extrae el archivo ZIP en `C:\php`

2. **Configurar PHP**:
   - Agrega `C:\php` a la variable de entorno PATH de Windows
   - Copia `php.ini-development` y renómbralo a `php.ini`
   - Edita `php.ini` y descomenta las siguientes extensiones:
     ```ini
     extension=openssl
     extension=pdo_sqlite
     extension=sqlite3
     extension=curl
     extension=mbstring
     extension=fileinfo
     ```

3. **Verificar instalación**:
   ```powershell
   php --version
   ```

### Paso 2: Instalar Composer en Windows

1. **Descargar Composer**:
   - Ve a https://getcomposer.org/download/
   - Descarga e instala `Composer-Setup.exe`
   - El instalador configurará automáticamente PHP y las variables de entorno

2. **Verificar instalación**:
   ```powershell
   composer --version
   ```

### Paso 3: Instalar Git en Windows

1. **Descargar Git**:
   - Ve a https://git-scm.com/download/win
   - Descarga e instala la versión más reciente
   - Durante la instalación, selecciona "Use Git from the Windows Command Prompt"

2. **Verificar instalación**:
   ```powershell
   git --version
   ```

### Paso 4: Configurar PowerShell

1. **Abrir PowerShell como Administrador**
2. **Habilitar la ejecución de scripts** (si es necesario):
   ```powershell
   Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
   ```

## Instalación Local - Guía Paso a Paso

### Paso 1: Clonar el Repositorio
```powershell
git clone https://github.com/eormeno-idei/microservicios-api.git
cd microservicios-api
```

### Paso 2: Instalar Dependencias
```powershell
composer install
```

### Paso 3: Configurar Variables de Entorno
```powershell
# Copia el archivo de ejemplo (Windows)
copy .env.example .env

# Genera la clave de aplicación
php artisan key:generate
```

## Paso 4: Configuración del Usuario Administrador

Este paso es fundamental para que al poblar con `seed` la BBDD no de ningun error.

En el archivo `.env` hay que añadir lo siguiente:

```env
ADMIN_NAME="Administrador"
ADMIN_EMAIL="admin@tuproyecto.com"
ADMIN_PASSWORD="tu_password_seguro"
```

### Paso 5: Configurar Base de Datos
```powershell
# Crear la base de datos SQLite
php artisan migrate

# Opcional: Poblar con datos de prueba
php artisan db:seed
```

### Paso 6: Ejecutar el Servidor
```powershell
php artisan serve
```

Listo! Tu API estará disponible en: http://127.0.0.1:8000

### Comandos Adicionales para Windows

#### Limpiar caché y configuración
```powershell
php artisan config:clear
php artisan cache:clear
php artisan route:clear
```

#### Verificar el estado del proyecto
```powershell
php artisan about
```

#### Crear enlace simbólico para storage (si es necesario)
```powershell
php artisan storage:link
```

## Configuración de Mailtrap (Recomendado para Estudiantes)

Para probar las funcionalidades de email (verificación, reset de contraseña), te recomendamos usar **Mailtrap**:

### Paso 1: Crear Cuenta en Mailtrap
1. Ve a https://mailtrap.io/
2. Crea una cuenta gratuita
3. Inicia sesión en tu dashboard

### Paso 2: Obtener Credenciales
1. Ve a **Email Testing** > **Inboxes**
2. Crea un nuevo inbox o selecciona uno existente
3. Ve a la pestaña **SMTP Settings**
4. Selecciona **Laravel 9+** en el dropdown
5. Copia las credenciales mostradas

### Paso 3: Configurar en .env
Abre tu archivo `.env` y actualiza estas variables:

```env
MAIL_MAILER=smtp
MAIL_HOST=sandbox.smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=tu_usuario_mailtrap
MAIL_PASSWORD=tu_password_mailtrap
MAIL_FROM_ADDRESS="noreply@tuproyecto.com"
MAIL_FROM_NAME="Tu Proyecto"
```

> **Nota**: Reemplaza `tu_usuario_mailtrap` y `tu_password_mailtrap` con las credenciales reales de Mailtrap.

## Probando la API

### Servicios de Demostración Incluidos

El proyecto incluye 15+ servicios de ejemplo en USIM:

- **ButtonDemoService** - Botones con estados
- **ProfileService** - Perfil con upload de avatar
- **ModalDemoService** - Sistema de modales
- **FormDemoService** - Formularios complejos
- **TableDemoService** - Tablas con paginación
- **InputDemoService** - Inputs con validación
- Y más...

### Cliente Web Incluido
1. Ve a: http://127.0.0.1:8000
2. Usa el cliente interactivo para explorar servicios USIM

### Endpoints Principales API REST
- **POST** `/api/register` - Registrar usuario
- **POST** `/api/login` - Iniciar sesión
- **POST** `/api/logout` - Cerrar sesión
- **GET** `/api/user` - Obtener usuario autenticado
- **POST** `/api/password/forgot` - Solicitar reset de contraseña
- **POST** `/api/password/reset` - Resetear contraseña

Ver [docs/api/API_COMPLETE_DOCUMENTATION.md](docs/api/API_COMPLETE_DOCUMENTATION.md) para la lista completa.

## 🚀 Quick Start USIM

### Ejemplo Básico - Service Interactivo

```php
<?php
namespace App\Services\Screens;

use App\Services\UI\AbstractUIService;
use App\Services\UI\Components\ButtonBuilder;
use App\Services\UI\Components\LabelBuilder;
use App\Services\UI\Components\UIContainer;
use App\Services\UI\UIBuilder;

class HelloWorldService extends AbstractUIService
{
    protected LabelBuilder $lbl_message;
    protected ButtonBuilder $btn_click;
    
    protected function buildBaseUI(UIContainer $container, ...$params): void
    {
        $container
            ->title('Hello USIM')
            ->maxWidth('400px')
            ->centerHorizontal();
        
        $container->add(
            UIBuilder::label('lbl_message')
                ->text('Hello, World!')
                ->style('info')
        );
        
        $container->add(
            UIBuilder::button('btn_click')
                ->label('Click Me')
                ->action('handle_click')
                ->style('primary')
        );
    }
    
    public function onHandleClick(array $params): void
    {
        $this->lbl_message
            ->text('Button clicked! 🎉')
            ->style('success');
    }
}
```

**Resultado:** Una pantalla completa con lógica interactiva en ~30 líneas de PHP, sin JavaScript.

## 📚 Documentación

El proyecto incluye documentación completa organizada por categorías:

### 🚀 Framework USIM
- **[docs/framework/USIM_ACADEMIC_REPORT.md](docs/framework/USIM_ACADEMIC_REPORT.md)** ⭐ - Documentación principal
- **[docs/framework/UI_BUILDER_REFERENCE.md](docs/framework/UI_BUILDER_REFERENCE.md)** - Referencia UIBuilder API
- **[docs/framework/CONTAINER_ALIGNMENT_GUIDE.md](docs/framework/CONTAINER_ALIGNMENT_GUIDE.md)** - Guía de layouts
- **[docs/framework/TECHNICAL_COMPONENTS_README.md](docs/framework/TECHNICAL_COMPONENTS_README.md)** - Sistema CSS

### 🌐 API REST y Comunicación
- **[docs/api/API_COMPLETE_DOCUMENTATION.md](docs/api/API_COMPLETE_DOCUMENTATION.md)** - Endpoints REST
- **[docs/api/EMAIL_CUSTOMIZATION_GUIDE.md](docs/api/EMAIL_CUSTOMIZATION_GUIDE.md)** - Sistema de emails

### 🚀 Deployment y Producción
- **[docs/deployment/PRODUCTION_UPLOAD_FIX.md](docs/deployment/PRODUCTION_UPLOAD_FIX.md)** - Configuración uploads

### 🛠️ Herramientas de Desarrollo
- **[docs/tooling/LOG_VIEWER.md](docs/tooling/LOG_VIEWER.md)** - Sistema de logs
- **[docs/tooling/COLORS_GUIDE.md](docs/tooling/COLORS_GUIDE.md)** - Colores en tests

**Ver [docs/README.md](docs/README.md) para el índice completo.**

## 🎓 Tutoriales

Tutoriales paso a paso disponibles en `/tutoriales`:

- Migraciones y modelos Eloquent
- Seeders con archivos JSON
- Frontend con autenticación Sanctum
- Enums en Laravel
- Storage y archivos
- Controladores y rutas

## 🎯 Para Estudiantes y Desarrolladores

Este proyecto es ideal para:

- **Aprender USIM** - Framework backend-driven innovador
- **Desarrollo Full-Stack** sin necesidad de frameworks frontend
- **APIs RESTful** con Laravel Sanctum
- **Arquitecturas modernas** con event-driven design
- **Testing automatizado** con Pest
- **Deployment profesional** con mejores prácticas

### Sugerencias para Proyectos
1. **Crea nuevos servicios USIM** para tu dominio de negocio
2. **Extiende componentes** con nuevas funcionalidades
3. **Personaliza el sistema** de emails y notificaciones
4. **Implementa features** usando el patrón USIM
5. **Contribuye** con nuevos componentes al framework

### Ejecutar Pruebas
```powershell
# Ejecutar todas las pruebas
php artisan test

# Ejecutar pruebas específicas
php artisan test --filter AuthTest

# Ejecutar pruebas con cobertura (requiere Xdebug)
php artisan test --coverage
```

## Documentación Adicional

El proyecto incluye documentación detallada:

- `API_COMPLETE_DOCUMENTATION.md` - Documentación completa de la API
- `IMPLEMENTATION_COMPLETE_SUMMARY.md` - Resumen de implementaciones
- `EMAIL_CUSTOMIZATION_GUIDE.md` - Guía de personalización de emails
- `FILE_UPLOAD_EXAMPLES.md` - Ejemplos de subida de archivos
- `TECHNICAL_COMPONENTS_README.md` - Componentes técnicos

## Para Estudiantes

Este proyecto está diseñado específicamente para:

- **Aprender desarrollo backend** con tecnologías modernas
- **Entender arquitecturas de microservicios**
- **Practicar APIs RESTful**
- **Implementar autenticación y autorización**
- **Trabajar con bases de datos y migraciones**
- **Manejar testing automatizado**

### Sugerencias para Proyectos Personales
1. **Modifica las entidades** según tu dominio de negocio
2. **Agrega nuevos endpoints** para tus funcionalidades
3. **Personaliza los emails** con tu marca
4. **Implementa nuevas validaciones**
5. **Extiende el sistema de archivos**

## Solución de Problemas

### Problemas Comunes en Windows

#### Error: "php: command not found"
```powershell
# Verifica que PHP esté en el PATH
echo $env:PATH
# Agrega PHP al PATH si no está presente
$env:PATH += ";C:\php"
```

#### Error: "composer: command not found"
```powershell
# Reinstala Composer desde https://getcomposer.org/download/
# O verifica que esté en el PATH
composer --version
```

#### Error: "Extension not found"
```powershell
# Verifica que las extensiones estén habilitadas en php.ini
php -m | Select-String "openssl|pdo_sqlite|sqlite3"
```

#### Error: "Permission denied" en Windows
```powershell
# Ejecuta PowerShell como Administrador
# Verifica permisos de carpetas storage y bootstrap/cache
icacls storage /grant Everyone:(OI)(CI)F
icacls bootstrap\cache /grant Everyone:(OI)(CI)F
```

### Errores Generales

#### Error: "Class not found"
```bash
composer dump-autoload
```

#### Error: "Key not found"
```bash
php artisan key:generate
```

#### Error: "Database not found"
```bash
php artisan migrate:fresh
```

#### Error: "Port already in use"
```powershell
# Cambiar puerto del servidor
php artisan serve --port=8001
```

#### Error: "SQLite database locked"
```powershell
# Cierra todas las conexiones y reinicia
php artisan migrate:fresh --seed
```

## Soporte

Si tienes preguntas o encuentras problemas:

1. Revisa la **[documentación completa](docs/README.md)**
2. Consulta los **[tutoriales](tutoriales/)** paso a paso
3. Revisa los logs en `storage/logs/` o usa el **[Log Viewer](docs/tooling/LOG_VIEWER.md)**
4. Verifica tu configuración en `.env`
5. Ejecuta las pruebas: `php artisan test`

## 🗺️ Roadmap

- ✅ Framework USIM base completado
- ✅ 16+ componentes UI implementados
- ✅ Sistema de eventos y diffing
- ✅ Upload con crop y preview
- ⏳ Testing completo con Pest
- ⏳ Laravel Reverb (WebSockets push)
- ⏳ Android Native Renderer

## Licencia

Este proyecto está bajo la licencia MIT. Puedes usarlo libremente para tus proyectos personales y académicos.

## Créditos

Este framework utiliza y agradece el trabajo de las siguientes librerías Open Source:

- [spatie/laravel-permission](https://spatie.be/docs/laravel-permission) - Manejo de Roles y Permisos.
- [illuminate/support](https://laravel.com) - Componentes base de Laravel.

Agradecimiento especial a [Spatie](https://spatie.be) por sus excelentes contribuciones al ecosistema Laravel.

---

**Proyecto USIM - Backend-Driven UI Framework**

> Construye aplicaciones completas usando solo PHP. Sin React, sin Vue, sin complejidad frontend.

**Preparado por:** Equipo de Desarrollo IDEI  
**Versión:** USIM 1.0
