/**
 * API Client - Manejo de Archivos Adjuntos
 * Este archivo maneja la funcionalidad de carga y gestión de archivos
 */

// Variables globales para archivos
let selectedFiles = [];
let maxFiles = 10;
let maxFileSize = 10 * 1024 * 1024; // 10MB en bytes

/**
 * Inicializar funcionalidad de archivos
 */
function initializeFileHandling() {
    console.log('📁 Inicializando manejo de archivos...');

    const fileInput = document.getElementById('fileInput');
    const selectFilesButton = document.getElementById('selectFilesButton');
    const clearFilesButton = document.getElementById('clearFilesButton');
    const fileUploadSection = document.querySelector('.file-upload-section');

    if (!fileInput || !selectFilesButton) {
        console.warn('⚠️ Elementos de archivos no encontrados');
        return;
    }

    // Event listeners
    selectFilesButton.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', handleFileSelection);

    if (clearFilesButton) {
        clearFilesButton.addEventListener('click', clearAllFiles);
    }

    // Drag and drop
    setupDragAndDrop(fileUploadSection);

    console.log('✅ Manejo de archivos inicializado');
}

/**
 * Configurar drag and drop
 */
function setupDragAndDrop(element) {
    if (!element) return;

    element.addEventListener('dragover', (e) => {
        e.preventDefault();
        element.classList.add('dragover');
    });

    element.addEventListener('dragleave', (e) => {
        e.preventDefault();
        if (!element.contains(e.relatedTarget)) {
            element.classList.remove('dragover');
        }
    });

    element.addEventListener('drop', (e) => {
        e.preventDefault();
        element.classList.remove('dragover');

        const files = Array.from(e.dataTransfer.files);
        handleFilesArray(files);
    });
}

/**
 * Manejar selección de archivos desde input
 */
function handleFileSelection(event) {
    const files = Array.from(event.target.files);
    handleFilesArray(files);
}

/**
 * Procesar array de archivos
 */
function handleFilesArray(files) {
    console.log('📁 Procesando archivos:', files.length);

    // Validar cantidad total
    if (selectedFiles.length + files.length > maxFiles) {
        showFileError(`No puedes seleccionar más de ${maxFiles} archivos en total`);
        return;
    }

    // Procesar cada archivo
    const validFiles = [];
    for (const file of files) {
        if (validateFile(file)) {
            validFiles.push(file);
        }
    }

    // Agregar archivos válidos
    selectedFiles = [...selectedFiles, ...validFiles];
    updateFilesList();
    updateFileUI();

    console.log('✅ Archivos procesados:', selectedFiles.length);
}

/**
 * Validar archivo individual
 */
function validateFile(file) {
    // Validar tamaño
    if (file.size > maxFileSize) {
        showFileError(`El archivo "${file.name}" es demasiado grande. Máximo ${formatFileSize(maxFileSize)}`);
        return false;
    }

    // Validar si ya existe
    if (selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
        showFileError(`El archivo "${file.name}" ya está seleccionado`);
        return false;
    }

    return true;
}

/**
 * Actualizar lista visual de archivos
 */
function updateFilesList() {
    const filesList = document.getElementById('filesList');
    const selectedFilesList = document.getElementById('selectedFilesList');

    if (!filesList || !selectedFilesList) return;

    if (selectedFiles.length === 0) {
        selectedFilesList.style.display = 'none';
        return;
    }

    selectedFilesList.style.display = 'block';
    filesList.innerHTML = '';

    selectedFiles.forEach((file, index) => {
        const fileItem = createFileItem(file, index);
        filesList.appendChild(fileItem);
    });
}

/**
 * Crear elemento visual para archivo
 */
function createFileItem(file, index) {
    const item = document.createElement('div');
    item.className = 'file-item';
    item.setAttribute('data-type', getFileType(file));

    const fileIcon = getFileIcon(file);
    const fileName = file.name.length > 30 ? file.name.substring(0, 27) + '...' : file.name;
    const fileSize = formatFileSize(file.size);

    item.innerHTML = `
        <div class="file-info">
            <div class="file-icon">${fileIcon}</div>
            <div class="file-details">
                <div class="file-name" title="${file.name}">${fileName}</div>
                <div class="file-size">${fileSize}</div>
            </div>
        </div>
        <button type="button" class="remove-file-button" onclick="removeFile(${index})">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="18" y1="6" x2="6" y2="18"></line>
                <line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
        </button>
    `;

    return item;
}

