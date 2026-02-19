// Variables globales
let currentResponse = null;

// Manejo de errores global para debugging
window.addEventListener('error', function(event) {
    console.error('🚨 Error JavaScript capturado:', {
        message: event.message,
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
        error: event.error
    });
});

// Manejo de promesas rechazadas
window.addEventListener('unhandledrejection', function(event) {
    console.error('🚨 Promesa rechazada no manejada:', event.reason);
});

// Función de inicialización
function initializeApiClient() {
    console.log('🚀 Inicializando API Client...');

    // Referencias a elementos del DOM
    const apiForm = document.getElementById('apiForm');
    const httpMethodSelect = document.getElementById('httpMethod');
    const apiUrlInput = document.getElementById('apiUrl');
    const customHeadersInput = document.getElementById('customHeaders');
    const requestBodyInput = document.getElementById('requestBody');
    const executeButton = document.getElementById('executeButton');
    const buttonText = document.querySelector('.button-text');
    const loadingSpinner = document.querySelector('.loading');

    const responseContainer = document.getElementById('responseContainer');
    const responseContent = document.getElementById('responseContent');
    const statusBadge = document.getElementById('statusBadge');
    const responseTime = document.getElementById('responseTime');
    const responseHeaders = document.getElementById('responseHeaders');
    const responseBody = document.getElementById('responseBody');
    const copyButton = document.getElementById('copyButton');

    // Verificar elementos críticos
    const criticalElements = {
        apiForm,
        httpMethodSelect,
        apiUrlInput,
        executeButton
    };

    for (const [name, element] of Object.entries(criticalElements)) {
        if (!element) {
            console.error(`❌ Elemento crítico no encontrado: ${name}`);
            return;
        }
    }

    console.log('✅ Todos los elementos DOM encontrados');

    // Hacer las referencias globales para que estén disponibles en otras funciones
    window.apiClientElements = {
        apiForm,
        httpMethodSelect,
        apiUrlInput,
        customHeadersInput,
        requestBodyInput,
        executeButton,
        buttonText,
        loadingSpinner,
        responseContainer,
        responseContent,
        statusBadge,
        responseTime,
        responseHeaders,
        responseBody,
        copyButton
    };

    // Event listeners
    if (apiForm) {
        apiForm.addEventListener('submit', handleFormSubmit);
        console.log('📝 Event listener del formulario agregado');
    }

    if (copyButton) {
        copyButton.addEventListener('click', copyResponseToClipboard);
        console.log('📋 Event listener del botón copiar agregado');
    }

    // Validación en tiempo real
    if (apiUrlInput) {
        apiUrlInput.addEventListener('blur', validateUrl);
    }
    if (customHeadersInput) {
        customHeadersInput.addEventListener('blur', () => validateJSON(customHeadersInput, 'headersError'));
    }
    if (requestBodyInput) {
        requestBodyInput.addEventListener('blur', () => validateJSON(requestBodyInput, 'bodyError'));
    }

    console.log('🎉 API Client inicializado correctamente');
}

// Inicializar cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', initializeApiClient);

/**
 * Maneja el envío del formulario
 */
