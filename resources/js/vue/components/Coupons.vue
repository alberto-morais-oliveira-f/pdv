<script setup>
import { inject, onMounted, ref } from 'vue';
import { ApiError } from '../../api.js';
import { centsToInput, formatMoney, parseCents } from '../../money.js';

const api = inject('pdvApi');
const t = inject('pdvT');

const coupons = ref([]);
const loading = ref(true);
const loadError = ref('');
const notice = ref('');

const editingId = ref(null);
const showForm = ref(false);
const form = ref(emptyForm());
const saving = ref(false);
const formError = ref('');
const fieldErrors = ref({});
const confirmDeleteId = ref(null);
const actionError = ref('');

function emptyForm() {
    return {
        code: '',
        label: '',
        type: 'percent',
        value_display: '',
        active: true,
        starts_at: '',
        ends_at: '',
        max_uses: '',
    };
}

function toDateInput(iso) {
    return iso ? String(iso).slice(0, 10) : '';
}

function displayValue(coupon) {
    if (coupon.type === 'percent') {
        return String(coupon.value / 100);
    }

    return centsToInput(coupon.value);
}

function formatCouponValue(coupon) {
    if (coupon.type === 'percent') {
        return `${String(coupon.value / 100).replace('.', ',')}%`;
    }

    return formatMoney(coupon.value);
}

function parsePercentBps(text) {
    const number = Number.parseFloat(String(text ?? '').trim().replace(',', '.'));

    return Number.isFinite(number) && number > 0 ? Math.round(number * 100) : 0;
}

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api.get('/coupons');
        coupons.value = response.data ?? [];
    } catch (error) {
        loadError.value = error instanceof ApiError ? error.firstError : t('common.error');
    } finally {
        loading.value = false;
    }
}

onMounted(load);

function startCreate() {
    editingId.value = null;
    form.value = emptyForm();
    resetFormState();
    showForm.value = true;
}

function startEdit(coupon) {
    editingId.value = coupon.id;
    form.value = {
        code: coupon.code,
        label: coupon.label ?? '',
        type: coupon.type,
        value_display: displayValue(coupon),
        active: coupon.active,
        starts_at: toDateInput(coupon.starts_at),
        ends_at: toDateInput(coupon.ends_at),
        max_uses: coupon.max_uses == null ? '' : String(coupon.max_uses),
    };
    resetFormState();
    showForm.value = true;
}

function resetFormState() {
    formError.value = '';
    fieldErrors.value = {};
}

function closeForm() {
    showForm.value = false;
    editingId.value = null;
}

function payloadFromForm() {
    const type = form.value.type;
    const value = type === 'percent'
        ? parsePercentBps(form.value.value_display)
        : parseCents(form.value.value_display);

    return {
        code: form.value.code,
        label: form.value.label === '' ? null : form.value.label,
        type,
        value,
        active: Boolean(form.value.active),
        starts_at: form.value.starts_at === '' ? null : form.value.starts_at,
        ends_at: form.value.ends_at === '' ? null : form.value.ends_at,
        max_uses: form.value.max_uses === '' ? null : Number(form.value.max_uses),
    };
}

async function save() {
    resetFormState();
    saving.value = true;

    const payload = payloadFromForm();

    try {
        if (editingId.value === null) {
            await api.post('/coupons', payload);
        } else {
            await api.put(`/coupons/${editingId.value}`, payload);
        }

        notice.value = t('coupons.saved');
        closeForm();
        await load();
    } catch (error) {
        if (error instanceof ApiError) {
            fieldErrors.value = error.errors;
            formError.value = error.firstError;
        } else {
            formError.value = t('common.error');
        }
    } finally {
        saving.value = false;
    }
}

async function remove(coupon) {
    actionError.value = '';

    try {
        await api.delete(`/coupons/${coupon.id}`);
        confirmDeleteId.value = null;
        notice.value = t('coupons.deleted');
        await load();
    } catch (error) {
        actionError.value = error instanceof ApiError ? error.firstError : t('common.error');
    }
}
</script>

