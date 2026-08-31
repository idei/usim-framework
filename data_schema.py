import re
import json
import uuid
import os
import sys
import subprocess
from datetime import datetime, timezone


def split_table_elements(body):
    """
    Splits the table definition body by commas that are outside parentheses.
    """
    elements = []
    current = []
    depth = 0
    in_quote = None

    for char in body:
        if in_quote:
            if char == in_quote:
                in_quote = None
            current.append(char)
        elif char in ("'", '"', '`'):
            in_quote = char
            current.append(char)
        elif char == '(':
            depth += 1
            current.append(char)
        elif char == ')':
            depth = max(0, depth - 1)
            current.append(char)
        elif char == ',' and depth == 0:
            elem = "".join(current).strip()
            if elem:
                elements.append(elem)
            current = []
        else:
            current.append(char)

    elem = "".join(current).strip()
    if elem:
        elements.append(elem)
    return elements


def sql_to_erd_json(input_sql_path, output_erd_path):
    tablas_a_ignorar = {
        "migrations", "cache", "cache_locks", "failed_jobs",
        "jobs", "job_batches", "password_reset_tokens", "sessions"
    }

    # Native type mapping
    type_ids = {
        'integer': 15, 'tinyint': 15, 'smallint': 15, 'mediumint': 15, 'bigint': 15, 'int': 15,
        'varchar': 312, 'char': 311,
        'text': 325, 'longtext': 325, 'mediumtext': 325,
        'datetime': 112, 'date': 101, 'timestamp': 112, 'time': 112,
        'blob': 363, 'real': 33, 'float': 33, 'double': 33,
        'numeric': 36, 'decimal': 45, 'boolean': 6
    }

    with open(input_sql_path, 'r', encoding='utf-8') as f:
        sql_content = f.read()

    # Remove SQL comments
    sql_content = re.sub(r'--.*?$', '', sql_content, flags=re.MULTILINE)
    sql_content = re.sub(r'/\*.*?\*/', '', sql_content, flags=re.DOTALL)
    # Remove INSERT and INDEX statements
    sql_content = re.sub(r'INSERT\s+INTO\s+.*?;', '', sql_content, flags=re.DOTALL | re.IGNORECASE)
    sql_content = re.sub(r'CREATE\s+(?:UNIQUE\s+)?INDEX\s+.*?;', '', sql_content, flags=re.DOTALL | re.IGNORECASE)

    table_pattern = r'CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?["`]?(\w+)["`]?\s*\((.*?)\)\s*;'
    bloques_tabla = re.findall(table_pattern, sql_content, flags=re.DOTALL | re.IGNORECASE)

    tablas_registradas = {}
    erd_output = {
        "documentName": "USIM main tables",
        "lastUpdatedAt": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.%f")[:-3] + "Z",
        "tableViewModels": [],
        "columnGroupModels": [],
        "columnModels": [],
        "columnShareModels": [],
        "relationViewModels": [],
        "foregroundMemos": [],
        "backgroundMemos": [],
        "erdSettingModel": {
            "displayStyle": { "styleName": "Both" },
            "exportDdlSetting": {
                "fileName": "USIM main tables",
                "withTable": True,
                "withIndex": True,
                "withForeignKey": True,
                "withSchema": True,
                "withComment": True,
                "commentStyle": "logical_name",
                "commentSeparator": " : "
            },
            "showRelationNames": True
        },
        "databaseSetting": {
            "databaseType": "sqlite",
            "columnTypes": [
                {"id": 15, "name": "integer", "description": "整数値。INTEGERアフィニティ。", "baseQuery": "INTEGER", "category": "integer"},
                {"id": 33, "name": "real", "description": "浮動小数点数値。REALアフィニティ。", "baseQuery": "REAL", "category": "decimal"},
                {"id": 325, "name": "text", "description": "文字列。TEXTアフィニティ。", "baseQuery": "TEXT", "category": "text"},
                {"id": 363, "name": "blob", "description": "バイナリデータ。BLOBアフィニティ。", "baseQuery": "BLOB", "category": "bit"},
                {"id": 36, "name": "numeric", "description": "数値。NUMERICアフィニティ。", "baseQuery": "NUMERIC", "category": "decimal"},
                {"id": 6, "name": "boolean", "description": "論理値。", "baseQuery": "BOOLEAN", "category": "bit"},
                {"id": 311, "name": "char (n)", "description": "固定長文字列。", "baseQuery": "CHAR[[PARAM]]", "category": "text", "withPrecision": True},
                {"id": 312, "name": "varchar (n)", "description": "可変長文字列。", "baseQuery": "VARCHAR[[PARAM]]", "category": "text", "withPrecision": True},
                {"id": 101, "name": "date", "description": "日付の実用エイリアス。", "baseQuery": "DATE", "category": "timestamp"},
                {"id": 112, "name": "datetime", "description": "日時の実用エイリアス。", "baseQuery": "DATETIME", "category": "timestamp"},
                {"id": 45, "name": "decimal (p, s)", "description": "固定小数点数。", "baseQuery": "DECIMAL[[PARAM]]", "category": "decimal", "withPrecision": True, "withScale": True}
            ],
            "version": 20260504
        }
    }

    share_cache = {}
    grid_x, grid_y = 50, 50

    # --- PASADA 1: Tablas y Columnas ---
    for nombre_tabla, cuerpo_tabla in bloques_tabla:
        nombre_tabla_clean = nombre_tabla.strip('"`[]\'')
        nombre_tabla_lower = nombre_tabla_clean.lower()
        if nombre_tabla_lower in tablas_a_ignorar:
            continue

        table_uuid = str(uuid.uuid4())
        tablas_registradas[nombre_tabla_lower] = {
            "uuid": table_uuid,
            "columns": {}
        }

        elementos = split_table_elements(cuerpo_tabla)

        # Pre-detect table-level primary keys
        table_pks = set()
        for elem in elementos:
            pk_match = re.match(r'^\s*primary\s+key\s*\((.*?)\)', elem, re.IGNORECASE)
            if pk_match:
                pk_cols = [c.strip().strip('"`[]\'').lower() for c in pk_match.group(1).split(',')]
                table_pks.update(pk_cols)

        column_ids_for_table = []

        for linea in elementos:
            # Check if this line is a table-level constraint
            linea_lower = linea.lower().strip()
            if (linea_lower.startswith("foreign key") or
                linea_lower.startswith("primary key") or
                linea_lower.startswith("constraint") or
                linea_lower.startswith("unique") or
                linea_lower.startswith("check")):
                continue

            partes = linea.split()
            if not partes:
                continue

            nombre_columna = partes[0].strip('"`[]\'')
            nombre_columna_lower = nombre_columna.lower()
            tipo_columna = partes[1].lower() if len(partes) > 1 else 'integer'
            tipo_limpio = re.sub(r'\(.*?\)', '', tipo_columna).strip('"`[]\'')

            type_id_numeric = 15
            for k, v in type_ids.items():
                if k == tipo_limpio or k in tipo_limpio:
                    type_id_numeric = v
                    break

            cache_key = (nombre_columna_lower, type_id_numeric)
            if cache_key not in share_cache:
                share_uuid = str(uuid.uuid4())
                share_cache[cache_key] = share_uuid
                erd_output["columnShareModels"].append({
                    "columnShareModelId": share_uuid,
                    "physicalName": nombre_columna,
                    "logicalName": nombre_columna,
                    "columnTypeId": type_id_numeric,
                    "createdAt": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.%f")[:-3] + "Z"
                })

            col_uuid = str(uuid.uuid4())
            tablas_registradas[nombre_tabla_lower]["columns"][nombre_columna_lower] = col_uuid
            column_ids_for_table.append(col_uuid)

            is_pk = ("primary key" in linea_lower) or (nombre_columna_lower in table_pks)
            is_nn = ("not null" in linea_lower) or is_pk

            col_model = {
                "columnModelId": col_uuid,
                "columnShareModelId": share_cache[cache_key]
            }
            if is_pk:
                col_model["primaryKey"] = True
            if is_nn:
                col_model["notNull"] = True

            def_match = re.search(r"default\s+('[\s\S]*?'|\S+)", linea, re.IGNORECASE)
            if def_match:
                col_model["defaultValue"] = def_match.group(1).rstrip(',')

            erd_output["columnModels"].append(col_model)

        table_view = {
            "tableModel": {
                "tableModelId": table_uuid,
                "physicalName": nombre_tabla_clean,
                "logicalName": nombre_tabla_clean,
                "columnModelIds": column_ids_for_table
            },
            "top": grid_y,
            "left": grid_x,
            "headerBackgroundColor": { "red": 227, "green": 242, "blue": 253 },
            "headerForegroundColor": { "red": 0, "green": 0, "blue": 0 },
            "createdAt": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.%f")[:-3] + "Z"
        }
        erd_output["tableViewModels"].append(table_view)

        grid_x += 700
        if grid_x > 2200:
            grid_x = 50
            grid_y += 500

    # --- PASADA 2: Claves Foráneas ---
    for nombre_tabla, cuerpo_tabla in bloques_tabla:
        nombre_tabla_clean = nombre_tabla.strip('"`[]\'')
        nombre_tabla_lower = nombre_tabla_clean.lower()
        if nombre_tabla_lower in tablas_a_ignorar:
            continue

        elementos = split_table_elements(cuerpo_tabla)
        for linea in elementos:
            col_origen = None
            tabla_destino = None
            col_destino = None

            # Table-level FK: e.g. foreign key("permission_id") references "permissions"("id")
            fk_match = re.search(
                r'foreign\s+key\s*\((.*?)\)\s*references\s+["`]?(\w+)["`]?\s*\((.*?)\)',
                linea,
                re.IGNORECASE
            )
            if fk_match:
                col_origen = fk_match.group(1).strip().strip('"`[]\'').lower()
                tabla_destino = fk_match.group(2).strip().strip('"`[]\'').lower()
                col_destino = fk_match.group(3).strip().strip('"`[]\'').lower()
            else:
                # Inline FK: e.g. user_id integer references users(id)
                inline_match = re.search(
                    r'^["`]?(\w+)["`]?\s+.*?references\s+["`]?(\w+)["`]?\s*\((.*?)\)',
                    linea,
                    re.IGNORECASE
                )
                if inline_match:
                    col_origen = inline_match.group(1).strip().strip('"`[]\'').lower()
                    tabla_destino = inline_match.group(2).strip().strip('"`[]\'').lower()
                    col_destino = inline_match.group(3).strip().strip('"`[]\'').lower()

            if col_origen and tabla_destino and col_destino:
                if nombre_tabla_lower in tablas_registradas and tabla_destino in tablas_registradas:
                    map_origen = tablas_registradas[nombre_tabla_lower]["columns"]
                    map_destino = tablas_registradas[tabla_destino]["columns"]

                    if col_origen in map_origen and col_destino in map_destino:
                        rel_uuid = str(uuid.uuid4())

                        # Parse ON DELETE and ON UPDATE actions
                        on_delete = "RESTRICT"
                        del_match = re.search(r'on\s+delete\s+(cascade|restrict|set\s+null|no\s+action)', linea, re.IGNORECASE)
                        if del_match:
                            on_delete = del_match.group(1).upper()

                        on_update = "RESTRICT"
                        upd_match = re.search(r'on\s+update\s+(cascade|restrict|set\s+null|no\s+action)', linea, re.IGNORECASE)
                        if upd_match:
                            on_update = upd_match.group(1).upper()

                        relation_node = {
                            "relationModel": {
                                "relationModelId": rel_uuid,
                                "relationName": f"fk_{nombre_tabla_clean}_{tabla_destino}",
                                "parentTableModelId": tablas_registradas[tabla_destino]["uuid"],
                                "parentCardinality": "1",
                                "childTableModelId": tablas_registradas[nombre_tabla_lower]["uuid"],
                                "childCardinality": "0..N",
                                "relationPairs": [
                                    {
                                        "parentColumnModelId": map_destino[col_destino],
                                        "childColumnModelId": map_origen[col_origen]
                                    }
                                ],
                                "onUpdateAction": on_update,
                                "onDeleteAction": on_delete
                            },
                            "lineViewModel": {
                                "strokeWidth": 1,
                                "edges": [],
                                "orthogonalLines": [],
                                "color": { "red": 0, "green": 0, "blue": 0 }
                            },
                            "labelViewModel": {
                                "label": tabla_destino,
                                "position": {
                                    "segment": 0,
                                    "fraction": 0.5,
                                    "offsetX": 0,
                                    "offsetY": -20
                                },
                                "color": { "red": 0, "green": 0, "blue": 0 },
                                "style": { "bold": False, "italic": False, "strikethrough": False, "fontSize": 10 }
                            },
                            "createdAt": datetime.now(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.%f")[:-3] + "Z"
                        }
                        erd_output["relationViewModels"].append(relation_node)

    # Ensure output directory exists
    output_dir = os.path.dirname(output_erd_path)
    if output_dir and not os.path.exists(output_dir):
        os.makedirs(output_dir, exist_ok=True)

    with open(output_erd_path, 'w', encoding='utf-8') as f:
        json.dump(erd_output, f, indent=4, ensure_ascii=False)

    print(f"¡Estructura espejo compilada! Mapeado en: {output_erd_path}")


def dump_schema_with_artisan(project_root, relative_sql_path):
    """
    Ejecuta 'php artisan schema:dump --path=...' en el directorio raíz del proyecto.
    """
    cmd = ["php", "artisan", "schema:dump", f"--path={relative_sql_path}"]
    print(f"Ejecutando: {' '.join(cmd)}...")
    result = subprocess.run(cmd, cwd=project_root, capture_output=True, text=True)
    if result.returncode != 0:
        error_msg = result.stderr.strip() or result.stdout.strip()
        print(f"Error al ejecutar php artisan schema:dump:\n{error_msg}", file=sys.stderr)
        raise RuntimeError(f"Fallo en php artisan schema:dump: {error_msg}")
    if result.stdout.strip():
        print(result.stdout.strip())


if __name__ == '__main__':
    base_dir = os.path.dirname(os.path.abspath(__file__))

    # Rutas por defecto en docs/
    relative_sql = 'docs/schema.sql'
    input_sql = sys.argv[1] if len(sys.argv) > 1 else os.path.join(base_dir, relative_sql)
    output_erd = sys.argv[2] if len(sys.argv) > 2 else os.path.join(base_dir, 'docs/schema.erd')

    # Asegurar que el directorio de salida exista
    os.makedirs(os.path.dirname(output_erd), exist_ok=True)

    # 1. Generar dump SQL invocando php artisan (si se usa la ruta por defecto o no se pasaron args personalizados)
    if len(sys.argv) <= 1:
        dump_schema_with_artisan(base_dir, relative_sql)

    try:
        # 2. Parsear el archivo SQL y compilar el JSON para schema.erd
        sql_to_erd_json(input_sql, output_erd)
    finally:
        # 3. Eliminar el archivo SQL de entrada
        if os.path.exists(input_sql):
            os.remove(input_sql)
            print(f"Archivo temporal eliminado: {input_sql}")