async function handleFormSubmit(event) {
    event.preventDefault();

    console.log('🔄 Iniciando envío del formulario...');

    // Validar formulario antes de enviar
    if (!validateForm()) {
        console.log('❌ Validación del formulario falló');
        return;
    }

    console.log('✅ Formulario validado correctamente');

    // Guardar en historial antes de enviar
    try {
        if (window.apiHistoryManager) {
            window.apiHistoryManager.saveCurrentQuery();
            console.log('💾 Consulta guardada en historial');
        } else {
            console.warn('⚠️ Manager de historial no disponible aún');
        }
    } catch (historyError) {
        console.warn('Error al guardar en historial (no afecta la ejecución):', historyError);
    }

    // Mostrar estado de carga
    setLoadingState(true);
    console.log('⏳ Estado de carga activado');

    try {
        const startTime = performance.now();

        // Preparar la petición
        const requestOptions = await prepareRequest();
        console.log('📦 Opciones de petición preparadas:', requestOptions);

        // Realizar la petición HTTP
        const apiUrlInput = window.apiClientElements?.apiUrlInput;
        if (!apiUrlInput) {
            throw new Error('Elemento URL no encontrado');
        }

        let url = apiUrlInput.value.trim();
        
        // Si la URL no tiene protocolo, agregar la URL base actual
        if (!url.startsWith('http://') && !url.startsWith('https://')) {
            const baseUrl = `${window.location.protocol}//${window.location.host}/`;
            url = baseUrl + url.replace(/^\/+/, ''); // Eliminar barras iniciales duplicadas
            console.log('🌐 URL base agregada automáticamente:', url);
        }
        
        console.log('🌐 Enviando petición a:', url);

        const response = await fetch(url, requestOptions);

        const endTime = performance.now();
        const duration = Math.round(endTime - startTime);

        console.log('📡 Respuesta recibida en', duration, 'ms');

        // Procesar la respuesta
        await handleResponse(response, duration);

    } catch (error) {
        console.error('❌ Error en la petición:', error);
        handleError(error);
    } finally {
        setLoadingState(false);
        console.log('✅ Estado de carga desactivado');
    }
}

/**
 * Valida todo el formulario
 */
function validateForm() {
    let isValid = true;

    // Validar URL
    if (!validateUrl()) {
        isValid = false;
    }

    // Obtener referencias a los elementos
    const customHeadersInput = window.apiClientElements?.customHeadersInput;
    const requestBodyInput = window.apiClientElements?.requestBodyInput;

    // Validar headers JSON
    if (customHeadersInput && !validateJSON(customHeadersInput, 'headersError')) {
        isValid = false;
    }

    // Validar body JSON
    if (requestBodyInput && !validateJSON(requestBodyInput, 'bodyError')) {
        isValid = false;
    }

    return isValid;
}

/**
 * Valida la URL ingresada
 */
function validateUrl() {
    const apiUrlInput = document.getElementById('apiUrl');
    if (!apiUrlInput) return false;

    const url = apiUrlInput.value.trim();
    const urlError = document.getElementById('urlError');

    if (!url) {
        showFieldError(apiUrlInput, urlError, 'La URL es requerida');
        return false;
    }

    // Si la URL tiene protocolo, validar como URL completa
    if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
            new URL(url);
            hideFieldError(apiUrlInput, urlError);
            return true;
        } catch (error) {
            showFieldError(apiUrlInput, urlError, 'Por favor ingresa una URL válida');
            return false;
        }
    } else {
        // Para URLs relativas, validar que tengan un formato básico válido
        // Permitir rutas como: api/ping, /api/users, users/123, etc.
        const relativeUrlPattern = /^[a-zA-Z0-9\/_\-\.]+$/;
        if (relativeUrlPattern.test(url)) {
            hideFieldError(apiUrlInput, urlError);
            return true;
        } else {
            showFieldError(apiUrlInput, urlError, 'Por favor ingresa una URL válida');
            return false;
        }
    }
}

/**
 * Valida formato JSON en un campo
 */
function validateJSON(input, errorId) {
    const value = input.value.trim();
    const errorElement = document.getElementById(errorId);

    if (!value) {
        hideFieldError(input, errorElement);
        return true;
    }

    try {
        JSON.parse(value);
        hideFieldError(input, errorElement);
        return true;
    } catch {
        showFieldError(input, errorElement, 'Formato JSON inválido');
        return false;
    }
}

/**
 * Muestra error en un campo
 */
function showFieldError(input, errorElement, message) {
    input.classList.add('form-error');
    errorElement.textContent = message;
    errorElement.classList.add('show-error');
}

/**
 * Oculta error en un campo
 */
function hideFieldError(input, errorElement) {
    input.classList.remove('form-error');
    errorElement.classList.remove('show-error');
}

/**
 * Prepara las opciones de la petición HTTP
 */
