# Convenciones de tipado PHPStan — Idei\Usim (nivel 9)

## Reglas generales
- No modificar lógica de negocio. Solo agregar/corregir anotaciones de tipo (PHPDoc) o
  castear valores cuando sea seguro, para satisfacer PHPStan nivel 9.
- No usar `@phpstan-ignore-line` ni agregar entradas manuales al baseline salvo que
  se indique explícitamente.
- Preferir el casteo explícito y seguro (`(string)`, `(int)`, etc.) antes que `@phpstan-ignore`.

## Convenciones de arrays
- Configuración de componentes: `array<string, mixed>`
- Listas secuenciales de strings: `list<string>`

## Patrones conocidos para `argument.type`
- `Request::input()` devuelve `mixed`. Si el método/función espera `string`, `int`, etc.,
  castear en el punto de uso: `(string) $request->input('campo')`.
- `config()` devuelve `mixed`. Mismo criterio: castear según el tipo esperado.
- Llamadas a sesión/cache (`session()->get()`, `Cache::get()`) devuelven `mixed`:
  castear o usar el segundo argumento (valor por defecto) para ayudar a la inferencia
  cuando sea razonable.

## config() usado como array
- Si `config()` se usa para obtener un array que luego se accede por offset
  (`config('x.y')['clave']`) o se itera, NO castees cada acceso individual.
  Poné una anotación `@var` precisa en el punto donde se asigna la variable,
  ej. `/** @var array<string, string> $nombre */ $nombre = config('x.y');`.
  Ajustá el tipo del value (`string`, `string|null`, `mixed`, etc.) según lo
  que realmente pueda contener esa clave de config — revisá el archivo de
  config real en vez de asumir.

## Cuándo NO castear automáticamente
- Si el error dice `mixed given`, el origen probablemente es `Request::input()`,
  `config()`, sesión o cache → aplicar el cast directo según las reglas de arriba.
- Si el error dice `string|false given` (o cualquier unión que NO sea `mixed`),
  NO apliques un cast ciego. Mostrame la línea y el contexto, explicá de dónde
  viene ese `false` (o el tipo no-mixed) y proponé el fix sin aplicarlo todavía.
  Suele requerir manejo explícito del caso de fallo, no solo una anotación de tipo.

## Al terminar un lote
- No tocar archivos fuera de los indicados explícitamente en el prompt.
- Devolver un resumen breve de qué se cambió por archivo, sin explicaciones largas.
