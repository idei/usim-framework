/**
 * API Client History Manager
 * Maneja el historial de consultas API almacenadas en localStorage
 */

class ApiHistoryManager {
    constructor() {
        this.storageKey = 'api_client_history';
        this.maxHistoryItems = 50;
        this.currentQueryId = null;
        this.isUpdating = false;

        this.initializeEventListeners();
        this.loadHistoryList();

        // Auto-guardar cuando se modifiquen los campos
        this.setupAutoSave();
    }

    /**
     * Inicializar event listeners
     */
    initializeEventListeners() {
        const historySelect = document.getElementById('queryHistory');
        const deleteButton = document.getElementById('deleteHistoryButton');
        const newQueryButton = document.getElementById('newQueryButton');
        const form = document.getElementById('apiForm');

        // Cargar consulta seleccionada
        historySelect.addEventListener('change', (e) => {
            if (e.target.value) {
                this.loadQuery(e.target.value);
            }
        });

        // Eliminar consulta
        deleteButton.addEventListener('click', () => {
            this.deleteSelectedQuery();
        });

        // Crear nueva consulta
        newQueryButton.addEventListener('click', () => {
            this.createNewQuery();
        });

        // Guardar al enviar formulario
        form.addEventListener('submit', (e) => {
            this.saveCurrentQuery();
        });

        // Actualizar estado del botón eliminar cuando cambie la selección
        historySelect.addEventListener('change', () => {
            this.updateDeleteButtonState();
        });
    }

