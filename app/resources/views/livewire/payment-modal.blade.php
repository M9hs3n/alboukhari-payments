<div>
    <div
        wire:ignore
        x-data="paymentModal(@js([
            'monthNames' => $monthNames,
            'lang' => app()->getLocale(),
            'i18n' => [
                'title'          => __('payment.title'),
                'due'            => __('payment.due'),
                'paid_so_far'    => __('payment.paid_so_far'),
                'remaining'      => __('payment.remaining'),
                'amount'         => __('payment.amount'),
                'method'         => __('payment.method'),
                'shortcuts'      => __('payment.shortcuts'),
                'method_cash'    => __('payment.method_cash'),
                'method_bank'    => __('payment.method_bank'),
                'date'           => __('payment.date'),
                'note'           => __('payment.note'),
                'cancel'         => __('payment.cancel'),
                'save'           => __('payment.save'),
                'save_and_next'  => __('payment.save_and_next'),
                'existing'       => __('Existing payments this month'),
                'confirm_delete' => __('common.confirm'),
                'error_invalid'  => __('Could not save payment'),
            ],
        ]))"
        @open-payment-modal.window="open($event.detail)"
        @open-payment-modal-fast.window="open($event.detail)"
        @payment-saved.window="if (!_savingSelf) close()"
    >
        <template x-if="isOpen">
            <div
                class="modal-backdrop"
                @click.self="close()"
                @keydown.window.escape="close()"
                @keydown.window.ctrl.enter.prevent="save(true)"
            >
                <div class="modal-box" @click.stop>
                    <div class="modal-header">
                        <h3>
                            💶 <span x-text="i18n.title"></span> &mdash;
                            <span x-text="studentName"></span>
                            <span class="text-muted fw-600">/ <span x-text="monthName"></span> <span x-text="year"></span></span>
                        </h3>
                        <button type="button" class="btn btn-sm btn-ghost" @click="close()" aria-label="Close">✕</button>
                    </div>

                    <div class="modal-body">
                        <div class="summary-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:14px">
                            <div class="summary-item">
                                <div class="label">📊 <span x-text="i18n.due"></span></div>
                                <div class="value" x-text="fmt(due)"></div>
                            </div>
                            <div class="summary-item">
                                <div class="label" x-text="i18n.paid_so_far"></div>
                                <div class="value" x-text="fmt(paid)"></div>
                            </div>
                            <div class="summary-item">
                                <div class="label" x-text="i18n.remaining"></div>
                                <div
                                    class="value"
                                    :style="{ color: remaining() > 0 ? 'var(--color-danger)' : 'var(--color-success)' }"
                                    x-text="fmt(remaining())"
                                ></div>
                            </div>
                        </div>

                        <template x-if="loadingExisting">
                            <div style="padding:10px;text-align:center;color:var(--color-text-muted);font-size:12px">⏳</div>
                        </template>

                        <template x-if="!loadingExisting && existingPayments.length > 0">
                            <div style="margin-bottom:14px;padding:10px;background:var(--color-warning-soft);border-radius:var(--radius)">
                                <strong class="fs-xs" style="text-transform:uppercase;letter-spacing:0.06em" x-text="i18n.existing + ':'"></strong>
                                <table style="width:100%;margin-top:6px;font-size:12px">
                                    <template x-for="p in existingPayments" :key="p.id">
                                        <tr>
                                            <td style="padding:4px" x-text="p.paid_at"></td>
                                            <td style="padding:4px" x-text="p.method_icon + ' ' + p.method_label"></td>
                                            <td style="padding:4px;text-align:end;font-weight:700" x-text="fmt(p.amount)"></td>
                                            <td style="padding:4px">
                                                <button type="button" class="btn btn-sm" @click="editExisting(p)">✏️</button>
                                                <button type="button" class="btn btn-sm btn-soft-danger" @click="if (confirm(i18n.confirm_delete)) deleteOne(p.id)">🗑️</button>
                                            </td>
                                        </tr>
                                    </template>
                                </table>
                            </div>
                        </template>

                        <div class="form-group">
                            <label x-text="i18n.amount + ' (€)'"></label>
                            <input
                                x-ref="amountInput"
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-input"
                                x-model.number="amount"
                                style="font-size:22px;font-weight:700;text-align:center;padding:12px"
                                @keydown.enter.prevent="save(false)"
                                required
                            >
                        </div>

                        <div class="form-group">
                            <label>
                                <span x-text="i18n.method"></span>
                                <small class="text-muted" x-text="i18n.shortcuts"></small>
                            </label>
                            <div
                                class="method-toggle"
                                @keydown.window.n.prevent="method = 'cash'"
                                @keydown.window.b.prevent="method = 'bank'"
                            >
                                <button type="button" class="cash" :class="{ active: method === 'cash' }" @click="method = 'cash'">
                                    💵 <span x-text="i18n.method_cash"></span>
                                </button>
                                <button type="button" class="bank" :class="{ active: method === 'bank' }" @click="method = 'bank'">
                                    🏦 <span x-text="i18n.method_bank"></span>
                                </button>
                            </div>
                        </div>

                        <div class="form-row cols-2">
                            <div class="form-group">
                                <label x-text="i18n.date"></label>
                                <input type="date" class="form-input" x-model="paid_at" required>
                            </div>
                            <div class="form-group">
                                <label x-text="i18n.note"></label>
                                <input type="text" class="form-input" x-model="note" placeholder="...">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn" @click="close()">
                            <span x-text="i18n.cancel"></span> (Esc)
                        </button>
                        <button type="button" class="btn btn-soft-success" @click="save(false)" :disabled="saving">
                            <span x-show="!saving">💾 <span x-text="i18n.save"></span></span>
                            <span x-show="saving" x-cloak>…</span>
                        </button>
                        <button type="button" class="btn btn-primary" @click="save(true)" :disabled="saving" title="Ctrl+Enter">
                            ↩ <span x-text="i18n.save_and_next"></span>
                        </button>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
    if (!window._paymentModalRegistered) {
        window._paymentModalRegistered = true;
        document.addEventListener('alpine:init', () => {
            Alpine.data('paymentModal', (config) => ({
                isOpen: false,
                studentId: null,
                year: null,
                month: null,
                monthName: '',
                studentName: '',
                due: 0,
                paid: 0,
                amount: 0,
                method: 'cash',
                note: '',
                paid_at: '',
                editingPaymentId: null,
                existingPayments: [],
                loadingExisting: false,
                saving: false,
                _savingSelf: false,
                monthNames: config.monthNames || {},
                i18n: config.i18n || {},
                lang: config.lang || 'en',

                remaining() { return (this.due || 0) - (this.paid || 0); },

                fmt(v) {
                    const n = parseFloat(v) || 0;
                    return new Intl.NumberFormat(this.lang === 'ar' ? 'ar' : (this.lang === 'nl' ? 'nl-NL' : 'en-GB'), {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2,
                    }).format(n) + ' €';
                },

                async open(detail) {
                    if (!detail || !detail.studentId || !detail.year || !detail.month) return;
                    this.studentId = parseInt(detail.studentId);
                    this.year = parseInt(detail.year);
                    this.month = parseInt(detail.month);
                    this.monthName = this.monthNames[this.month] || '';

                    if (detail.studentName !== undefined && detail.due !== undefined && detail.paid !== undefined) {
                        this.studentName = detail.studentName;
                        this.due = parseFloat(detail.due) || 0;
                        this.paid = parseFloat(detail.paid) || 0;
                        this._show();
                    } else {
                        try {
                            const data = await this.$wire.prefetchModalData(this.studentId, this.year, this.month);
                            this.studentName = data.studentName;
                            this.due = parseFloat(data.due) || 0;
                            this.paid = parseFloat(data.paid) || 0;
                            this._show();
                        } catch (e) { console.error('[paymentModal] prefetch failed', e); }
                    }
                },

                _show() {
                    const remaining = this.due - this.paid;
                    this.amount = remaining > 0 ? remaining : this.due;
                    this.method = 'cash';
                    this.note = '';
                    this.paid_at = new Date().toISOString().slice(0, 10);
                    this.editingPaymentId = null;
                    this.existingPayments = [];
                    this.isOpen = true;

                    this.$nextTick(() => { this.$refs.amountInput?.focus(); this.$refs.amountInput?.select(); });
                    this._loadExisting();
                },

                async _loadExisting() {
                    this.loadingExisting = true;
                    try {
                        this.existingPayments = await this.$wire.loadExistingPayments(this.studentId, this.year, this.month);
                    } catch (e) { console.error('[paymentModal] loadExisting failed', e); this.existingPayments = []; }
                    this.loadingExisting = false;
                },

                editExisting(p) {
                    this.editingPaymentId = p.id;
                    this.amount = parseFloat(p.amount);
                    this.method = p.method === 'legacy_zero' ? 'bank' : p.method;
                    this.note = p.note || '';
                    this.paid_at = p.paid_at;
                    this.$nextTick(() => this.$refs.amountInput?.focus());
                },

                close() {
                    this.isOpen = false;
                    this.saving = false;
                    this._savingSelf = false;
                },

                async deleteOne(paymentId) {
                    this._savingSelf = true;
                    try {
                        await this.$wire.deletePaymentAlpine(paymentId, this.studentId);
                        await this._loadExisting();
                        // Refresh due/paid summary
                        const data = await this.$wire.prefetchModalData(this.studentId, this.year, this.month);
                        this.due = parseFloat(data.due) || 0;
                        this.paid = parseFloat(data.paid) || 0;
                    } catch (e) { console.error(e); alert(this.i18n.error_invalid || 'Error'); }
                    this._savingSelf = false;
                },

                async save(goNext) {
                    if (this.saving) return;
                    const amt = parseFloat(this.amount);
                    if (isNaN(amt) || amt < 0) { alert(this.i18n.error_invalid || 'Invalid amount'); return; }
                    if (!['cash', 'bank'].includes(this.method)) { alert(this.i18n.error_invalid || 'Invalid method'); return; }
                    if (!this.paid_at) { alert(this.i18n.error_invalid || 'Date required'); return; }

                    this.saving = true;
                    this._savingSelf = true;
                    try {
                        const res = await this.$wire.savePaymentAlpine({
                            studentId: this.studentId,
                            year: this.year,
                            month: this.month,
                            amount: amt,
                            method: this.method,
                            note: this.note,
                            paid_at: this.paid_at,
                            editingPaymentId: this.editingPaymentId,
                        });
                        if (!res || res.ok !== true) {
                            alert((this.i18n.error_invalid || 'Error') + ': ' + (res?.error || 'unknown'));
                            this.saving = false;
                            this._savingSelf = false;
                            return;
                        }

                        if (goNext) {
                            // Try to find the next visible cell for same month in DOM
                            const currentRow = document.querySelector(`tr[wire\\:key="row-${this.studentId}"]`);
                            let nextRow = currentRow?.nextElementSibling;
                            while (nextRow && (nextRow.style.display === 'none' || nextRow.classList.contains('hidden'))) {
                                nextRow = nextRow.nextElementSibling;
                            }
                            const nextCell = nextRow?.querySelector(`td.cell-month[data-month="${this.month}"]`);
                            if (nextCell) {
                                this.close();
                                this.$nextTick(() => nextCell.click());
                                return;
                            }
                        }
                        this.close();
                    } catch (e) {
                        console.error('[paymentModal] save failed', e);
                        alert(this.i18n.error_invalid || 'Error');
                    } finally {
                        this.saving = false;
                        this._savingSelf = false;
                    }
                },
            }));
        });
    }
</script>
