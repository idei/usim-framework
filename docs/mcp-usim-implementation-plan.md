# Plan Detallado: Implementacion MCP para USIM (Apto para Otro Chat de IA)

Fecha: 2026-04-19
Estado base del repo: USIM con Headless Mode y `Screen::getAgentContext()` ya implementados.
Objetivo: incorporar una capa MCP que permita a agentes IA (ChatGPT, Gemini, Claude, etc.) operar Screens USIM de forma segura, estable y portable.

## 1) Contexto y alcance

USIM ya expone el contrato headless:

- `GET /api/ui/{screen}` para carga inicial.
- `POST /api/ui-event` para interacciones.
- `X-USIM-Storage` para continuidad de estado.
- `agent_context` opcional por Screen via `getAgentContext(): array`.

Este plan NO mueve logica al frontend/LLM. La fuente de verdad sigue siendo backend USIM (estado + reglas + autorizacion).

Alcance de este plan:

- Crear un adaptador MCP sobre USIM.
- Definir herramientas (tools) para agentes.
- Mantener seguridad y permisos del backend.
- Validar con tests funcionales y de contrato.
- Documentar para integraciones multi-modelo.

Fuera de alcance inicial (post-MVP):

- Auto-planificacion compleja multi-step con memoria larga en servidor.
- Entrenamiento o fine-tuning de modelos.
- Sustitucion del contrato USIM existente.

## 2) Decision arquitectonica

Decision recomendada: MCP como capa de aplicacion (app consumidora), no como cambio core del paquete reusable.

Razon:

- Mantiene separacion de responsabilidades:
  - `packages/idei/usim/`: framework reusable.
  - `app/`: integracion con IA/MCP, policies y negocio.
- Evita acoplar el core USIM a un protocolo especifico de agentes.
- Permite evolucionar integración MCP sin romper APIs base del paquete.

Arquitectura objetivo:

1. Cliente IA (ChatGPT/Gemini/Claude) invoca tools.
2. Servidor MCP de la app interpreta tools.
3. MCP llama a USIM headless (`/api/ui/*`).
4. MCP persiste session state (storage + mapeo de componentes).
5. MCP devuelve resumen semantico al agente.

## 3) Objetivos de producto (MVP)

Un MVP se considera listo si cumple todos estos criterios:

1. Puede ejecutar al menos 2 eventos consecutivos sin perder estado.
2. Respeta autorizacion de Screens (`checkAccess/authorize`).
3. Entrega errores utiles para el agente (no solo 500 generico).
4. Soporta flujo real de usuario (ej: abrir screen + accionar + recibir redirect/toast).
5. Mantiene trazabilidad de acciones con auditoria minima.

## 4) Modelo de estado MCP

Por cada sesion de agente:

- `session_id`: id de conversacion MCP.
- `screen`: screen activa.
- `storage`: ultimo `X-USIM-Storage` valido.
- `component_map`: relacion `name -> json_key` + metadata minima.
- `last_payload`: ultima respuesta USIM (o resumen estructurado).
- `user_context`: usuario autenticado y atributos de permiso.

Regla clave: en cada `send_event`, reenviar `storage` actual y persistir el nuevo inmediatamente.

## 5) API de Tools MCP (propuesta base)

## 5.1 Tool: `open_screen`

Input:

- `screen` (string): ruta logica de screen.
- `params` (obj, opcional): parametros iniciales.

Accion interna:

- Llama `GET /api/ui/{screen}`.
- Captura payload + `storage`.
- Construye/actualiza `component_map`.

Output:

- `screen`.
- `agent_context` (si existe).
- `components_summary`.
- `available_actions`.
- `storage_state_ref` (interno del MCP, no exponer secretos).

## 5.2 Tool: `send_event`

Input:

- `component` (string): `name` o JSON key numérica.
- `action` (string).
- `event` (string, opcional segun contrato).
- `parameters` (obj, opcional).

Accion interna:

- Resuelve `component` a JSON key via `component_map`.
- Llama `POST /api/ui-event` con `X-USIM-Storage`.
- Persiste nuevo `storage` y cambios.