<template>
    <div class="pdv-coupons" data-testid="pdv-coupons">
        <div class="pdv-row">
            <h4>{{ t('coupons.title') }}</h4>
            <button v-if="!showForm" type="button" class="btn btn-primary btn-sm" data-testid="pdv-coupon-new" @click="startCreate">
                {{ t('coupons.new') }}
            </button>
        </div>

        <div v-if="notice" class="pdv-alert pdv-alert--success" role="status">{{ notice }}</div>
        <div v-if="actionError" class="pdv-alert pdv-alert--error" role="alert">{{ actionError }}</div>

        <form v-if="showForm" class="pdv-panel" data-testid="pdv-coupon-form" @submit.prevent="save">
            <h5>{{ editingId === null ? t('coupons.new') : t('coupons.edit') }}</h5>
            <div v-if="formError" class="pdv-alert pdv-alert--error" role="alert">{{ formError }}</div>

            <div class="pdv-field">
                <label for="coupon-code">{{ t('coupons.code') }}</label>
                <input id="coupon-code" v-model="form.code" type="text" class="form-control text-uppercase" maxlength="64" required autocomplete="off">
                <small v-if="fieldErrors.code" class="text-danger">{{ fieldErrors.code[0] }}</small>
            </div>
            <div class="pdv-field">
                <label for="coupon-label">{{ t('coupons.label') }}</label>
                <input id="coupon-label" v-model="form.label" type="text" class="form-control" maxlength="255">
                <small v-if="fieldErrors.label" class="text-danger">{{ fieldErrors.label[0] }}</small>
            </div>
            <div class="pdv-field">
                <label for="coupon-type">{{ t('coupons.type') }}</label>
                <select id="coupon-type" v-model="form.type" class="form-select">
                    <option value="percent">{{ t('coupons.types.percent') }}</option>
                    <option value="fixed">{{ t('coupons.types.fixed') }}</option>
                </select>
                <small v-if="fieldErrors.type" class="text-danger">{{ fieldErrors.type[0] }}</small>
            </div>
            <div class="pdv-field">
                <label for="coupon-value">{{ form.type === 'percent' ? t('coupons.value_percent') : t('coupons.value_fixed') }}</label>
                <input
                    id="coupon-value"
                    v-model="form.value_display"
                    :type="form.type === 'percent' ? 'number' : 'text'"
                    class="form-control"
                    :min="form.type === 'percent' ? '0.01' : undefined"
                    :max="form.type === 'percent' ? '100' : undefined"
                    :step="form.type === 'percent' ? '0.01' : undefined"
                    :inputmode="form.type === 'percent' ? 'decimal' : 'decimal'"
                    required
                >
                <small v-if="fieldErrors.value" class="text-danger">{{ fieldErrors.value[0] }}</small>
            </div>
            <div class="pdv-field">
                <label for="coupon-starts">{{ t('coupons.starts_at') }}</label>
                <input id="coupon-starts" v-model="form.starts_at" type="date" class="form-control">
                <small v-if="fieldErrors.starts_at" class="text-danger">{{ fieldErrors.starts_at[0] }}</small>
            </div>
            <div class="pdv-field">
                <label for="coupon-ends">{{ t('coupons.ends_at') }}</label>
                <input id="coupon-ends" v-model="form.ends_at" type="date" class="form-control">
                <small v-if="fieldErrors.ends_at" class="text-danger">{{ fieldErrors.ends_at[0] }}</small>
            </div>
            <div class="pdv-field">
                <label for="coupon-max-uses">{{ t('coupons.max_uses') }}</label>
                <input id="coupon-max-uses" v-model="form.max_uses" type="number" min="1" step="1" class="form-control">
                <small v-if="fieldErrors.max_uses" class="text-danger">{{ fieldErrors.max_uses[0] }}</small>
            </div>
            <div class="pdv-field">
                <label><input v-model="form.active" type="checkbox"> {{ t('coupons.active') }}</label>
            </div>

            <div class="pdv-row">
                <button type="submit" class="btn btn-primary" :disabled="saving">{{ t('common.save') }}</button>
                <button type="button" class="btn btn-light" :disabled="saving" @click="closeForm">{{ t('common.cancel') }}</button>
            </div>
        </form>

        <p v-if="loading" class="pdv-muted">{{ t('common.loading') }}</p>
        <div v-else-if="loadError" class="pdv-alert pdv-alert--error" role="alert">
            {{ loadError }}
            <button type="button" class="btn btn-sm btn-light ms-2" @click="load">{{ t('common.retry') }}</button>
        </div>
        <p v-else-if="coupons.length === 0" class="pdv-muted">{{ t('coupons.empty') }}</p>

        <table v-else class="pdv-table" data-testid="pdv-coupons-table">
            <thead>
                <tr>
                    <th>{{ t('coupons.code') }}</th>
                    <th>{{ t('coupons.label') }}</th>
                    <th>{{ t('coupons.type') }}</th>
                    <th class="pdv-num">{{ t('coupons.value') }}</th>
                    <th>{{ t('coupons.active') }}</th>
                    <th class="pdv-num">{{ t('coupons.uses') }}</th>
                    <th class="pdv-num">{{ t('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="coupon in coupons" :key="coupon.id">
                    <td>{{ coupon.code }}</td>
                    <td>{{ coupon.label || '—' }}</td>
                    <td>{{ t(`coupons.types.${coupon.type}`, coupon.type) }}</td>
                    <td class="pdv-num">{{ formatCouponValue(coupon) }}</td>
                    <td>{{ coupon.active ? t('coupons.active') : t('coupons.inactive') }}</td>
                    <td class="pdv-num">{{ coupon.uses_count }}{{ coupon.max_uses == null ? '' : ` / ${coupon.max_uses}` }}</td>
                    <td class="pdv-num">
                        <template v-if="confirmDeleteId === coupon.id">
                            <span>{{ t('coupons.confirm_delete') }}</span>
                            <button type="button" class="btn btn-sm btn-danger" @click="remove(coupon)">{{ t('common.yes') }}</button>
                            <button type="button" class="btn btn-sm btn-light" @click="confirmDeleteId = null">{{ t('common.no') }}</button>
                        </template>
                        <template v-else>
                            <button type="button" class="btn btn-sm btn-outline-primary" @click="startEdit(coupon)">{{ t('common.edit') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="confirmDeleteId = coupon.id">{{ t('common.delete') }}</button>
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