async function prepareRequest() {
    const httpMethodSelect = window.apiClientElements?.httpMethodSelect;
    const customHeadersInput = window.apiClientElements?.customHeadersInput;
    const requestBodyInput = window.apiClientElements?.requestBodyInput;

    if (!httpMethodSelect) {
        throw new Error('Elementos del formulario no encontrados');
    }

    const method = httpMethodSelect.value;
    const headers = {};

    // Parsear headers personalizados
    const customHeaders = customHeadersInput?.value.trim() || '';
    if (customHeaders) {
        try {
            Object.assign(headers, JSON.parse(customHeaders));
        } catch (error) {
            console.warn('Error parseando headers personalizados:', error);
        }
    }

    // Configurar opciones básicas
    const options = {
        method: method,
        headers: headers,
        mode: 'cors',
        credentials: 'same-origin'
    };

    // Verificar si hay archivos seleccionados
    const hasFiles = typeof hasSelectedFiles === 'function' && hasSelectedFiles();

    // Agregar body para métodos que lo permiten
    if (['POST', 'PUT', 'PATCH'].includes(method)) {
        if (hasFiles) {
            // Si hay archivos, usar FormData
            console.log('📁 Preparando petición con archivos adjuntos');

            const body = requestBodyInput?.value.trim() || '';
            let baseData = {};

            // Si hay JSON en el body, parsearlo e incluirlo en FormData
            if (body) {
                try {
                    baseData = JSON.parse(body);
                } catch (error) {
                    console.warn('Error parseando JSON del body, se enviará como string:', error);
                    baseData = { json_data: body };
                }
            }

            // Preparar FormData con archivos
            const formData = typeof prepareFormDataWithFiles === 'function'
                ? prepareFormDataWithFiles(baseData)
                : null;

            if (formData) {
                options.body = formData;
                // NO establecer Content-Type manualmente - FormData lo hace automáticamente
                delete headers['Content-Type'];
                delete headers['content-type'];
            } else {
                throw new Error('Error preparando archivos para envío');
            }
        } else {
            // Sin archivos, usar método tradicional
            const body = requestBodyInput?.value.trim() || '';
            if (body) {
                options.body = body;
                // Asegurar Content-Type si no está definido
                if (!headers['Content-Type'] && !headers['content-type']) {
                    headers['Content-Type'] = 'application/json';
                }
            }
        }
    }

    return options;
}

/**
 * Maneja la respuesta HTTP
 */
async function handleResponse(response, duration) {
    currentResponse = response;

    // Mostrar información básica
    displayResponseInfo(response, duration);

    // Mostrar headers
    displayResponseHeaders(response);

    // Procesar y mostrar el body
    await displayResponseBody(response);

    // Mostrar el panel de respuesta
    showResponsePanel();
}

/**
 * Muestra información básica de la respuesta
 */
function displayResponseInfo(response, duration) {
    const statusBadge = window.apiClientElements?.statusBadge;
    const responseTime = window.apiClientElements?.responseTime;

    if (!statusBadge || !responseTime) {
        console.error('Elementos de respuesta no encontrados');
        return;
    }

    // Status badge
    const status = response.status;
    statusBadge.textContent = `${status} ${response.statusText}`;
    statusBadge.className = 'status-badge ' + getStatusClass(status);

    // Tiempo de respuesta
    responseTime.textContent = `${duration}ms`;
}

/**
 * Determina la clase CSS según el código de estado
 */
function getStatusClass(status) {
    if (status >= 200 && status < 300) return 'status-success';
    if (status >= 400 && status < 500) return 'status-error';
    if (status >= 500) return 'status-error';
    return 'status-warning';
}

/**
 * Muestra los headers de respuesta
 */
function displayResponseHeaders(response) {
    const responseHeaders = window.apiClientElements?.responseHeaders;

    if (!responseHeaders) {
        console.error('Elemento responseHeaders no encontrado');
        return;
    }

    const headersObj = {};
    for (const [key, value] of response.headers.entries()) {
        headersObj[key] = value;
    }

    responseHeaders.textContent = JSON.stringify(headersObj, null, 2);
}