Output:

- `changes_summary`.
- `meta` relevante (`redirect`, `toast`, `abort`, `modal`, etc.).
- `updated_actions`.
- `next_recommendation` (opcional).

## 5.3 Tool: `describe_state`

Input:

- sin parametros (o `verbosity`).

Accion interna:

- Resume estado actual de sesion.

Output:

- `current_screen`.
- `goal` (desde `agent_context` si existe).
- `inputs_pending`.
- `safe_actions`.
- `warnings`.

## 5.4 Tool: `list_navigation` (opcional recomendado)

Input:

- sin parametros.

Output:

- `agent_navigation`: lista de opciones de menu con estado de acceso.
- Cada item: `{ label, route, available, reason? }`.

Politica sugerida:

- Si opcion existe pero no disponible: `available=false` y `reason` explicito.
- Evitar enumerar rutas sensibles ocultas que no deban revelarse.

## 6) Seguridad y gobernanza

Controles minimos desde fase temprana:

1. Allowlist de tools habilitadas.
2. Allowlist de acciones por screen cuando aplique.
3. Rate limiting por sesion/usuario.
4. Auditoria estructurada (quien, tool, input resumido, resultado, timestamp).
5. Sanitizacion de parametros de entrada.
6. Confirmacion explicita para acciones destructivas.

Regla de oro: MCP no omite validaciones USIM; solo orquesta.

## 7) Plan de implementacion por fases

## Fase 1: Esqueleto MCP

Objetivo: dejar estructura funcional minima.

Entregables:

- Modulo `app/Services/Mcp/*`.
- Rutas MCP en `routes/api.php` (scope propio).
- Handler base de tools.
- 2-3 tools mock para validar protocolo.

Criterio de salida:

- Endpoint MCP responde y registra sesion/tool call.

## Fase 2: Session Bridge MCP <-> USIM

Objetivo: persistencia real de estado.

Entregables:

- `McpSessionStore` (cache/db).
- Serializacion de `storage` + mapeo de componentes.
- Rehidratacion de sesion en cada tool call.

Criterio de salida:

- Dos llamadas consecutivas conservan contexto y storage.

## Fase 3: Tool `open_screen` real

Objetivo: abrir screens reales en headless.

Entregables:

- Integracion con `GET /api/ui/{screen}`.
- Extraccion de `agent_context`.
- Resumen de componentes y acciones.

Criterio de salida:

- Abre al menos 3 screens reales sin perdida de sesion.

## Fase 4: Tool `send_event` real

Objetivo: interacciones end-to-end.

Entregables:

- Integracion con `POST /api/ui-event`.
- Resolucion robusta de `component` (`name`/JSON key).
- Manejo de `meta` (`toast`, `redirect`, `abort`, etc.).

Criterio de salida:

- Flujo real de 2+ eventos consecutivos funcionando.

## Fase 5: Capa semantica para agente

Objetivo: mejorar decisiones del LLM y reducir tokens.

Entregables:

- `StateInterpreter` para resumen compacto.
- Estrategia de priorizacion de componentes accionables.
- Uso preferente de `agent_context` + hints utiles.

Criterio de salida:

- Respuesta MCP clara, corta y accionable para el agente.

## Fase 6: Seguridad avanzada

Objetivo: endurecer operacion.

Entregables:

- Policies por tool/screen/action.
- Confirmaciones obligatorias en operaciones sensibles.
- Audit trail completo.

Criterio de salida:

- Intentos no autorizados rechazados con mensajes claros y logs.

## Fase 7: Compatibilidad multi-modelo

Objetivo: facilitar integracion con distintos proveedores.

Entregables:

- Contratos tool-calling compatibles con OpenAI/Gemini.
- Adapter layer (si formato de tool difiere).
- Ejemplos de payloads por proveedor.

Criterio de salida:

- Mismo backend MCP usable por al menos 2 proveedores.

## Fase 8: Testing integral

Objetivo: cobertura funcional y de regresion.

Entregables:

- Tests feature MCP.
- Tests de contrato de tools.
- Tests de permisos y seguridad.
- Regression tests con flows USIM existentes.

