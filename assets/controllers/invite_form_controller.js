import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['keyInput', 'preview', 'status'];

    static values = {
        checkUrl: String,
        previewBaseUrl: String,
        exceptId: Number,
        msgKeyAvailable: String,
        msgKeyTaken: String,
        msgKeyRequired: String,
        msgKeyInvalid: String,
        msgError: String,
    };

    connect() {
        this.updatePreview();
    }

    onKeyChange() {
        this.updatePreview();
        this.clearStatus();
    }

    generateKey(event) {
        event.preventDefault();
        const alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        let key = '';
        const bytes = new Uint8Array(10);
        (window.crypto || window.msCrypto).getRandomValues(bytes);
        for (let i = 0; i < bytes.length; i++) {
            key += alphabet[bytes[i] % alphabet.length];
        }
        this.keyInputTarget.value = key;
        this.updatePreview();
        this.clearStatus();
    }

    async checkKey(event) {
        event.preventDefault();
        const linkKey = this.keyInputTarget.value.trim();

        if (!linkKey) {
            this.setStatus(this.msgKeyRequiredValue, 'error');
            return;
        }

        if (!/^[a-zA-Z0-9_-]{3,64}$/.test(linkKey)) {
            this.setStatus(this.msgKeyInvalidValue, 'error');
            return;
        }

        try {
            const body = { linkKey };
            if (this.exceptIdValue > 0) {
                body.exceptId = this.exceptIdValue;
            }

            const response = await fetch(this.checkUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify(body),
            });
            const data = await response.json();
            this.updatePreview();

            if (data.available) {
                this.setStatus(this.msgKeyAvailableValue, 'success');
            } else {
                const message = this.messageFromKey(data.message) || this.msgKeyTakenValue;
                this.setStatus(message, 'error');
            }
        } catch (error) {
            this.setStatus(this.msgErrorValue, 'error');
        }
    }

    updatePreview() {
        const key = this.keyInputTarget.value.trim();
        if (!key || !this.previewBaseUrlValue) {
            this.previewTarget.textContent = '—';
            return;
        }

        const base = this.previewBaseUrlValue.replace(/\?.*$/, '').replace(/\/$/, '');
        this.previewTarget.textContent = `${base}?ref=${encodeURIComponent(key)}`;
    }

    setStatus(message, type) {
        this.statusTarget.textContent = message;
        this.statusTarget.classList.remove('is-success', 'is-error');
        this.statusTarget.classList.add(type === 'success' ? 'is-success' : 'is-error');
    }

    clearStatus() {
        this.statusTarget.textContent = '';
        this.statusTarget.classList.remove('is-success', 'is-error');
    }

    messageFromKey(key) {
        const map = {
            'cabinet.invites.form.key_available': this.msgKeyAvailableValue,
            'cabinet.invites.form.key_taken': this.msgKeyTakenValue,
            'cabinet.invites.form.key_required': this.msgKeyRequiredValue,
            'cabinet.invites.form.key_invalid': this.msgKeyInvalidValue,
            'cabinet.invites.form.error': this.msgErrorValue,
        };

        return map[key] || null;
    }
}