/**
 * Procesa y muestra el body de la respuesta
 */
async function displayResponseBody(response) {
    const responseBody = window.apiClientElements?.responseBody;

    if (!responseBody) {
        console.error('Elemento responseBody no encontrado');
        return;
    }

    try {
        const contentType = response.headers.get('content-type') || '';

        if (contentType.includes('application/json')) {
            const jsonData = await response.json();
            const formattedJson = JSON.stringify(jsonData, null, 2);
            responseBody.innerHTML = renderMarkdownCodeBlock(formattedJson, 'json');

            // Si la respuesta contiene archivos adjuntos, agregar previews
            if (jsonData.success && jsonData.data) {
                const attachmentPreview = renderAttachmentPreviews(jsonData.data);
                if (attachmentPreview) {
                    responseBody.innerHTML += attachmentPreview;
                }
            }
        } else {
            const textData = await response.text();
            responseBody.innerHTML = renderMarkdownCodeBlock(textData || '(Respuesta vacía)', 'text');
        }
    } catch (error) {
        console.error('Error procesando respuesta:', error);
        responseBody.innerHTML = renderMarkdownCodeBlock('Error procesando la respuesta: ' + error.message, 'error');
    }
}

/**
 * Renderiza contenido como un bloque de código markdown
 */
function renderMarkdownCodeBlock(content, language = 'json') {
    const escapedContent = content
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    let highlightedContent = escapedContent;

    // Aplicar syntax highlighting según el lenguaje
    if (language === 'json') {
        highlightedContent = syntaxHighlightJSON(escapedContent);
    }

    return `
        <div class="markdown-code-block">
            <div class="code-block-header">
                <span class="code-language">${language}</span>
                <button class="copy-code-button" onclick="copyCodeToClipboard(this)" title="Copiar código">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect width="14" height="14" x="8" y="8" rx="2" ry="2"/>
                        <path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>
                    </svg>
                </button>
            </div>
            <pre class="code-block-content"><code class="language-${language}">${highlightedContent}</code></pre>
        </div>
    `;
}

/**
 * Copia el contenido del bloque de código al portapapeles
 */
function copyCodeToClipboard(button) {
    const codeBlock = button.closest('.markdown-code-block');
    const codeContent = codeBlock.querySelector('code');
    const textContent = codeContent.textContent;

    navigator.clipboard.writeText(textContent).then(() => {
        // Mostrar feedback visual
        const originalText = button.innerHTML;
        button.innerHTML = `
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20,6 9,17 4,12"/>
            </svg>
        `;
        button.style.color = '#10b981';

        setTimeout(() => {
            button.innerHTML = originalText;
            button.style.color = '';
        }, 2000);
    }).catch(err => {
        console.error('Error al copiar:', err);
    });
}

/**
 * Aplica syntax highlighting a JSON
 */
function syntaxHighlightJSON(json) {
    json = json.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

    return json.replace(/("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g, function (match) {
        let cls = 'json-number';
        if (/^"/.test(match)) {
            if (/:$/.test(match)) {
                cls = 'json-key';
            } else {
                cls = 'json-string';
            }
        } else if (/true|false/.test(match)) {
            cls = 'json-boolean';
        } else if (/null/.test(match)) {
            cls = 'json-null';
        }
        return '<span class="' + cls + '">' + match + '</span>';
    });
}

/**
 * Muestra el panel de respuesta con animación
 */
function showResponsePanel() {
    const responseContainer = window.apiClientElements?.responseContainer;
    const responseContent = window.apiClientElements?.responseContent;

    if (!responseContainer || !responseContent) {
        console.error('Elementos del panel de respuesta no encontrados');
        return;
    }

    responseContainer.style.display = 'none';
    responseContent.style.display = 'block';
    responseContent.classList.add('fade-in');
}

/**
 * Maneja errores de red o de petición
 */