Criterio de salida:

- Suite verde en escenarios principales + negativos.

## Fase 9: Documentacion final y handoff

Objetivo: dejar operable por otro equipo/chat IA.

Entregables:

- Guia de arquitectura MCP-USIM.
- Guia de troubleshooting.
- Cookbook de tools (inputs/outputs/errores).
- Checklist de release.

Criterio de salida:

- Cualquier dev puede continuar sin contexto oral adicional.

## 8) Estrategia de testing recomendada

Cobertura minima por tool:

1. Caso feliz.
2. Parametros invalidos.
3. Permiso denegado.
4. Estado ausente/expirado.
5. Error backend USIM.

Cobertura de flujo:

1. `open_screen` -> `send_event` -> `send_event` (estado continuo).
2. `send_event` que causa `redirect`.
3. Usuario sin permisos en opcion de menu.

Cobertura de seguridad:

1. Rate limit excedido.
2. Tool no permitida.
3. Action fuera de allowlist.

## 9) Riesgos y mitigaciones

1. Riesgo: duplicar logica de negocio en MCP.
   Mitigacion: toda regla vive en USIM; MCP solo traduce y resume.

2. Riesgo: payloads muy grandes para LLM.
   Mitigacion: resumen semantico + filtrado de ruido.

3. Riesgo: filtrado de opciones sensibles en navegacion.
   Mitigacion: policy de visibilidad por item + `reason` controlado.

4. Riesgo: desincronizacion de estado.
   Mitigacion: storage obligatorio en toda llamada de evento + tests de continuidad.

## 10) Backlog tecnico inicial (sugerido)

Sprint 1 (MVP tecnico):

1. Crear esqueleto MCP + rutas.
2. Implementar `McpSessionStore`.
3. Implementar `open_screen`.
4. Implementar `send_event`.
5. Tests de continuidad y permiso basicos.

Sprint 2 (calidad y seguridad):

1. Interpreter semantico.
2. Allowlist por actions.
3. Auditoria.
4. Rate limiting.
5. Tests negativos ampliados.

Sprint 3 (producto y documentacion):

1. Compatibilidad multi-proveedor.
2. Tool `list_navigation`.
3. Docs operativas y ejemplos de integracion.

## 11) Prompt listo para otro chat de IA

Usa este bloque tal cual para continuar implementacion en otro chat:

"""
Contexto: Monorepo Laravel 11 + paquete local USIM (`packages/idei/usim`).
USIM ya soporta:
- Headless Mode (`USIM_HEADLESS_MODE=true`)
- `GET /api/ui/{screen}`
- `POST /api/ui-event`
- Estado via `X-USIM-Storage`
- `Screen::getAgentContext(): array` serializado en `agent_context`.

Necesito que implementes un modulo MCP en la app consumidora (NO en el core reusable), con estas metas:
1) Session bridge robusto MCP<->USIM.
2) Tools reales: `open_screen`, `send_event`, `describe_state`.
3) Seguridad minima: allowlist, permisos por screen, rate limit, auditoria.
4) Testing: continuidad de estado (2+ eventos), permisos, errores utiles.
5) Documentacion operativa y ejemplos para ChatGPT/Gemini.

Restricciones:
- No duplicar logica de negocio del backend.
- Mantener compatibilidad con contrato JSON USIM y meta keys reservadas.
- Priorizar cambios en `app/` y `routes/`; tocar `packages/idei/usim/` solo si es estrictamente reusable.

Entregame:
- plan de cambios por archivos,
- implementacion incremental,
- tests,
- y resumen final de decisiones.
"""

## 12) Checklist de terminado

- [ ] `open_screen` funcional contra screens reales.
- [ ] `send_event` funcional con persistencia de `storage`.
- [ ] Permisos y no-autorizado bien manejados.
- [ ] Logs de auditoria activos.
- [ ] Tests MVP verdes.
- [ ] Documentacion de uso y troubleshooting publicada.

---

Este documento esta orientado a ejecucion tecnica real, continuidad entre sesiones de IA y handoff a equipo humano sin perder contexto.
