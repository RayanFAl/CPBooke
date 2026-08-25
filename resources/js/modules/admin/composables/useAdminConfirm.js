import { ref } from 'vue';

const confirmState = ref({
    show: false,
    title: '',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    variant: 'danger',
});

let resolveConfirm = null;

function resetConfirm() {
    confirmState.value = {
        show: false,
        title: '',
        message: '',
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        variant: 'danger',
    };
    resolveConfirm = null;
}

export function useAdminConfirm() {
    const confirm = ({
        title = 'Confirm action',
        message = '',
        confirmLabel = 'Confirm',
        cancelLabel = 'Cancel',
        variant = 'danger',
    } = {}) => new Promise((resolve) => {
        resolveConfirm = resolve;
        confirmState.value = {
            show: true,
            title,
            message,
            confirmLabel,
            cancelLabel,
            variant,
        };
    });

    const accept = () => {
        resolveConfirm?.(true);
        resetConfirm();
    };

    const cancel = () => {
        resolveConfirm?.(false);
        resetConfirm();
    };

    return {
        confirmState,
        confirm,
        accept,
        cancel,
    };
}