function handleError(error) {
    console.error('Error en la petición:', error);

    const responseContainer = window.apiClientElements?.responseContainer;
    const responseContent = window.apiClientElements?.responseContent;
    const statusBadge = window.apiClientElements?.statusBadge;
    const responseTime = window.apiClientElements?.responseTime;
    const responseHeaders = window.apiClientElements?.responseHeaders;
    const responseBody = window.apiClientElements?.responseBody;

    if (!responseContainer || !responseContent || !statusBadge ||
        !responseTime || !responseHeaders || !responseBody) {
        console.error('Elementos para mostrar error no encontrados');
        return;
    }

    // Mostrar error en el panel de respuesta
    responseContainer.style.display = 'none';
    responseContent.style.display = 'block';

    statusBadge.textContent = 'Error de Red';
    statusBadge.className = 'status-badge status-error';

    responseTime.textContent = 'N/A';
    responseHeaders.textContent = 'No disponible';
    responseBody.innerHTML = `<span style="color: #e74c3c;">Error: ${error.message}</span>`;

    responseContent.classList.add('fade-in');
}

/**
 * Controla el estado de carga del botón
 */
function setLoadingState(isLoading) {
    const executeButton = window.apiClientElements?.executeButton;
    const buttonText = window.apiClientElements?.buttonText;
    const loadingSpinner = window.apiClientElements?.loadingSpinner;

    if (!executeButton) return;

    if (isLoading) {
        executeButton.disabled = true;
        if (buttonText) buttonText.style.display = 'none';
        if (loadingSpinner) loadingSpinner.style.display = 'block';
    } else {
        executeButton.disabled = false;
        if (buttonText) buttonText.style.display = 'block';
        if (loadingSpinner) loadingSpinner.style.display = 'none';
    }
}

/**
 * Copia la respuesta JSON al portapapeles
 */
async function copyResponseToClipboard() {
    const responseBody = window.apiClientElements?.responseBody;
    const copyButton = window.apiClientElements?.copyButton;

    if (!responseBody || !copyButton) return;

    try {
        // Buscar el contenido del código dentro del bloque markdown
        const codeElement = responseBody.querySelector('code');
        const text = codeElement ? codeElement.textContent : (responseBody.textContent || responseBody.innerText);

        await navigator.clipboard.writeText(text);

        // Feedback visual
        const originalText = copyButton.textContent;
        copyButton.textContent = 'Copiado!';
        copyButton.style.background = '#27ae60';

        setTimeout(() => {
            copyButton.textContent = originalText;
            copyButton.style.background = 'transparent';
        }, 2000);

    } catch (error) {
        console.error('Error copiando al portapapeles:', error);
        alert('Error al copiar al portapapeles');
    }
}

/**
 * Inicialización de la aplicación
 */
function initializeApp() {
    const apiUrlInput = window.apiClientElements?.apiUrlInput;
    const customHeadersInput = window.apiClientElements?.customHeadersInput;

    if (!apiUrlInput) {
        console.error('apiUrlInput no encontrado durante la inicialización');
        return;
    }

    // Configurar valores por defecto
    apiUrlInput.value = window.location.origin + '/api/';

    if (customHeadersInput) {
        customHeadersInput.value = JSON.stringify({
            "Content-Type": "application/json",
            "Accept": "application/json"
        }, null, 2);
    }

    // Configurar auto-generación de nombres de consulta
    setupAutoQueryNaming();

    console.log('API Client inicializado correctamente');
}

/**
 * Configurar auto-generación de nombres de consulta
 */
