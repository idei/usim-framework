<?php

namespace Idei\Usim\Support;

/**
 * UI Differ - Compara dos estructuras UI y retorna solo cambios
 *
 * Protocolo de cambios:
 * - AGREGAR: { id: { type, text, parent, ... } } ← tiene parent
 * - ACTUALIZAR: { id: { text: "nuevo" } } ← solo propiedades que cambiaron
 * - ELIMINAR: { id: { parent: null } } ← parent = null
 */
class UIDiffer
{
    /**
     * Compara dos estructuras UI y retorna solo los cambios
     *
        * @param array<int|string, array<string, mixed>> $oldUI UI anterior (JSON)
        * @param array<int|string, array<string, mixed>> $newUI UI nueva (JSON)
        * @return array<int|string, array<string, mixed>> Cambios detectados
     */
    public static function compare(array $oldUI, array $newUI): array
    {
        $changes = [];

        // Indexar componentes por ID
        $oldComponents = self::flattenComponents($oldUI);
        $newComponents = self::flattenComponents($newUI);

        // Detectar AGREGAR o ACTUALIZAR
        foreach ($newComponents as $id => $newComp) {
            if (!isset($oldComponents[$id])) {
                // AGREGAR: Componente nuevo (incluye parent)
                $changes[$id] = $newComp;
            } else {
                $oldParent = $oldComponents[$id]['parent'] ?? null;
                $newParent = $newComp['parent'] ?? null;

                if ($oldParent === null && $newParent !== null) {
                    // RE-ADJUNTAR: el componente estaba desvinculado (parent=null).
                    // El frontend lo eliminó del DOM y del registry, por lo que
                    // necesita el config COMPLETO para recrearlo correctamente
                    // (incluyendo anchos de columna, estilos, etc.).
                    $changes[$id] = $newComp;
                } else {
                    // ACTUALIZAR: Solo propiedades que cambiaron
                    $diff = self::diffProperties($oldComponents[$id], $newComp);
                    if (!empty($diff)) {
                        $changes[$id] = $diff;
                    }
                }
            }
        }

        // Detectar ELIMINAR
        foreach ($oldComponents as $id => $oldComp) {
            if (!isset($newComponents[$id])) {
                // ELIMINAR: parent = null
                $changes[$id] = ['parent' => null];
            }
        }

        return $changes;
    }

    /**
     * Compara dos componentes y retorna solo propiedades que cambiaron
     *
        * @param array<string, mixed> $old Componente anterior
        * @param array<string, mixed> $new Componente nuevo
        * @return array<string, mixed> Propiedades que cambiaron
     */
    private static function diffProperties(array $old, array $new): array
    {
        $changes = [];

        // Comparar todas las propiedades del nuevo componente
        foreach ($new as $key => $value) {
            // Ignorar 'type' y '_order' (no son cambios de contenido)
            if ($key === 'type' || $key === '_order') {
                continue;
            }

            // Si la propiedad no existía o cambió
            if (!isset($old[$key]) || $old[$key] !== $value) {
                $changes[$key] = $value;
            }
        }

        // Detectar propiedades eliminadas (ahora son null)
        foreach ($old as $key => $value) {
            if (!isset($new[$key]) && $key !== 'type' && $key !== 'parent' && $key !== '_order') {
                $changes[$key] = null;
            }
        }

        return $changes;
    }

    /**
     * Aplana la estructura UI a un array indexado por ID
     *
        * @param array<int|string, array<string, mixed>> $ui Estructura UI en formato JSON
        * @return array<int|string, array<string, mixed>> Array indexado por ID
     */
    private static function flattenComponents(array $ui): array
    {
        $flat = [];

        foreach ($ui as $id => $component) {
            // Solo procesar entradas con ID numérico
            if (is_numeric($id)) {
                $flat[$id] = $component;
            }
        }

        return $flat;
    }
}