    /**
     * Configurar auto-guardado cuando se modifiquen los campos
     */
    setupAutoSave() {
        const fieldsToWatch = [
            'queryName',
            'httpMethod',
            'apiUrl',
            'customHeaders',
            'requestBody'
        ];

        fieldsToWatch.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                // Usar diferentes eventos según el tipo de campo
                const events = field.tagName === 'SELECT' ? ['change'] : ['input', 'blur'];

                events.forEach(eventType => {
                    field.addEventListener(eventType, () => {
                        if (!this.isUpdating && this.currentQueryId) {
                            this.updateCurrentQuery();
                            this.highlightFieldChange(field);
                        }
                    });
                });
            }
        });
    }

    /**
     * Resaltar visualmente que un campo ha cambiado
     */
    highlightFieldChange(field) {
        field.classList.add('field-changed');
        setTimeout(() => {
            field.classList.remove('field-changed');
        }, 300);
    }

    /**
     * Obtener todos los queries del localStorage
     */
    getStoredQueries() {
        try {
            const stored = localStorage.getItem(this.storageKey);
            return stored ? JSON.parse(stored) : {};
        } catch (error) {
            console.error('Error al cargar historial:', error);
            return {};
        }
    }

    /**
     * Guardar queries en localStorage
     */
    saveStoredQueries(queries) {
        try {
            localStorage.setItem(this.storageKey, JSON.stringify(queries));
        } catch (error) {
            console.error('Error al guardar historial:', error);
        }
    }

    /**
     * Generar ID único para una consulta
     */
    generateQueryId(name) {
        const timestamp = Date.now();
        const randomId = Math.random().toString(36).substr(2, 9);
        const safeName = name.toLowerCase().replace(/[^a-z0-9]/g, '_').substring(0, 20);
        return `${safeName}_${timestamp}_${randomId}`;
    }

    /**
     * Obtener datos actuales del formulario
     */
    getCurrentFormData() {
        const queryNameEl = document.getElementById('queryName');
        const methodEl = document.getElementById('httpMethod');
        const urlEl = document.getElementById('apiUrl');
        const headersEl = document.getElementById('customHeaders');
        const bodyEl = document.getElementById('requestBody');

        if (!queryNameEl || !methodEl || !urlEl) {
            console.error('❌ Elementos del formulario no encontrados para obtener datos');
            throw new Error('Elementos del formulario requeridos no encontrados');
        }

        return {
            name: queryNameEl.value.trim(),
            method: methodEl.value,
            url: urlEl.value.trim(),
            headers: headersEl ? headersEl.value.trim() : '',
            body: bodyEl ? bodyEl.value.trim() : '',
            timestamp: Date.now()
        };
    }

    /**
     * Cargar datos en el formulario
     */
    loadFormData(data) {
        this.isUpdating = true;

        document.getElementById('queryName').value = data.name || '';
        document.getElementById('httpMethod').value = data.method || 'GET';
        document.getElementById('apiUrl').value = data.url || '';
        document.getElementById('customHeaders').value = data.headers || '';
        document.getElementById('requestBody').value = data.body || '';

        this.isUpdating = false;
    }

    /**
     * Cargar consulta desde el historial
     */
    loadQuery(queryId) {
        const queries = this.getStoredQueries();
        const query = queries[queryId];

        if (!query) {
            console.error('Consulta no encontrada:', queryId);
            return;
        }

        this.currentQueryId = queryId;
        this.loadFormData(query);

        console.log('📄 Consulta cargada:', query.name);
    }

    /**
     * Guardar consulta actual
     */
    saveCurrentQuery() {
        console.log('💾 Iniciando guardado de consulta...');

        try {
            const formData = this.getCurrentFormData();
            console.log('📝 Datos del formulario obtenidos:', {
                name: formData.name,
                method: formData.method,
                url: formData.url ? formData.url.substring(0, 50) + '...' : 'sin URL',
                hasHeaders: !!formData.headers,
                hasBody: !!formData.body
            });

            if (!formData.name) {
                // Si no tiene nombre, generar uno automático
                try {
                    const url = new URL(formData.url);
                    formData.name = `${formData.method} ${url.pathname}`;
                } catch (urlError) {
                    // Si la URL no es válida, usar un nombre genérico
                    formData.name = `${formData.method} Request`;
                }
                console.log('🔄 Nombre auto-generado:', formData.name);
            }

            const queries = this.getStoredQueries();
            const queryId = this.currentQueryId || this.generateQueryId(formData.name);

            console.log('🆔 Query ID:', queryId, this.currentQueryId ? '(existente)' : '(nuevo)');

            queries[queryId] = formData;

            // Limpiar historial si excede el máximo
            this.cleanupHistory(queries);

            this.saveStoredQueries(queries);
            this.currentQueryId = queryId;

            this.loadHistoryList();
            this.selectCurrentQuery();
            this.showSavedIndicator();

            console.log('✅ Consulta guardada exitosamente:', queryId);
            return queryId;
        } catch (error) {
            console.error('❌ Error al guardar consulta:', error);
            // No bloquear la ejecución si hay error en el guardado
            return null;
        }
    }

    /**
     * Actualizar consulta actual cuando se modifiquen campos
     */
    updateCurrentQuery() {
        if (!this.currentQueryId) return;

        const formData = this.getCurrentFormData();
        const queries = this.getStoredQueries();

        if (queries[this.currentQueryId]) {
            // Mantener el nombre original si no se ha cambiado
            if (!formData.name) {
                formData.name = queries[this.currentQueryId].name;
            }

            queries[this.currentQueryId] = {
                ...queries[this.currentQueryId],
                ...formData,
                timestamp: Date.now() // Actualizar timestamp
            };

            this.saveStoredQueries(queries);
            this.loadHistoryList();
            this.selectCurrentQuery();
        }
    }

    /**
     * Limpiar historial si excede el máximo permitido
     */
    cleanupHistory(queries) {
        const queryEntries = Object.entries(queries);

        if (queryEntries.length > this.maxHistoryItems) {
            // Ordenar por timestamp y mantener solo los más recientes
            queryEntries.sort((a, b) => (b[1].timestamp || 0) - (a[1].timestamp || 0));

            // Mantener solo los items más recientes
            const recentQueries = queryEntries.slice(0, this.maxHistoryItems);

            // Crear nuevo objeto con solo los items recientes
            const cleanedQueries = {};
            recentQueries.forEach(([id, query]) => {
                cleanedQueries[id] = query;
            });

            // Actualizar el objeto original
            Object.keys(queries).forEach(key => delete queries[key]);
            Object.assign(queries, cleanedQueries);

            console.log(`🧹 Historial limpiado: mantenidos ${recentQueries.length} de ${queryEntries.length} items`);
        }
    }

    /**
     * Cargar lista de historial en el select
     */
    loadHistoryList() {
        const historySelect = document.getElementById('queryHistory');
        if (!historySelect) return;

        const queries = this.getStoredQueries();

        // Limpiar opciones actuales (excepto la primera)
        while (historySelect.children.length > 1) {
            historySelect.removeChild(historySelect.lastChild);
        }

        // Ordenar consultas por timestamp (más recientes primero)
        const sortedQueries = Object.entries(queries)
            .sort((a, b) => (b[1].timestamp || 0) - (a[1].timestamp || 0));

        // Agregar opciones
        sortedQueries.forEach(([id, query]) => {
            const option = document.createElement('option');
            option.value = id;
            option.textContent = query.name || `${query.method} Request`;
            historySelect.appendChild(option);
        });

        // Actualizar estado del botón eliminar
        this.updateDeleteButtonState();
    }

    /**
     * Seleccionar consulta actual en el dropdown
     */
    selectCurrentQuery() {
        const historySelect = document.getElementById('queryHistory');
        if (historySelect && this.currentQueryId) {
            historySelect.value = this.currentQueryId;
        }
    }

    /**
     * Actualizar estado del botón eliminar
     */
    updateDeleteButtonState() {
        const historySelect = document.getElementById('queryHistory');
        const deleteButton = document.getElementById('deleteHistoryButton');

        deleteButton.disabled = !historySelect.value;
    }

    /**
     * Crear una nueva consulta en blanco
     */
    createNewQuery() {
        // Confirmar si hay cambios sin guardar
        if (this.currentQueryId && this.hasUnsavedChanges()) {
            const confirmCreate = confirm('Tienes cambios sin guardar. ¿Quieres crear una nueva consulta sin guardar los cambios actuales?');
            if (!confirmCreate) {
                return;
            }
        }

        // Limpiar formulario
        this.clearForm();

        // Resetear ID actual
        this.currentQueryId = null;

        // Limpiar selección del historial
        const historySelect = document.getElementById('queryHistory');
        historySelect.value = '';

        // Enfocar en el campo de nombre
        const queryNameInput = document.getElementById('queryName');
        if (queryNameInput) {
            queryNameInput.focus();
        }

        // Actualizar estado del botón eliminar
        this.updateDeleteButtonState();

        // Mostrar feedback visual
        this.showNewQueryIndicator();

        console.log('📝 Nueva consulta creada');
    }

    /**
     * Verificar si hay cambios sin guardar
     */
    hasUnsavedChanges() {
        if (!this.currentQueryId) return false;

        const currentData = this.getCurrentFormData();
        const storedQueries = this.getStoredQueries();
        const storedData = storedQueries[this.currentQueryId];

        if (!storedData) return false;

        // Comparar campos principales
        return (
            currentData.name !== storedData.name ||
            currentData.method !== storedData.method ||
            currentData.url !== storedData.url ||
            currentData.headers !== storedData.headers ||
            currentData.body !== storedData.body
        );
    }

    /**
     * Limpiar todos los campos del formulario
     */
    clearForm() {
        this.isUpdating = true;

        // Limpiar campos principales
        document.getElementById('queryName').value = '';
        document.getElementById('httpMethod').value = 'GET';
        document.getElementById('apiUrl').value = '';
        document.getElementById('customHeaders').value = '';
        document.getElementById('requestBody').value = '';

        // Limpiar archivos adjuntos si existe la función
        if (typeof clearAllFiles === 'function') {
            clearAllFiles();
        }

        // Limpiar errores de validación
        this.clearValidationErrors();

        // Ocultar panel de respuesta si está visible
        const responseContainer = document.getElementById('responseContainer');
        if (responseContainer) {
            responseContainer.style.display = 'none';
        }

        this.isUpdating = false;
    }

    /**
     * Limpiar errores de validación
     */
    clearValidationErrors() {
        const errorElements = document.querySelectorAll('.error-message');
        errorElements.forEach(element => {
            element.style.display = 'none';
        });

        const invalidFields = document.querySelectorAll('.form-control.invalid');
        invalidFields.forEach(field => {
            field.classList.remove('invalid');
        });
    }

    /**
     * Mostrar indicador de nueva consulta
     */
    showNewQueryIndicator() {
        const newQueryButton = document.getElementById('newQueryButton');
        if (!newQueryButton) return;

        // Efecto visual temporal
        newQueryButton.style.background = '#27ae60';
        newQueryButton.style.transform = 'scale(1.1)';

        setTimeout(() => {
            newQueryButton.style.background = '';
            newQueryButton.style.transform = '';
        }, 300);

        // Mostrar mensaje temporal en el nombre de consulta
        const queryNameInput = document.getElementById('queryName');
        if (queryNameInput) {
            const originalPlaceholder = queryNameInput.placeholder;
            queryNameInput.placeholder = '✨ Nueva consulta - Dale un nombre...';

            setTimeout(() => {
                queryNameInput.placeholder = originalPlaceholder;
            }, 3000);
        }
    }

    /**
     * Mostrar indicador de guardado exitoso
     */
    showSavedIndicator() {
        const queryNameInput = document.getElementById('queryName');
        if (!queryNameInput) return;

        // Agregar clase de guardado exitoso
        queryNameInput.classList.add('saved-indicator');

        setTimeout(() => {
            queryNameInput.classList.remove('saved-indicator');
        }, 1000);

        console.log('💾 Consulta guardada en historial');
    }
}