function setupAutoQueryNaming() {
    const queryNameInput = document.getElementById('queryName');
    const urlInput = document.getElementById('apiUrl');
    const methodSelect = document.getElementById('httpMethod');

    if (!queryNameInput || !urlInput || !methodSelect) {
        console.warn('Elementos para auto-generación de nombres no encontrados:', {
            queryNameInput: !!queryNameInput,
            urlInput: !!urlInput,
            methodSelect: !!methodSelect
        });
        return;
    }

    console.log('✅ Auto-generación de nombres configurada correctamente');

    // Generar nombre automático cuando cambien URL o método
    function generateAutoName() {
        // Solo generar si el campo está vacío o tiene un nombre auto-generado previo
        const currentName = queryNameInput.value.trim();
        const isAutoGenerated = currentName === '' || currentName.match(/^(GET|POST|PUT|DELETE|PATCH)\s+/);

        if (isAutoGenerated && urlInput.value.trim()) {
            try {
                const url = new URL(urlInput.value.trim());
                const method = methodSelect.value;
                const path = url.pathname;

                // Crear nombre descriptivo
                let name = `${method} ${path}`;

                // Agregar parámetros de consulta si existen
                if (url.search) {
                    const params = new URLSearchParams(url.search);
                    const firstParam = Array.from(params.keys())[0];
                    if (firstParam) {
                        name += ` (${firstParam})`;
                    }
                }

                queryNameInput.value = name;

                // Disparar evento para que el historial se actualice
                queryNameInput.dispatchEvent(new Event('input', { bubbles: true }));
            } catch (error) {
                // Si la URL no es válida, usar un nombre simple
                queryNameInput.value = `${methodSelect.value} Request`;
            }
        }
    }

    // Escuchar cambios
    urlInput.addEventListener('blur', generateAutoName);
    methodSelect.addEventListener('change', generateAutoName);
}

// Inicialización cuando el DOM está cargado
document.addEventListener('DOMContentLoaded', function() {
    // Esperar un poco para asegurar que initializeApiClient se ejecute primero
    setTimeout(() => {
        if (window.apiClientElements) {
            initializeApp();
        } else {
            console.warn('apiClientElements no está disponible, reintentando...');
            setTimeout(() => {
                if (window.apiClientElements) {
                    initializeApp();
                } else {
                    console.error('No se pudo inicializar la aplicación: elementos no encontrados');
                }
            }, 500);
        }
    }, 100);
});

// Funciones para manejo de archivos adjuntos

/**
 * Renderiza previews de archivos adjuntos si existen en los datos
 * @param {Object} data - Objeto de datos que puede contener attachments
 * @returns {string} - HTML con los previews o cadena vacía
 */
function renderAttachmentPreviews(data) {
    let attachments = [];

    // Buscar attachments en diferentes ubicaciones
    if (data.attachments) {
        attachments = data.attachments;
    } else if (data.user && data.user.attachments) {
        attachments = data.user.attachments;
    } else if (data.items && Array.isArray(data.items)) {
        // Para listas, buscar attachments en cada item
        let hasAttachments = false;
        let itemsHtml = '';

        data.items.forEach((item, index) => {
            if (item.attachments && item.attachments.length > 0) {
                hasAttachments = true;
                itemsHtml += `
                    <div class="attachments-section">
                        <h4>Archivos del elemento ${index + 1} (${item.name || item.email || item.id}):</h4>
                        ${renderAttachmentList(item.attachments)}
                    </div>
                `;
            }
        });

        if (hasAttachments) {
            return `<div class="list-attachments-preview">${itemsHtml}</div>`;
        }
        return '';
    }

    if (attachments.length === 0) {
        return '';
    }

    return `
        <div class="attachments-preview">
            <h4>Archivos adjuntos:</h4>
            ${renderAttachmentList(attachments)}
        </div>
    `;
}

/**
 * Renderiza una lista de archivos adjuntos
 * @param {Array} attachments - Array de attachments
 * @returns {string} - HTML de la lista
 */
function renderAttachmentList(attachments) {
    return attachments.map(attachment => `
        <div class="attachment-item" data-mime="${attachment.mime_type}">
            <div class="attachment-info">
                <strong>${attachment.name}</strong>
                <span class="mime-type">${attachment.mime_type}</span>
                <span class="file-size">${formatFileSize(attachment.size)}</span>
            </div>
            ${renderAttachmentContent(attachment)}
        </div>
    `).join('');
}

