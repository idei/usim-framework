# 📚 Documentación del Proyecto

> **Última actualización:** 30 de noviembre de 2025

## 📂 Estructura de Documentación

```
docs/
├── README.md                          # Este archivo - Índice principal
│
├── framework/                         # Framework USIM
│   ├── USIM_ACADEMIC_REPORT.md       # ⭐ Documentación principal
│   ├── UI_BUILDER_REFERENCE.md       # Referencia de UI API
│   ├── CONTAINER_ALIGNMENT_GUIDE.md  # Guía de alineación de contenedores
│   └── TECHNICAL_COMPONENTS_README.md# Sistema CSS y componentes técnicos
│
├── api/                               # API REST y Comunicación
│   ├── API_COMPLETE_DOCUMENTATION.md # Endpoints REST completos
│   └── EMAIL_CUSTOMIZATION_GUIDE.md  # Sistema de emails
│
├── deployment/                        # Producción y Deployment
│   └── PRODUCTION_UPLOAD_FIX.md      # Configuración de uploads en producción
│
└── tooling/                           # Herramientas de Desarrollo
    ├── LOG_VIEWER.md                  # Sistema de logs
    ├── LOG_VIEWER_DEMO.md             # Demo de logs
    └── COLORS_GUIDE.md                # Guía de colores en tests
```

---

## 🎯 Acceso Rápido por Categoría

### 🚀 Framework USIM (UI Services Implementation Model)

| Documento | Descripción | Tamaño |
|-----------|-------------|--------|
| **[USIM_ACADEMIC_REPORT.md](framework/USIM_ACADEMIC_REPORT.md)** ⭐ | Documentación académica completa del framework. Arquitectura, características, ejemplos de servicios reales y comparativas con stack tradicional | 41K |
| **[UI_BUILDER_REFERENCE.md](framework/UI_BUILDER_REFERENCE.md)** | Referencia técnica de UI. Patrón Composite, manipulación de árbol, todos los componentes disponibles | 13K |
| **[CONTAINER_ALIGNMENT_GUIDE.md](framework/CONTAINER_ALIGNMENT_GUIDE.md)** | Guía específica de alineación de contenedores horizontales con ejemplos | 5.2K |
| **[TECHNICAL_COMPONENTS_README.md](framework/TECHNICAL_COMPONENTS_README.md)** | Sistema CSS modular, variables, temas y personalización | 13K |

Notas de versión funcional recientes:

- **Headless Mode** (`USIM_HEADLESS_MODE=true`): USIM puede correr en modo API-only usando `GET /api/ui/{screen}` + `POST /api/ui-event`.
- **Agent Context** (`Screen::getAgentContext()`): metadata opcional para clientes IA/headless en la key `agent_context` del payload.

**Total Framework:** 4 documentos, ~72K

---

### 🌐 API REST y Comunicación

| Documento | Descripción | Tamaño |
|-----------|-------------|--------|
| **[API_COMPLETE_DOCUMENTATION.md](api/API_COMPLETE_DOCUMENTATION.md)** | Documentación completa de endpoints REST. Autenticación Sanctum, estructura de respuestas, manejo de archivos | 30K |
| **[EMAIL_CUSTOMIZATION_GUIDE.md](api/EMAIL_CUSTOMIZATION_GUIDE.md)** | Personalización de emails: CSS inline, vistas Blade, notificaciones Mailable | 7.6K |

**Total API:** 2 documentos, ~38K

---

### 🚀 Deployment y Producción

| Documento | Descripción | Tamaño |
|-----------|-------------|--------|
| **[PRODUCTION_UPLOAD_FIX.md](deployment/PRODUCTION_UPLOAD_FIX.md)** | Solución error 413 en uploads. Configuración PHP-FPM y Nginx para producción | 6.2K |

**Total Deployment:** 1 documento, ~6K

---

### 🛠️ Herramientas de Desarrollo

| Documento | Descripción | Tamaño |
|-----------|-------------|--------|
| **[LOG_VIEWER.md](tooling/LOG_VIEWER.md)** | Sistema de visualización de logs. Interfaz web, filtros, configuración | 6.6K |
| **[LOG_VIEWER_DEMO.md](tooling/LOG_VIEWER_DEMO.md)** | Ejemplos prácticos para generar logs de prueba | 6.4K |
| **[COLORS_GUIDE.md](tooling/COLORS_GUIDE.md)** | Guía visual del esquema de colores en tests con Pest | 3K |

**Total Tooling:** 3 documentos, ~16K

---

## 📖 Guía de Lectura Recomendada

## 🧠 Concepto Operativo de Screen (USIM)

Antes de profundizar en APIs o componentes, usa este modelo mental:

