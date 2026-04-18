# Prompt de Continuidad (Headless + Agent Context en Screens)

Copia y pega este texto en un chat nuevo para retomar exactamente desde el estado actual.

---

Hola. Quiero retomar el diseño de USIM en este repositorio Laravel monorepo:

- Workspace: /workspaces/usim-framework
- Rama actual: main
- Modelo mental obligatorio: USIM es backend-driven/server-driven UI

## Lo que ya se discutió y quedó claro

1. Definición de Screen en USIM:
- Una Screen es una clase PHP de página completa, no una vista pasiva.
- Debe concentrar UI + estado + reglas de interacción en backend.
- El frontend es renderizador del contrato JSON, no fuente de verdad.

2. Routing automático de Screen:
- No hay una Route de Laravel por cada Screen.
- USIM usa:
	- ruta web catch-all (shell), y
	- loader API genérico /api/ui/{screen}
- El mapeo URL <-> clase Screen se hace por convención de namespace.

3. Permisos y menús:
- La Screen resuelve acceso con authorize()/checkAccess().
- También aporta metadata de navegación (label/icon/route) para menús.

4. Headless mode (idea, no implementado):
- Variable de entorno booleana HEADLESS_MODE (default false).
- Cuando true: backend debe servir JSON puro sin cliente renderer web.
- Aún no se implementó nada.

5. Agent context en Screens (idea, no implementado):
- Queremos que cada Screen pueda comunicar a un agente:
	- qué hace,
	- qué datos espera,
	- cómo se completa,
	- y eventualmente soportar interacción conversacional.
- Aún no se decidió si será metadata simple, parámetro textual o componente nuevo.

## Cambios de documentación ya realizados (importante)

Se actualizaron:
- .github/copilot-instructions.md
- README.md
- docs/README.md
- packages/idei/usim/README.md
- resources/views/welcome-usim.blade.php
- database/translations/es.json
- database/translations/en.json

con aclaraciones de Screen, routing por convención, permisos y metadata de menú.

## Qué necesito ahora en esta nueva sesión

No implementes código de una. Primero quiero diseño técnico.

### Objetivo inmediato

Proponer un diseño incremental para:

1) HEADLESS_MODE
- comportamiento exacto cuando true
- rutas afectadas
- compatibilidad con modo actual
- impacto en payload (redirect, toast, modal, abort, storage)

2) Agent Context por Screen
- contrato mínimo (MVP) y extensible
- dónde vive (método en Screen, trait, metadata, componente)
- cómo exponerlo (payload, endpoint, ambos)
- cómo mantener compatibilidad hacia atrás

### Entregable esperado en esta sesión

Quiero 3 propuestas de arquitectura (mínima, intermedia, robusta), con:

- pros/contras
- alcance de archivos a tocar
- riesgos
- estrategia de migración
- estrategia de pruebas

No implementes todavía. Primero consensuamos diseño.

---

Restricciones:

- Mantener filosofía backend-driven de USIM.
- No mover lógica de negocio al cliente.
- Preservar backward compatibility de contratos y meta keys reservadas.
- Separar claramente decisiones de framework (packages/idei/usim) y app consumidora.

