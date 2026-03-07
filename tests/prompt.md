# Prompt Template: Generar Test de Screen (USIM)

Copia y pega este prompt en un chat con capacidad de agente.
Completa los campos entre `<>` antes de enviarlo.

---

Quiero que generes un test de screen para Laravel + Pest + USIM en este repositorio.

## Contexto
- Screen objetivo: `<FQCN_SCREEN>`
- Archivo destino del test: `<RUTA_TEST_PHP>`
- Tipo de test: `<carga_inicial | flujo_evento | modal | auth | notificacion | reset_password | otro>`
- Si requiere auth: `<si/no>`
- Si requiere rol: `<admin/user/ninguno>`
- Debe usar `uiScenario`: `<si/no>` (por defecto: si)

## Comportamiento esperado
1. `<comportamiento_1>`
2. `<comportamiento_2>`
3. `<comportamiento_3>`

## Contratos que quiero validar
- Componentes esperados: `<lista_componentes>`
- Acciones esperadas: `<lista_acciones>`
- Respuesta esperada: `<toast|redirect|abort|modal|update_modal|clear_uploaders|otro>`
- Validaciones de dominio extra (DB/notificacion/auth): `<detalle>`

## Reglas de implementacion
- Usa el patron del repo: `uiScenario(...)->component(...)->expect(...)`.
- Evita parseo raw del payload salvo que sea estrictamente necesario.
- Si hay notificaciones, usa `Notification::fake()`.
- Si hay links en email, extraelos desde `toMail(...)->viewData`.
- Si aplica, termina cada test con `$ui->assertNoIssues();`.
- No agregues codigo no solicitado.
- Ejecuta los tests afectados y corrige si algo falla.

## Salida esperada
1. Crea/edita el archivo de test con implementacion completa.
2. Ejecuta el/los comandos de test necesarios.
3. Reporta:
   - archivos modificados
   - resumen de asserts clave
   - resultado de ejecucion (`passed/failed`)

---

## Ejemplo rapido (opcional)

Screen objetivo: `App\\UI\\Screens\\Auth\\Login`
Archivo destino del test: `tests/Feature/LoginScreenTest.php`
Tipo de test: `flujo_evento`
Auth: `no`
Comportamiento esperado:
1. carga inputs de email/password
2. submit_login redirige en credenciales validas
3. credenciales invalidas muestran toast error