/**
 * Renderiza el contenido específico de un attachment según su tipo
 * @param {Object} attachment - Objeto attachment
 * @returns {string} - HTML del contenido específico
 */
function renderAttachmentContent(attachment) {
    const { mime_type, metadata, url, id } = attachment;
    const baseUrl = window.location.origin;
    const fullUrl = url.startsWith('http') ? url : `${baseUrl}${url}`;

    if (mime_type.startsWith('image/')) {
        const imageUrl = metadata.thumbnail_url ?
            (metadata.thumbnail_url.startsWith('http') ? metadata.thumbnail_url : `${baseUrl}${metadata.thumbnail_url}`) :
            fullUrl;

        return `
            <div class="image-preview">
                <img src="${imageUrl}"
                     alt="${metadata.alt_text || attachment.name}"
                     style="max-width: 200px; max-height: 200px; object-fit: cover; border-radius: 4px;">
                <div class="image-actions">
                    <button onclick="viewFullImage('${fullUrl}')" class="action-button">Ver completa</button>
                    <button onclick="downloadAttachment('${id}', '${attachment.name}')" class="action-button">Descargar</button>
                </div>
            </div>
        `;
    } else if (mime_type === 'application/pdf') {
        const previewButton = metadata.preview_url ?
            `<button onclick="viewDocument('${metadata.preview_url}')" class="action-button">Vista previa</button>` : '';

        return `
            <div class="document-preview">
                <div class="document-icon" style="font-size: 48px; text-align: center;">📄</div>
                <div class="document-actions">
                    ${previewButton}
                    <button onclick="downloadAttachment('${id}', '${attachment.name}')" class="action-button">Descargar</button>
                </div>
            </div>
        `;
    } else {
        return `
            <div class="generic-file">
                <div class="file-icon" style="font-size: 48px; text-align: center;">📎</div>
                <button onclick="downloadAttachment('${id}', '${attachment.name}')" class="action-button">Descargar</button>
            </div>
        `;
    }
}

/**
 * Formatea el tamaño de un archivo en formato legible
 * @param {number} bytes - Tamaño en bytes
 * @returns {string} - Tamaño formateado
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Muestra una imagen en tamaño completo en un modal
 * @param {string} imageUrl - URL de la imagen
 */
function viewFullImage(imageUrl) {
    const modal = document.createElement('div');
    modal.className = 'image-modal';
    modal.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        cursor: pointer;
    `;

    modal.innerHTML = `
        <div class="modal-content" style="position: relative; max-width: 90vw; max-height: 90vh;">
            <span class="close" style="position: absolute; top: -40px; right: 0; color: white; font-size: 30px; cursor: pointer;">&times;</span>
            <img src="${imageUrl}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
        </div>
    `;

    // Cerrar modal al hacer clic
    modal.addEventListener('click', (e) => {
        if (e.target === modal || e.target.classList.contains('close')) {
            modal.remove();
        }
    });

    document.body.appendChild(modal);
}

/**
 * Descarga un archivo por su ID
 * @param {string} fileId - ID del archivo
 * @param {string} filename - Nombre sugerido para el archivo
 */
async function downloadAttachment(fileId, filename) {
    try {
        const baseUrl = window.location.origin;
        const response = await fetch(`${baseUrl}/api/files/${fileId}?download=true`);

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);

        const a = document.createElement('a');
        a.href = url;
        a.download = filename;
        document.body.appendChild(a);
        a.click();

        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);

        console.log(`✅ Archivo ${filename} descargado exitosamente`);
    } catch (error) {
        console.error('❌ Error descargando archivo:', error.message);
        alert('Error al descargar el archivo. Verifica la consola para más detalles.');
    }
}

/**
 * Muestra un documento en una nueva ventana
 * @param {string} documentUrl - URL del documento
 */
function viewDocument(documentUrl) {
    const baseUrl = window.location.origin;
    const fullUrl = documentUrl.startsWith('http') ? documentUrl : `${baseUrl}${documentUrl}`;
    window.open(fullUrl, '_blank');
}
