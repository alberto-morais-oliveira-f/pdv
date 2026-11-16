<script setup>
import { inject, onMounted, ref, watch } from 'vue';
import { ApiError } from '../../api.js';
import { formatMoney } from '../../money.js';

const api = inject('pdvApi');
const t = inject('pdvT');

function today() {
    const now = new Date();
    const pad = (value) => String(value).padStart(2, '0');

    return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
}

const date = ref(today());
const sales = ref([]);
const loading = ref(true);
const loadError = ref('');
const notice = ref('');

const selected = ref(null);
const detailLoading = ref(false);
const detailError = ref('');
const confirmingCancel = ref(false);
const cancelling = ref(false);
const cancelError = ref('');

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api.get('/sales', { date: date.value });
        sales.value = response.data ?? [];
    } catch (error) {
        loadError.value = error instanceof ApiError ? error.firstError : t('common.error');
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(date, () => {
    selected.value = null;
    load();
});

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

async function openDetail(sale) {
    selected.value = sale;
    detailError.value = '';
    cancelError.value = '';
    confirmingCancel.value = false;
    detailLoading.value = true;

    try {
        const response = await api.get(`/sales/${sale.id}`);
        selected.value = response.data;
    } catch (error) {
        detailError.value = error instanceof ApiError ? error.firstError : t('common.error');
    } finally {
        detailLoading.value = false;
    }
}

async function cancelSale() {
    cancelError.value = '';
    cancelling.value = true;

    try {
        const response = await api.delete(`/sales/${selected.value.id}`);
        selected.value = response?.data ?? selected.value;
        confirmingCancel.value = false;
        notice.value = t('sales.cancelled_ok');
        await load();
    } catch (error) {
        cancelError.value = error instanceof ApiError ? error.firstError : t('common.error');
    } finally {
        cancelling.value = false;
    }
}
</script>

<template>
    <div class="pdv-sales">
        <div class="pdv-row">
            <h4>{{ t('sales.title') }}</h4>
            <label for="pdv-sales-date">{{ t('sales.date') }}</label>
            <input id="pdv-sales-date" v-model="date" type="date" class="form-control form-control-sm w-auto">
        </div>

        <div v-if="notice" class="pdv-alert pdv-alert--success" role="status">{{ notice }}</div>

        <div class="pdv-grid">
            <section class="pdv-panel">
                <p v-if="loading" class="pdv-muted">{{ t('common.loading') }}</p>
                <div v-else-if="loadError" class="pdv-alert pdv-alert--error" role="alert">
                    {{ loadError }}
                    <button type="button" class="btn btn-sm btn-light ms-2" @click="load">{{ t('common.retry') }}</button>
                </div>
                <p v-else-if="sales.length === 0" class="pdv-muted">{{ t('sales.empty') }}</p>

                <table v-else class="pdv-table">
                    <thead>
                        <tr>
                            <th>{{ t('sales.time') }}</th>
                            <th>{{ t('sales.status') }}</th>
                            <th class="pdv-num">{{ t('sales.gross') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="sale in sales" :key="sale.id">
                            <td>{{ formatTime(sale.occurred_at) }}</td>
                            <td>{{ t(`sales.statuses.${sale.status}`, sale.status) }}</td>
                            <td class="pdv-num">{{ formatMoney(sale.totals.gross) }}</td>
                            <td class="pdv-num">
                                <button type="button" class="btn btn-sm btn-outline-primary" @click="openDetail(sale)">{{ t('sales.details') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section v-if="selected" class="pdv-panel">
                <h5>{{ t('sales.details') }} · {{ formatTime(selected.occurred_at) }}</h5>
                <p v-if="detailLoading" class="pdv-muted">{{ t('common.loading') }}</p>
                <div v-if="detailError" class="pdv-alert pdv-alert--error" role="alert">{{ detailError }}</div>

                <p>{{ t('sales.status') }}: <strong>{{ t(`sales.statuses.${selected.status}`, selected.status) }}</strong></p>

                <h6>{{ t('sales.items') }}</h6>
                <table class="pdv-table">
                    <tbody>
                        <tr v-for="(item, index) in selected.items" :key="index">
                            <td>{{ item.description }}</td>
                            <td class="pdv-num">{{ item.qty }} × {{ formatMoney(item.unit_amount) }}</td>
                            <td class="pdv-num">{{ formatMoney(item.total_amount) }}</td>
                        </tr>
                    </tbody>
                </table>

                <h6>{{ t('sales.payments') }}</h6>
                <table class="pdv-table">
                    <tbody>
                        <tr v-for="(payment, index) in selected.payments" :key="index">
                            <td>{{ t(`counter.methods.${payment.method}`, payment.method) }}</td>
                            <td class="pdv-num">{{ formatMoney(payment.amount) }}</td>
                        </tr>
                    </tbody>
                </table>

                <p>
                    {{ t('sales.gross') }}: {{ formatMoney(selected.totals.gross) }} ·
                    {{ t('sales.fees') }}: {{ formatMoney(selected.totals.fees) }} ·
                    {{ t('sales.net') }}: {{ formatMoney(selected.totals.net) }}
                </p>

                <div v-if="cancelError" class="pdv-alert pdv-alert--error" role="alert">{{ cancelError }}</div>

                <div v-if="selected.status === 'completed'" class="pdv-row">
                    <template v-if="confirmingCancel">
                        <span>{{ t('sales.confirm_cancel') }}</span>
                        <button type="button" class="btn btn-sm btn-danger" :disabled="cancelling" @click="cancelSale">{{ t('common.yes') }}</button>
                        <button type="button" class="btn btn-sm btn-light" :disabled="cancelling" @click="confirmingCancel = false">{{ t('common.no') }}</button>
                    </template>
                    <button v-else type="button" class="btn btn-sm btn-outline-danger" @click="confirmingCancel = true">{{ t('sales.cancel_sale') }}</button>
                </div>
            </section>
        </div>
    </div>
</template>
