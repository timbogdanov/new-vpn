import { ref } from 'vue';

export function useClipboard() {
    const copied = ref(false);

    async function copy(text) {
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
            }
            copied.value = true;
            setTimeout(() => (copied.value = false), 1500);
            return true;
        } catch (_) {
            return false;
        }
    }

    return { copy, copied };
}
