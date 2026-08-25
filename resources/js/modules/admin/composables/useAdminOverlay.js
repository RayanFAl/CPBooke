import { nextTick, onUnmounted, watch } from 'vue';

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'textarea:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

function getFocusableElements(container) {
    return [...container.querySelectorAll(FOCUSABLE_SELECTOR)]
        .filter((element) => !element.hasAttribute('disabled') && element.offsetParent !== null);
}

export function useAdminOverlay(containerRef, show, onClose) {
    let previousFocus = null;

    const handleKeydown = (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            onClose();
            return;
        }

        if (event.key !== 'Tab' || !containerRef.value) {
            return;
        }

        const focusable = getFocusableElements(containerRef.value);

        if (focusable.length === 0) {
            event.preventDefault();
            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && active === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    };

    const activate = () => {
        previousFocus = document.activeElement;
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', handleKeydown);

        nextTick(() => {
            if (!containerRef.value) {
                return;
            }

            const focusable = getFocusableElements(containerRef.value);
            (focusable[0] ?? containerRef.value).focus();
        });
    };

    const deactivate = () => {
        document.body.style.overflow = '';
        document.removeEventListener('keydown', handleKeydown);

        if (previousFocus && typeof previousFocus.focus === 'function') {
            previousFocus.focus();
        }

        previousFocus = null;
    };

    watch(show, (visible) => {
        if (visible) {
            activate();
        } else {
            deactivate();
        }
    }, { immediate: true });

    onUnmounted(deactivate);
}
