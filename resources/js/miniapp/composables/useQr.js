import { ref, watch } from 'vue';
import QRCode from 'qrcode';

export function useQr(value, options = {}) {
    const dataUrl = ref('');
    const error = ref(null);

    const render = async (text) => {
        if (!text) {
            dataUrl.value = '';
            return;
        }
        try {
            dataUrl.value = await QRCode.toDataURL(text, {
                margin: 1,
                scale: 8,
                color: {
                    dark: options.dark || '#ffffff',
                    light: options.light || '#00000000',
                },
                errorCorrectionLevel: options.ecc || 'M',
            });
        } catch (e) {
            error.value = e;
        }
    };

    watch(() => (typeof value === 'function' ? value() : value?.value ?? value), render, { immediate: true });

    return { dataUrl, error };
}
