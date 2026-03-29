# Instrucciones para Copilot en USIM Framework

## Contexto del proyecto

- Este repositorio implementa USIM (UI Services Implementation Model), un framework backend-driven/server-driven UI sobre Laravel.
- El stack principal es Laravel 11 con PHP 8.3+.
- La UI se define en PHP; el frontend JavaScript actua como renderizador agnostico de instrucciones enviadas por el backend.
- Prioriza soluciones que mantengan la logica, validacion y estado en el backend.

## Como trabajar en este repositorio

- Para cambios de UI, prioriza pantallas y servicios PHP sobre soluciones con React, Vue o logica frontend ad hoc.
- Usa los patrones propios de USIM: `AbstractUIService`, `UIBuilder`, builders de componentes, contenedores y handlers de eventos con convencion `on<ActionName>`.
- Considera que el estado de pantalla es server-side y que las propiedades `store_*` forman parte del estado persistido entre requests.
- Mantiene cambios pequenos, coherentes con Laravel y con la arquitectura backend-driven del proyecto.
- No dupliques validaciones entre frontend y backend salvo que el usuario lo pida de forma explicita.

## Estructura importante

- La aplicacion Laravel vive en `app/`, `routes/`, `config/`, `resources/` y `tests/`.
- El framework USIM tambien se desarrolla como paquete en `packages/idei/usim/`; si un cambio afecta al framework, revisa primero esa carpeta antes de proponer codigo en la app consumidora.
- Las pantallas USIM suelen vivir en `app/UI/Screens/` y se descubren con el flujo del framework.
- Los servicios de autenticacion y dominio estan organizados en `app/Services/`.

## Convenciones tecnicas

- Prefiere implementaciones en PHP siguiendo convenciones de Laravel y del framework USIM.
- Cuando trabajes con pantallas USIM, piensa en eventos, diff incremental, componentes estables e IDs deterministas.
- Para ejemplos, cambios o refactors, preserva la separacion entre framework reusable y codigo especifico de la aplicacion.
- Para levantar el proyecto, ten presente que este repo usa RoadRunner mediante `./start.sh`; no asumas que `php artisan serve` es el flujo principal.

## Testing y documentacion

- Si generas o mantienes tests de UI/Screen, sigue la guia en `tests/SCREEN_TESTING_GUIDE.md` y usa `tests/prompt.md` como referencia de formato.
- Si el cambio afecta al paquete USIM, usa tambien como contexto `packages/idei/usim/README.md`.
- Usa `README.md` y la documentacion dentro de `docs/` como referencia funcional antes de proponer arquitectura nueva.

## Estilo de ayuda esperado

- Responde con foco practico y orientado a cambios reales en el codigo.
- Explica tradeoffs cuando una solucion pueda tocar framework y aplicacion al mismo tiempo.
- Si una solicitud contradice la arquitectura backend-driven de USIM, senalalo y propone una alternativa alineada con el proyecto.

## General
Al final de cada respuesta dime "Listo Emilio" para que sepa que terminaste de responder.