// Auto-inicialización cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Inicializando API History Manager...');

    // Verificar que los elementos necesarios existan
    const historySelect = document.getElementById('queryHistory');
    const apiForm = document.getElementById('apiForm');
    const queryNameInput = document.getElementById('queryName');

    console.log('📋 Verificación de elementos:', {
        historySelect: !!historySelect,
        apiForm: !!apiForm,
        queryNameInput: !!queryNameInput
    });

    if (historySelect && apiForm) {
        try {
            // Crear instancia global del manager de historial
            window.apiHistoryManager = new ApiHistoryManager();
            console.log('✅ API History Manager inicializado correctamente');

            // Verificar que la instancia esté disponible
            if (window.apiHistoryManager) {
                console.log('🎯 Instancia del historial verificada y disponible globalmente');

                // Función de debug para probar manualmente
                window.debugHistory = function() {
                    console.log('🔍 Estado del historial:');
                    console.log('- Instancia:', !!window.apiHistoryManager);
                    console.log('- Query actual:', window.apiHistoryManager.currentQueryId);
                    console.log('- Consultas guardadas:', window.apiHistoryManager.getStoredQueries());

                    // Test de guardado
                    try {
                        const testId = window.apiHistoryManager.saveCurrentQuery();
                        console.log('✅ Test de guardado exitoso:', testId);
                    } catch (error) {
                        console.error('❌ Error en test de guardado:', error);
                    }
                };

                console.log('🔧 Función window.debugHistory() disponible para testing');
            }
        } catch (error) {
            console.error('❌ Error al inicializar el historial:', error);
        }
    } else {
        console.warn('⚠️ No se pudo inicializar el historial - elementos no encontrados:', {
            historySelect: !!historySelect,
            apiForm: !!apiForm
        });
    }
});
