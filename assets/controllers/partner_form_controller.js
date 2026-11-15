import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'modal',
        'confirmModal',
        'modalTitle',
        'form',
        'idInput',
        'nameInput',
        'emailInput',
        'phoneInput',
        'ownPercentInput',
        'level1PercentInput',
        'level2PercentInput',
        'pagesList',
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
        msgPagesRequired: String,
        msgPageUrlRequired: String,
        msgPageCodeInvalid: String,
        msgPageCodeDuplicate: String,
        msgPageCodeTaken: String,
        msgPageUrlDuplicate: String,
        msgPageDeleteForbidden: String,
        msgEmailInvalid: String,
        msgPercentInvalid: String,
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
        msgAddPage: String,
        msgRemovePage: String,
        msgPageCodeLabel: String,
        msgPageUrlLabel: String,
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
        this.emailInputTarget.value = row.dataset.partnerEmail || '';
        this.phoneInputTarget.value = row.dataset.partnerPhone || '';
        this.ownPercentInputTarget.value = row.dataset.partnerOwnPercent || '10.00';
        this.level1PercentInputTarget.value = row.dataset.partnerLevel1Percent || '5.00';
        this.level2PercentInputTarget.value = row.dataset.partnerLevel2Percent || '2.00';
        this.renderPages(this.parsePages(row.dataset.partnerPages));
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

    addPage() {
        this.appendPageRow({ id: '', code: '', url: '' });
        this.clearStatus();
    }

    removePage(event) {
        const row = event.currentTarget.closest('[data-page-row]');
        if (!row) {
            return;
        }
        row.remove();
        if (this.pagesListTarget.querySelectorAll('[data-page-row]').length === 0) {
            this.appendPageRow({ id: '', code: '', url: '' });
        }
        this.clearStatus();
    }

    async save(event) {
        event.preventDefault();

        const name = this.nameInputTarget.value.trim();
        const email = this.emailInputTarget.value.trim();
        const phone = this.phoneInputTarget.value.trim();
        const ownPercent = this.ownPercentInputTarget.value.trim();
        const level1Percent = this.level1PercentInputTarget.value.trim();
        const level2Percent = this.level2PercentInputTarget.value.trim();
        const pages = this.collectPages();

        if (!name) {
            this.setStatus(this.msgNameRequiredValue, 'error');
            return;
        }

        if (![ownPercent, level1Percent, level2Percent].every((value) => this.isValidPercent(value))) {
            this.setStatus(this.msgPercentInvalidValue, 'error');
            return;
        }

        if (pages.length === 0) {
            this.setStatus(this.msgPagesRequiredValue, 'error');
            return;
        }

        for (const page of pages) {
            if (!page.url) {
                this.setStatus(this.msgPageUrlRequiredValue, 'error');
                return;
            }
            if (!/^[a-z0-9]{2}$/i.test(page.code)) {
                this.setStatus(this.msgPageCodeInvalidValue, 'error');
                return;
            }
        }

        const codes = pages.map((page) => page.code.toLowerCase());
        if (new Set(codes).size !== codes.length) {
            this.setStatus(this.msgPageCodeDuplicateValue, 'error');
            return;
        }

        const urls = pages.map((page) => page.url.replace(/\/$/, ''));
        if (new Set(urls).size !== urls.length) {
            this.setStatus(this.msgPageUrlDuplicateValue, 'error');
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
                body: JSON.stringify({
                    name,
                    email,
                    phone,
                    ownPercent,
                    level1Percent,
                    level2Percent,
                    pages,
                }),
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
        row.dataset.partnerEmail = partner.email || '';
        row.dataset.partnerPhone = partner.phone || '';
        row.dataset.partnerOwnPercent = partner.ownPercent || '10.00';
        row.dataset.partnerLevel1Percent = partner.level1Percent || '5.00';
        row.dataset.partnerLevel2Percent = partner.level2Percent || '2.00';
        row.dataset.partnerCreated = partner.createdAt || '';
        row.dataset.partnerPages = JSON.stringify(partner.pages || []);

        row.querySelector('[data-field="name"]').textContent = partner.name;
        row.querySelector('[data-field="pages"]').innerHTML = this.formatPagesHtml(partner.pages || []);
        row.querySelector('[data-field="email"]').textContent = partner.email || '—';
        row.querySelector('[data-field="phone"]').textContent = partner.phone || '—';
    }

    buildRow(partner) {
        const row = document.createElement('tr');
        row.dataset.partnerId = String(partner.id);
        row.dataset.partnerName = partner.name || '';
        row.dataset.partnerEmail = partner.email || '';
        row.dataset.partnerPhone = partner.phone || '';
        row.dataset.partnerOwnPercent = partner.ownPercent || '10.00';
        row.dataset.partnerLevel1Percent = partner.level1Percent || '5.00';
        row.dataset.partnerLevel2Percent = partner.level2Percent || '2.00';
        row.dataset.partnerCreated = partner.createdAt || '';
        row.dataset.partnerPages = JSON.stringify(partner.pages || []);
        row.innerHTML = `
            <td>#${this.escapeHtml(String(partner.id))}</td>
            <td data-field="name">${this.escapeHtml(partner.name)}</td>
            <td class="url-cell" data-field="pages">${this.formatPagesHtml(partner.pages || [])}</td>
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

    formatPagesHtml(pages) {
        if (!pages.length) {
            return '—';
        }

        return pages
            .map((page) => `<div>[${this.escapeHtml(page.code)}] ${this.escapeHtml(page.url)}</div>`)
            .join('');
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
        this.ownPercentInputTarget.value = '10';
        this.level1PercentInputTarget.value = '5';
        this.level2PercentInputTarget.value = '2';
        this.renderPages([{ id: '', code: '', url: '' }]);
        this.clearStatus();
        this.initialSnapshot = this.snapshot();
    }

    isValidPercent(value) {
        if (value === '' || Number.isNaN(Number(value))) {
            return false;
        }
        const number = Number(value);
        return number >= 0 && number <= 100;
    }

    renderPages(pages) {
        this.pagesListTarget.innerHTML = '';
        const list = pages.length ? pages : [{ id: '', code: '', url: '' }];
        list.forEach((page) => this.appendPageRow(page));
    }

    appendPageRow(page) {
        const row = document.createElement('div');
        row.className = 'page-row';
        row.dataset.pageRow = '1';
        row.innerHTML = `
            <input type="hidden" data-page-id value="${this.escapeHtml(String(page.id || ''))}">
            <div class="page-row-fields">
                <div class="page-code-field">
                    <label>${this.escapeHtml(this.msgPageCodeLabelValue)}</label>
                    <input
                        type="text"
                        data-page-code
                        data-action="input->partner-form#onChange"
                        value="${this.escapeHtml(page.code || '')}"
                        maxlength="2"
                        placeholder="ab"
                        required
                    >
                </div>
                <div class="page-url-field">
                    <label>${this.escapeHtml(this.msgPageUrlLabelValue)}</label>
                    <input
                        type="url"
                        data-page-url
                        data-action="input->partner-form#onChange"
                        value="${this.escapeHtml(page.url || '')}"
                        maxlength="255"
                        placeholder="https://example.com/page"
                        required
                    >
                </div>
                <button
                    type="button"
                    class="btn-link-danger page-remove"
                    data-action="partner-form#removePage"
                >${this.escapeHtml(this.msgRemovePageValue)}</button>
            </div>
        `;
        this.pagesListTarget.append(row);
    }

    collectPages() {
        return Array.from(this.pagesListTarget.querySelectorAll('[data-page-row]')).map((row) => {
            const id = row.querySelector('[data-page-id]')?.value.trim() || '';
            const code = row.querySelector('[data-page-code]')?.value.trim() || '';
            const url = row.querySelector('[data-page-url]')?.value.trim() || '';
            return {
                id: id ? Number(id) : null,
                code,
                url,
            };
        }).filter((page) => page.code || page.url);
    }

    parsePages(raw) {
        if (!raw) {
            return [];
        }
        try {
            const parsed = JSON.parse(raw);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    snapshot() {
        return [
            this.idInputTarget.value.trim(),
            this.nameInputTarget.value.trim(),
            this.emailInputTarget.value.trim(),
            this.phoneInputTarget.value.trim(),
            this.ownPercentInputTarget.value.trim(),
            this.level1PercentInputTarget.value.trim(),
            this.level2PercentInputTarget.value.trim(),
            JSON.stringify(this.collectPages()),
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
            'admin.cabinet.partners.form.pages_required': this.msgPagesRequiredValue,
            'admin.cabinet.partners.form.page_url_required': this.msgPageUrlRequiredValue,
            'admin.cabinet.partners.form.page_code_invalid': this.msgPageCodeInvalidValue,
            'admin.cabinet.partners.form.page_code_duplicate': this.msgPageCodeDuplicateValue,
            'admin.cabinet.partners.form.page_code_taken': this.msgPageCodeTakenValue,
            'admin.cabinet.partners.form.page_url_duplicate': this.msgPageUrlDuplicateValue,
            'admin.cabinet.partners.form.page_delete_forbidden': this.msgPageDeleteForbiddenValue,
            'admin.cabinet.partners.form.email_invalid': this.msgEmailInvalidValue,
            'admin.cabinet.partners.form.percent_invalid': this.msgPercentInvalidValue,
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
