import { ref } from 'vue';

const toasts = ref([]);
let nextToastId = 0;

function removeToast(id) {
    toasts.value = toasts.value.filter((toast) => toast.id !== id);
}

function pushToast(message, variant = 'success', duration = 5000) {
    if (!message) {
        return null;
    }

    const id = ++nextToastId;

    toasts.value.push({
        id,
        message,
        variant,
    });

    if (duration > 0) {
        window.setTimeout(() => removeToast(id), duration);
    }

    return id;
}

export function useAdminToast() {
    return {
        toasts,
        success: (message, duration = 5000) => pushToast(message, 'success', duration),
        error: (message, duration = 5000) => pushToast(message, 'error', duration),
        info: (message, duration = 5000) => pushToast(message, 'info', duration),
        dismiss: removeToast,
    };
}
