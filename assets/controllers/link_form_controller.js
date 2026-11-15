import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'modal',
        'confirmModal',
        'form',
        'partnerSelect',
        'pageSelect',
        'productNameInput',
        'keyInput',
        'preview',
        'status',
        'listStatus',
        'tableBody',
        'emptyRow',
    ];

    static values = {
        partners: Array,
        checkUrl: String,
        createUrl: String,
        detailUrlTemplate: String,
        deleteUrlTemplate: String,
        emptyText: String,
        currency: String,
        msgKeyAvailable: String,
        msgKeyTaken: String,
        msgKeyRequired: String,
        msgKeyInvalid: String,
        msgPartnerRequired: String,
        msgPageRequired: String,
        msgProductNameRequired: String,
        msgUnsaved: String,
        msgSaved: String,
        msgError: String,
        msgOpen: String,
        msgDelete: String,
        msgDeleteConfirm: String,
        msgDeleteSuccess: String,
        msgDeleteForbidden: String,
        msgDeleteNotFound: String,
        msgPagePlaceholder: String,
    };

    connect() {
        this.dirty = false;
        this.initialSnapshot = '';
    }

    open() {
        this.resetForm();
        this.clearListStatus();
        this.modalTarget.classList.remove('is-hidden');
        this.partnerSelectTarget.focus();
    }

    requestClose() {
        if (this.isDirty()) {
            this.confirmModalTarget.classList.remove('is-hidden');
            return;
        }

        this.close();
    }

    confirmClose() {
        this.confirmModalTarget.classList.add('is-hidden');
        this.close();
    }

    cancelClose() {
        this.confirmModalTarget.classList.add('is-hidden');
    }

    close() {
        this.modalTarget.classList.add('is-hidden');
        this.confirmModalTarget.classList.add('is-hidden');
        this.resetForm();
    }

    backdropClose(event) {
        if (event.target === this.modalTarget) {
            this.requestClose();
        }
    }

    stopPropagation(event) {
        event.stopPropagation();
    }

    onPartnerChange() {
        this.populatePages();
        this.onChange();
    }

    onChange() {
        this.updatePreview();
        this.clearStatus();
    }

    async checkKey(event) {
        event.preventDefault();
        const pageId = this.pageSelectTarget.value;
        const linkKey = this.keyInputTarget.value.trim();

        if (!this.partnerSelectTarget.value) {
            this.setStatus(this.msgPartnerRequiredValue, 'error');
            return;
        }

        if (!pageId) {
            this.setStatus(this.msgPageRequiredValue, 'error');
            return;
        }

        if (!linkKey) {
            this.setStatus(this.msgKeyRequiredValue, 'error');
            return;
        }

        try {
            const response = await fetch(this.checkUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ pageId, linkKey }),
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

    async save(event) {
        event.preventDefault();
        const pageId = this.pageSelectTarget.value;
        const productName = this.productNameInputTarget.value.trim();
        const linkKey = this.keyInputTarget.value.trim();

        if (!this.partnerSelectTarget.value) {
            this.setStatus(this.msgPartnerRequiredValue, 'error');
            return;
        }

        if (!pageId) {
            this.setStatus(this.msgPageRequiredValue, 'error');
            return;
        }

        if (!productName) {
            this.setStatus(this.msgProductNameRequiredValue, 'error');
            return;
        }

        if (!linkKey) {
            this.setStatus(this.msgKeyRequiredValue, 'error');
            return;
        }

        try {
            const response = await fetch(this.createUrlValue, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ pageId, productName, linkKey }),
            });
            const data = await response.json();

            if (!response.ok || !data.ok) {
                this.setStatus(this.messageFromKey(data.message) || this.msgErrorValue, 'error');
                return;
            }

            this.prependRow(data.link);
            this.dirty = false;
            this.initialSnapshot = this.snapshot();
            this.close();
            this.setListStatus(this.msgSavedValue, 'success');
        } catch (error) {
            this.setStatus(this.msgErrorValue, 'error');
        }
    }

    async deleteLink(event) {
        event.preventDefault();
        const button = event.currentTarget;
        const linkId = button.dataset.linkId;

        if (!linkId) {
            return;
        }

        if (!window.confirm(this.msgDeleteConfirmValue)) {
            return;
        }

        const deleteUrl = this.deleteUrlTemplateValue.replace('__ID__', String(linkId));

        try {
            const response = await fetch(deleteUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                },
            });
            const data = await response.json();

            if (!response.ok || !data.ok) {
                this.setListStatus(
                    this.messageFromKey(data.message) || this.msgDeleteForbiddenValue,
                    'error',
                );
                return;
            }

            const row = this.tableBodyTarget.querySelector(`tr[data-link-id="${linkId}"]`);
            if (row) {
                row.remove();
            }

            this.ensureEmptyRow();
            this.setListStatus(this.msgDeleteSuccessValue, 'success');
        } catch (error) {
            this.setListStatus(this.msgErrorValue, 'error');
        }
    }

    prependRow(link) {
        if (this.hasEmptyRowTarget) {
            this.emptyRowTarget.remove();
        }

        const detailUrl = this.detailUrlTemplateValue.replace('__ID__', String(link.id));
        const row = document.createElement('tr');
        row.dataset.linkId = String(link.id);
        row.innerHTML = `
            <td>${this.escapeHtml(link.partnerName)}</td>
            <td>${this.escapeHtml(link.productName || '—')}</td>
            <td>${this.escapeHtml(link.pageCode || '—')}</td>
            <td>${this.escapeHtml(link.linkKey)}</td>
            <td class="url-cell">${this.escapeHtml(link.url)}</td>
            <td>${this.escapeHtml(String(link.clicks))}</td>
            <td>${this.escapeHtml(String(link.orders))}</td>
            <td>${this.escapeHtml(String(link.reward))} ${this.escapeHtml(this.currencyValue)}</td>
            <td class="row-actions">
                <a href="${this.escapeHtml(detailUrl)}">${this.escapeHtml(this.msgOpenValue)}</a>
                <button
                    type="button"
                    class="btn-link-danger"
                    data-action="link-form#deleteLink"
                    data-link-id="${this.escapeHtml(String(link.id))}"
                >${this.escapeHtml(this.msgDeleteValue)}</button>
            </td>
        `;
        this.tableBodyTarget.prepend(row);
    }

    ensureEmptyRow() {
        const rows = this.tableBodyTarget.querySelectorAll('tr[data-link-id]');
        if (rows.length > 0) {
            return;
        }

        if (this.hasEmptyRowTarget) {
            return;
        }

        const emptyRow = document.createElement('tr');
        emptyRow.setAttribute('data-link-form-target', 'emptyRow');
        emptyRow.innerHTML = `<td colspan="9">${this.escapeHtml(this.emptyTextValue)}</td>`;
        this.tableBodyTarget.append(emptyRow);
    }

    populatePages() {
        const partnerId = Number(this.partnerSelectTarget.value);
        const partner = (this.partnersValue || []).find((item) => Number(item.id) === partnerId);
        const pages = partner ? partner.pages || [] : [];

        this.pageSelectTarget.innerHTML = '';
        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = this.msgPagePlaceholderValue;
        this.pageSelectTarget.append(placeholder);

        pages.forEach((page) => {
            const option = document.createElement('option');
            option.value = String(page.id);
            option.dataset.code = page.code || '';
            option.dataset.url = page.url || '';
            option.textContent = `[${page.code}] ${page.url}`;
            this.pageSelectTarget.append(option);
        });

        this.pageSelectTarget.disabled = pages.length === 0;
        if (pages.length === 1) {
            this.pageSelectTarget.value = String(pages[0].id);
        }
    }

    updatePreview() {
        const option = this.pageSelectTarget.selectedOptions[0];
        const baseUrl = option ? option.dataset.url : '';
        const code = option ? option.dataset.code : '';
        const key = this.keyInputTarget.value.trim();

        if (!baseUrl || !code || !key) {
            this.previewTarget.textContent = '—';
            return;
        }

        this.previewTarget.textContent = `${baseUrl.replace(/\/$/, '')}/${code}/${key}`;
    }

    resetForm() {
        this.formTarget.reset();
        this.pageSelectTarget.innerHTML = `<option value="">${this.escapeHtml(this.msgPagePlaceholderValue)}</option>`;
        this.pageSelectTarget.disabled = true;
        this.previewTarget.textContent = '—';
        this.clearStatus();
        this.initialSnapshot = this.snapshot();
        this.dirty = false;
    }

    snapshot() {
        return [
            this.partnerSelectTarget.value,
            this.pageSelectTarget.value,
            this.productNameInputTarget.value.trim(),
            this.keyInputTarget.value.trim(),
        ].join('|');
    }

    isDirty() {
        return this.snapshot() !== this.initialSnapshot;
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

    setListStatus(message, type) {
        this.listStatusTarget.textContent = message;
        this.listStatusTarget.classList.remove('is-hidden', 'is-success', 'is-error');
        this.listStatusTarget.classList.add(type === 'success' ? 'is-success' : 'is-error');
    }

    clearListStatus() {
        this.listStatusTarget.textContent = '';
        this.listStatusTarget.classList.add('is-hidden');
        this.listStatusTarget.classList.remove('is-success', 'is-error');
    }

    messageFromKey(key) {
        const map = {
            'cabinet.links.popup.key_available': this.msgKeyAvailableValue,
            'cabinet.links.popup.key_taken': this.msgKeyTakenValue,
            'cabinet.links.popup.key_required': this.msgKeyRequiredValue,
            'cabinet.links.popup.key_invalid': this.msgKeyInvalidValue,
            'cabinet.links.popup.partner_required': this.msgPartnerRequiredValue,
            'cabinet.links.popup.page_required': this.msgPageRequiredValue,
            'cabinet.links.popup.product_name_required': this.msgProductNameRequiredValue,
            'cabinet.links.popup.saved': this.msgSavedValue,
            'cabinet.links.popup.error': this.msgErrorValue,
            'cabinet.links.delete.success': this.msgDeleteSuccessValue,
            'cabinet.links.delete.forbidden': this.msgDeleteForbiddenValue,
            'cabinet.links.delete.not_found': this.msgDeleteNotFoundValue,
        };

        return map[key] || null;
    }

    escapeHtml(value) {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }
}