/**
 * Obtener icono según tipo de archivo
 */
function getFileIcon(file) {
    const type = file.type.toLowerCase();

    if (type.includes('image')) {
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
            <circle cx="8.5" cy="8.5" r="1.5"/>
            <polyline points="21,15 16,10 5,21"/>
        </svg>`;
    }

    if (type.includes('pdf')) {
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
        </svg>`;
    }

    if (type.includes('video')) {
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polygon points="23 7 16 12 23 17 23 7"/>
            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
        </svg>`;
    }

    if (type.includes('audio')) {
        return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M9 18V5l12-2v13"/>
            <circle cx="6" cy="18" r="3"/>
            <circle cx="18" cy="16" r="3"/>
        </svg>`;
    }

    // Archivo genérico
    return `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z"/>
    </svg>`;
}

/**
 * Obtener tipo de archivo para styling
 */
function getFileType(file) {
    const type = file.type.toLowerCase();

    if (type.includes('image')) return 'image';
    if (type.includes('pdf')) return 'pdf';
    if (type.includes('video')) return 'video';
    if (type.includes('audio')) return 'audio';
    if (type.includes('text')) return 'text';
    if (type.includes('zip') || type.includes('rar') || type.includes('7z')) return 'archive';
    if (type.includes('doc') || type.includes('docx') || type.includes('xlsx') || type.includes('pptx')) return 'document';

    return 'other';
}

/**
 * Formatear tamaño de archivo
 */
function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Remover archivo por índice
 */
function removeFile(index) {
    selectedFiles.splice(index, 1);
    updateFilesList();
    updateFileUI();

    // Limpiar input file
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.value = '';
    }
}

/**
 * Limpiar todos los archivos
 */
function clearAllFiles() {
    selectedFiles = [];
    updateFilesList();
    updateFileUI();

    // Limpiar input file
    const fileInput = document.getElementById('fileInput');
    if (fileInput) {
        fileInput.value = '';
    }
}

/**
 * Actualizar UI según estado de archivos
 */
function updateFileUI() {
    const fileDescriptionWrapper = document.getElementById('fileDescriptionWrapper');

    if (fileDescriptionWrapper) {
        fileDescriptionWrapper.style.display = selectedFiles.length > 0 ? 'block' : 'none';
    }
}

/**
 * Mostrar error relacionado con archivos
 */
function showFileError(message) {
    // Crear toast de error temporal
    const toast = document.createElement('div');
    toast.className = 'file-error-toast';
    toast.textContent = message;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: var(--danger-color);
        color: white;
        padding: 12px 20px;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        z-index: 1000;
        font-size: 14px;
        max-width: 300px;
        animation: slideInRight 0.3s ease;
    `;

    document.body.appendChild(toast);

    // Remover después de 4 segundos
    setTimeout(() => {
        toast.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 4000);
}

/**
 * Preparar FormData con archivos para envío
 */
function prepareFormDataWithFiles(baseData = {}) {
    if (selectedFiles.length === 0) {
        return null; // No hay archivos, usar método normal
    }

    const formData = new FormData();

    // Agregar archivos
    selectedFiles.forEach((file, index) => {
        formData.append(`files[${index}]`, file);
    });

    // Agregar descripción si existe
    const fileDescription = document.getElementById('fileDescription');
    if (fileDescription && fileDescription.value.trim()) {
        formData.append('description', fileDescription.value.trim());
    }

    // Agregar otros datos del formulario
    Object.keys(baseData).forEach(key => {
        if (baseData[key] !== null && baseData[key] !== undefined) {
            formData.append(key, baseData[key]);
        }
    });

    return formData;
}

/**
 * Obtener archivos seleccionados
 */
function getSelectedFiles() {
    return selectedFiles;
}

/**
 * Verificar si hay archivos seleccionados
 */
function hasSelectedFiles() {
    return selectedFiles.length > 0;
}

// Agregar estilos CSS para las animaciones
if (!document.getElementById('file-animations')) {
    const style = document.createElement('style');
    style.id = 'file-animations';
    style.textContent = `
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    `;
    document.head.appendChild(style);
}

// Inicializar cuando el DOM esté listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFileHandling);
} else {
    initializeFileHandling();
}
