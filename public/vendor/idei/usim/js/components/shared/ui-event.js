/**
 * Shared UI event helpers for modular components.
 */
(function initUsimComponentHelpers(global) {
    const namespace = global.USIM_COMPONENT_HELPERS || {};

    async function sendUiEvent({ componentId, event, action, parameters = {}, credentials = 'same-origin' }) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const usimStorage = typeof getUsimStorageHeaderValue === 'function'
            ? getUsimStorageHeaderValue()
            : '';

        const response = await fetch('/api/ui-event', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'X-USIM-Storage': usimStorage,
            },
            credentials,
            body: JSON.stringify({
                component_id: componentId,
                event,
                action,
                parameters,
            }),
        });

        let result = null;
        try {
            result = await response.json();
        } catch (_error) {
            result = null;
        }

        return { ok: response.ok, response, result };
    }

    function applyUiUpdate(result) {
        if (!result || typeof result !== 'object' || Object.keys(result).length === 0) {
            return false;
        }

        const renderer = global.globalRenderer || (typeof globalRenderer !== 'undefined' ? globalRenderer : null);

        if (renderer && typeof renderer.handleUIUpdate === 'function') {
            renderer.handleUIUpdate(result);
            return true;
        }

        return false;
    }

    namespace.sendUiEvent = sendUiEvent;
    namespace.applyUiUpdate = applyUiUpdate;
    global.USIM_COMPONENT_HELPERS = namespace;
})(window);
