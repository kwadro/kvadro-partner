import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'modal',
        'confirmModal',
        'modalTitle',
        'form',
        'idInput',
        'nameInput',
        'urlInput',
        'emailInput',
        'phoneInput',
        'status',
        'listStatus',
        'tableBody',
        'emptyRow',
    ];

    static values = {
        createUrl: String,
        updateUrlTemplate: String,
        deleteUrlTemplate: String,
        emptyText: String,
        msgNameRequired: String,
        msgUrlRequired: String,
        msgEmailInvalid: String,
        msgUnsaved: String,
        msgCreated: String,
        msgUpdated: String,
        msgDeleted: String,
        msgDeleteForbidden: String,
        msgDeleteConfirm: String,
        msgNotFound: String,
        msgError: String,
        msgEdit: String,
        msgDelete: String,
        msgCreateTitle: String,
        msgEditTitle: String,
    };

    connect() {
        this.editingId = null;
        this.initialSnapshot = '';
    }

    openCreate() {
        this.editingId = null;
        this.modalTitleTarget.textContent = this.msgCreateTitleValue;
        this.resetForm();
        this.clearListStatus();
        this.modalTarget.classList.remove('is-hidden');
        this.nameInputTarget.focus();
    }

    openEdit(event) {
        const partnerId = event.currentTarget.dataset.partnerId;
        const row = this.tableBodyTarget.querySelector(`tr[data-partner-id="${partnerId}"]`);
        if (!row) {
            return;
        }

        this.editingId = partnerId;
        this.modalTitleTarget.textContent = this.msgEditTitleValue;
        this.clearListStatus();
        this.clearStatus();

        this.idInputTarget.value = partnerId;
        this.nameInputTarget.value = row.dataset.partnerName || '';
        this.urlInputTarget.value = row.dataset.partnerUrl || '';
        this.emailInputTarget.value = row.dataset.partnerEmail || '';
        this.phoneInputTarget.value = row.dataset.partnerPhone || '';
        this.initialSnapshot = this.snapshot();

        this.modalTarget.classList.remove('is-hidden');
        this.nameInputTarget.focus();
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
        this.editingId = null;
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

    onChange() {
        this.clearStatus();
    }

    async save(event) {
        event.preventDefault();

        const name = this.nameInputTarget.value.trim();
        const url = this.urlInputTarget.value.trim();
        const email = this.emailInputTarget.value.trim();
        const phone = this.phoneInputTarget.value.trim();

        if (!name) {
            this.setStatus(this.msgNameRequiredValue, 'error');
            return;
        }

        if (!url) {
            this.setStatus(this.msgUrlRequiredValue, 'error');
            return;
        }

        const isEdit = Boolean(this.editingId);
        const saveUrl = isEdit
            ? this.updateUrlTemplateValue.replace('__ID__', String(this.editingId))
            : this.createUrlValue;

        try {
            const response = await fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ name, url, email, phone }),
            });
            const data = await response.json();

            if (!response.ok || !data.ok) {
                this.setStatus(this.messageFromKey(data.message) || this.msgErrorValue, 'error');
                return;
            }

            if (isEdit) {
                this.updateRow(data.partner);
                this.setListStatus(this.msgUpdatedValue, 'success');
            } else {
                this.prependRow(data.partner);
                this.setListStatus(this.msgCreatedValue, 'success');
            }

            this.initialSnapshot = this.snapshot();
            this.close();
        } catch (error) {
            this.setStatus(this.msgErrorValue, 'error');
        }
    }

    async deletePartner(event) {
        event.preventDefault();
        const partnerId = event.currentTarget.dataset.partnerId;
        if (!partnerId) {
            return;
        }

        if (!window.confirm(this.msgDeleteConfirmValue)) {
            return;
        }

        const deleteUrl = this.deleteUrlTemplateValue.replace('__ID__', String(partnerId));

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

            const row = this.tableBodyTarget.querySelector(`tr[data-partner-id="${partnerId}"]`);
            if (row) {
                row.remove();
            }

            this.ensureEmptyRow();
            this.setListStatus(this.msgDeletedValue, 'success');
        } catch (error) {
            this.setListStatus(this.msgErrorValue, 'error');
        }
    }

    prependRow(partner) {
        if (this.hasEmptyRowTarget) {
            this.emptyRowTarget.remove();
        }

        const row = this.buildRow(partner);
        this.tableBodyTarget.prepend(row);
    }

    updateRow(partner) {
        const row = this.tableBodyTarget.querySelector(`tr[data-partner-id="${partner.id}"]`);
        if (!row) {
            this.prependRow(partner);
            return;
        }

        row.dataset.partnerName = partner.name || '';
        row.dataset.partnerUrl = partner.url || '';
        row.dataset.partnerEmail = partner.email || '';
        row.dataset.partnerPhone = partner.phone || '';
        row.dataset.partnerCreated = partner.createdAt || '';

        row.querySelector('[data-field="name"]').textContent = partner.name;
        row.querySelector('[data-field="url"]').textContent = partner.url;
        row.querySelector('[data-field="email"]').textContent = partner.email || '—';
        row.querySelector('[data-field="phone"]').textContent = partner.phone || '—';
    }

    buildRow(partner) {
        const row = document.createElement('tr');
        row.dataset.partnerId = String(partner.id);
        row.dataset.partnerName = partner.name || '';
        row.dataset.partnerUrl = partner.url || '';
        row.dataset.partnerEmail = partner.email || '';
        row.dataset.partnerPhone = partner.phone || '';
        row.dataset.partnerCreated = partner.createdAt || '';
        row.innerHTML = `
            <td>#${this.escapeHtml(String(partner.id))}</td>
            <td data-field="name">${this.escapeHtml(partner.name)}</td>
            <td class="url-cell" data-field="url">${this.escapeHtml(partner.url)}</td>
            <td data-field="email">${this.escapeHtml(partner.email || '—')}</td>
            <td data-field="phone">${this.escapeHtml(partner.phone || '—')}</td>
            <td data-field="created">${this.escapeHtml(partner.createdAt)}</td>
            <td class="row-actions">
                <button
                    type="button"
                    class="btn-link-action"
                    data-action="partner-form#openEdit"
                    data-partner-id="${this.escapeHtml(String(partner.id))}"
                >${this.escapeHtml(this.msgEditValue)}</button>
                <button
                    type="button"
                    class="btn-link-danger"
                    data-action="partner-form#deletePartner"
                    data-partner-id="${this.escapeHtml(String(partner.id))}"
                >${this.escapeHtml(this.msgDeleteValue)}</button>
            </td>
        `;

        return row;
    }

    ensureEmptyRow() {
        const rows = this.tableBodyTarget.querySelectorAll('tr[data-partner-id]');
        if (rows.length > 0 || this.hasEmptyRowTarget) {
            return;
        }

        const emptyRow = document.createElement('tr');
        emptyRow.setAttribute('data-partner-form-target', 'emptyRow');
        emptyRow.innerHTML = `<td colspan="7">${this.escapeHtml(this.emptyTextValue)}</td>`;
        this.tableBodyTarget.append(emptyRow);
    }

    resetForm() {
        this.formTarget.reset();
        this.idInputTarget.value = '';
        this.clearStatus();
        this.initialSnapshot = this.snapshot();
    }

    snapshot() {
        return [
            this.idInputTarget.value.trim(),
            this.nameInputTarget.value.trim(),
            this.urlInputTarget.value.trim(),
            this.emailInputTarget.value.trim(),
            this.phoneInputTarget.value.trim(),
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
            'admin.cabinet.partners.form.name_required': this.msgNameRequiredValue,
            'admin.cabinet.partners.form.url_required': this.msgUrlRequiredValue,
            'admin.cabinet.partners.form.email_invalid': this.msgEmailInvalidValue,
            'admin.cabinet.partners.created': this.msgCreatedValue,
            'admin.cabinet.partners.updated': this.msgUpdatedValue,
            'admin.cabinet.partners.deleted': this.msgDeletedValue,
            'admin.cabinet.partners.delete_forbidden': this.msgDeleteForbiddenValue,
            'admin.cabinet.partners.not_found': this.msgNotFoundValue,
            'admin.cabinet.partners.form.error': this.msgErrorValue,
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