- Una **Screen** es una clase PHP que representa una página completa.
- La UI se define en backend y el cliente solo renderiza contrato JSON.
- El estado es server-side y se sincroniza incrementalmente por diffs (delta updates).
- Las acciones del cliente se resuelven en handlers `on<ActionName>` en la misma Screen.
- La URL, el control de acceso y la metadata de menú también salen de la propia Screen.

Aclaración:
USIM no registra una ruta Laravel separada por cada Screen; usa convención namespace/path, una ruta catch-all web y un endpoint API genérico para resolver la clase correspondiente.

Para detalle técnico completo:

- `packages/idei/usim/README.md` (secciones *Screens*, *Event Handlers* y *State Management*).
- `framework/USIM_ACADEMIC_REPORT.md` para visión arquitectónica amplia.

### 👨‍💻 Para Nuevos Desarrolladores:
1. **Inicio:** [framework/USIM_ACADEMIC_REPORT.md](framework/USIM_ACADEMIC_REPORT.md) - Comprender el framework
2. **API Técnica:** [framework/UI_BUILDER_REFERENCE.md](framework/UI_BUILDER_REFERENCE.md) - Referencia de componentes
3. **Ejemplos:** Revisar ButtonDemoService, ProfileService y ModalDemoService en USIM_ACADEMIC_REPORT
4. **REST API:** [api/API_COMPLETE_DOCUMENTATION.md](api/API_COMPLETE_DOCUMENTATION.md) - Endpoints disponibles

### 🎨 Para Desarrollo de UI:
1. [framework/USIM_ACADEMIC_REPORT.md](framework/USIM_ACADEMIC_REPORT.md) - Framework completo
2. [framework/UI_BUILDER_REFERENCE.md](framework/UI_BUILDER_REFERENCE.md) - API de UI
3. [framework/CONTAINER_ALIGNMENT_GUIDE.md](framework/CONTAINER_ALIGNMENT_GUIDE.md) - Layouts específicos
4. [framework/TECHNICAL_COMPONENTS_README.md](framework/TECHNICAL_COMPONENTS_README.md) - CSS y estilos

### 🚀 Para DevOps/Deployment:
1. [deployment/PRODUCTION_UPLOAD_FIX.md](deployment/PRODUCTION_UPLOAD_FIX.md) - Configuración de uploads
2. [tooling/LOG_VIEWER.md](tooling/LOG_VIEWER.md) - Monitoreo y debugging

### 🧪 Para Testing y Debugging:
1. [tooling/COLORS_GUIDE.md](tooling/COLORS_GUIDE.md) - Interpretar output de tests
2. [tooling/LOG_VIEWER_DEMO.md](tooling/LOG_VIEWER_DEMO.md) - Generar logs de prueba

---

## 📊 Resumen Estadístico

| Categoría | Documentos | Tamaño Total |
|-----------|------------|--------------|
| **Framework USIM** | 4 | ~72K |
| **API REST** | 2 | ~38K |
| **Deployment** | 1 | ~6K |
| **Tooling** | 3 | ~16K |
| **TOTAL** | **10** | **~132K** |

---

## 🗑️ Documentos Eliminados (Obsoletos)

Los siguientes documentos fueron removidos por estar desactualizados, duplicados o pertenecer a otro proyecto:

### Obsoletos - Framework USIM:
- ❌ `UI_FRAMEWORK_GUIDE.md` (52K) - API antigua (reemplazado por USIM_ACADEMIC_REPORT.md)
- ❌ `UPLOADER_COMPONENT_PLAN.md` (17K) - Plan ya implementado
- ❌ `pasos.md` (228 bytes) - Notas temporales
- ❌ `IMPLEMENTATION_COMPLETE_SUMMARY.md` (14K) - Información dispersa y redundante
- ❌ `FILE_UPLOAD_EXAMPLES.md` (5K) - Ejemplos de API legacy sin USIM

### De otro proyecto (CMS):
- ❌ `DATABASE_SEEDERS_GUIDE.md` (11K) - Sistema de seeders de CMS
- ❌ `DATABASE_QUERY_EXAMPLES.md` (13K) - Queries de CMS (posts, channels, medias)
- ❌ `SEEDERS_IMPLEMENTATION_SUMMARY.md` (11K) - Resumen de seeders de CMS
- ❌ `SEEDERS_FILES_INVENTORY.md` (11K) - Inventario de seeders de CMS

**Total eliminado:** ~134K en 9 documentos

---

## 🤝 Contribución

Al crear nueva documentación:
- Ubicar en la carpeta apropiada (`framework/`, `api/`, `deployment/`, `tooling/`)
- Usar Markdown con sintaxis clara
- Incluir ejemplos de código completos
- Mantener estructura consistente (título, introducción, ejemplos, resumen)
- Agregar entrada en este README.md

---

**Preparado por:** Equipo de Desarrollo IDEI  
**Última revisión:** 30 de noviembre de 2025  
**Versión del Framework:** USIM 1.0
