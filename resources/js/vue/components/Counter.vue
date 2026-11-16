<script setup>
import { computed, inject, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ApiError } from '../../api.js';
import { centsToInput, formatMoney, parseCents } from '../../money.js';

const api = inject('pdvApi');
const t = inject('pdvT');

const DEBOUNCE_MS = 300;
const TYPE_FILTERS = ['all', 'subscription', 'service', 'product'];
const DISCOUNT_MODES = ['none', 'manual_fixed', 'manual_percent', 'coupon'];

const query = ref('');
const catalog = ref([]);
const searching = ref(false);
const searchError = ref('');
const typeFilter = ref('all');
const highlightPlans = ref(false);
let searchTimer = null;
let searchSeq = 0;

const cart = ref([]);
const payments = ref([]);
const terminals = ref([]);
const draft = ref({ method: 'cash', amount: '', terminal_id: '' });
const paymentError = ref('');

const customerQuery = ref('');
const customerResults = ref([]);
const customerSearching = ref(false);
const customerSearchError = ref('');
const customerHasSearched = ref(false);
const customer = ref(null);
let customerTimer = null;
let customerSeq = 0;

const history = ref([]);
const historyLoading = ref(false);
const historyError = ref('');
const upgradeNotice = ref('');
const upgradeNoticeKind = ref('success');
let historySeq = 0;

const discountMode = ref('none');
const discountFixed = ref('');
const discountPercent = ref('');
const couponCode = ref('');
const appliedCoupon = ref(null);
const couponApplying = ref(false);
const couponError = ref('');

const submitting = ref(false);
const submitError = ref('');
const completedSale = ref(null);

const total = computed(() => cart.value.reduce((sum, line) => sum + line.qty * line.unit_amount, 0));
const discountAmount = computed(() => resolveDiscount(total.value));
const payable = computed(() => Math.max(0, total.value - discountAmount.value));
const paid = computed(() => payments.value.reduce((sum, line) => sum + line.amount, 0));
const remaining = computed(() => payable.value - paid.value);
const canFinalize = computed(() => {
    if (submitting.value || cart.value.length === 0 || needsCustomer.value) {
        return false;
    }

    if (discountMode.value === 'coupon' && appliedCoupon.value === null) {
        return false;
    }

    if (remaining.value !== 0) {
        return false;
    }

    return payable.value === 0 || payments.value.length > 0;
});
const activeTerminals = computed(() => terminals.value.filter((terminal) => terminal.active));
const needsCustomer = computed(() => cart.value.some((line) => line.type === 'subscription') && customer.value === null);

const subscriptions = computed(() => filteredByType('subscription'));
const services = computed(() => filteredByType('service'));
const products = computed(() => filteredByType('product'));
const visibleCatalog = computed(() => {
    if (typeFilter.value === 'all') {
        return catalog.value;
    }

    return catalog.value.filter((item) => item.type === typeFilter.value);
});

function filteredByType(type) {
    return catalog.value.filter((item) => item.type === type);
}

function lineKey(item) {
    return `${item.type}|${item.source_type}|${item.source_id}`;
}

function typeLabel(type) {
    return t(`counter.types.${type}`);
}

function percentAmount(gross, bps) {
    const clamped = Math.min(10000, Math.max(0, bps));
    const amount = Math.floor(gross / 10000) * clamped + Math.floor((gross % 10000) * clamped / 10000);

    return Math.min(gross, Math.max(0, amount));
}

function parsePercentBps(text) {
    const number = Number.parseFloat(String(text ?? '').trim().replace(',', '.'));

    return Number.isFinite(number) && number > 0 ? Math.round(number * 100) : 0;
}

function couponDiscount(gross, coupon) {
    if (coupon.type === 'percent') {
        return percentAmount(gross, coupon.value);
    }

    return Math.min(gross, Math.max(0, coupon.value));
}

function resolveDiscount(gross) {
    if (discountMode.value === 'manual_fixed') {
        return Math.min(gross, Math.max(0, parseCents(discountFixed.value)));
    }

    if (discountMode.value === 'manual_percent') {
        return percentAmount(gross, parsePercentBps(discountPercent.value));
    }

    if (discountMode.value === 'coupon' && appliedCoupon.value) {
        return couponDiscount(gross, appliedCoupon.value);
    }

    return 0;
}

function setDiscountMode(mode) {
    discountMode.value = mode;
    couponError.value = '';

    if (mode !== 'manual_fixed') {
        discountFixed.value = '';
    }

    if (mode !== 'manual_percent') {
        discountPercent.value = '';
    }

    if (mode !== 'coupon') {
        couponCode.value = '';
        appliedCoupon.value = null;
    }
}

async function applyCoupon() {
    couponError.value = '';
    appliedCoupon.value = null;

    const code = couponCode.value.trim();

    if (code === '') {
        couponError.value = t('counter.discount_code_required');

        return;
    }

    couponApplying.value = true;

    try {
        const data = await api.get('/coupons/validate', { code, gross: total.value });
        appliedCoupon.value = data;
        couponCode.value = data.code;
    } catch (error) {
        couponError.value = error instanceof ApiError ? error.firstError : t('common.error');
    } finally {
        couponApplying.value = false;
    }
}

function discountPayload() {
    if (discountMode.value === 'none' || resolveDiscount(total.value) === 0) {
        return null;
    }

    if (discountMode.value === 'manual_fixed') {
        const amount = parseCents(discountFixed.value);

        if (amount < 1) {
            return null;
        }

        return { mode: 'manual_fixed', amount };
    }

    if (discountMode.value === 'manual_percent') {
        const percentBps = Math.min(10000, Math.max(0, parsePercentBps(discountPercent.value)));

        if (percentBps < 1) {
            return null;
        }

        return { mode: 'manual_percent', percent_bps: percentBps };
    }

    const code = appliedCoupon.value?.code ?? couponCode.value.trim();

    if (code === '') {
        return null;
    }

    return { mode: 'coupon', code };
}

function resetDiscount() {
    discountMode.value = 'none';
    discountFixed.value = '';
    discountPercent.value = '';
    couponCode.value = '';
    appliedCoupon.value = null;
    couponError.value = '';
}

async function loadCatalog() {
    const term = query.value.trim();
    const seq = ++searchSeq;

    searching.value = true;
    searchError.value = '';

    try {
        const data = await api.get('/catalog', { q: term, limit: 48 });

        if (seq === searchSeq) {
            catalog.value = Array.isArray(data) ? data : [];
        }
    } catch (error) {
        if (seq === searchSeq) {
            catalog.value = [];
            searchError.value = error instanceof ApiError ? error.firstError : t('common.error');
        }
    } finally {
        if (seq === searchSeq) {
            searching.value = false;
        }
    }
}

watch(query, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(loadCatalog, DEBOUNCE_MS);
});

async function runCustomerSearch() {
    const term = customerQuery.value.trim();
    const seq = ++customerSeq;

    if (term === '') {
        customerResults.value = [];
        customerHasSearched.value = false;
        customerSearching.value = false;
        customerSearchError.value = '';

        return;
    }

    customerSearching.value = true;
    customerSearchError.value = '';

    try {
        const data = await api.get('/customers', { q: term });

        if (seq === customerSeq) {
            customerResults.value = Array.isArray(data) ? data : [];
            customerHasSearched.value = true;
        }
    } catch (error) {
        if (seq === customerSeq) {
            customerResults.value = [];
            customerSearchError.value = error instanceof ApiError ? error.firstError : t('common.error');
        }
    } finally {
        if (seq === customerSeq) {
            customerSearching.value = false;
        }
    }
}

watch(customerQuery, () => {
    clearTimeout(customerTimer);
    customerTimer = setTimeout(runCustomerSearch, DEBOUNCE_MS);
});

function selectCustomer(row) {
    customer.value = row;
    customerQuery.value = '';
    customerResults.value = [];
    customerHasSearched.value = false;
    customerSearchError.value = '';
    upgradeNotice.value = '';
    loadHistory();
}

function clearCustomer() {
    customer.value = null;
    historySeq += 1;
    history.value = [];
    historyError.value = '';
    historyLoading.value = false;
    upgradeNotice.value = '';
}

function historyRequestIsCurrent(seq, key) {
    if (seq !== historySeq || customer.value === null) {
        return false;
    }

    return `${customer.value.type}|${customer.value.id}` === key;
}

async function loadHistory() {
    if (customer.value === null) {
        historySeq += 1;
        history.value = [];
        historyError.value = '';
        historyLoading.value = false;

        return;
    }

    const seq = ++historySeq;
    const key = `${customer.value.type}|${customer.value.id}`;

    historyLoading.value = true;
    historyError.value = '';

    try {
        const response = await api.get('/sales', {
            customer_type: customer.value.type,
            customer_id: customer.value.id,
            limit: 5,
        });

        if (!historyRequestIsCurrent(seq, key)) {
            return;
        }

        history.value = response.data ?? [];
    } catch (error) {
        if (!historyRequestIsCurrent(seq, key)) {
            return;
        }

        history.value = [];
        historyError.value = error instanceof ApiError ? error.firstError : t('common.error');
    } finally {
        if (historyRequestIsCurrent(seq, key)) {
            historyLoading.value = false;
        }
    }
}

function formatHistoryDate(iso) {
    return new Date(iso).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function historyPayable(sale) {
    const gross = sale.totals?.gross ?? 0;
    const discount = sale.totals?.discount ?? 0;

    return Math.max(0, gross - discount);
}

async function addRenewalPlan() {
    upgradeNotice.value = '';
    typeFilter.value = 'subscription';
    highlightPlans.value = true;

    await nextTick();
    document.querySelector('[data-testid="pdv-plans-section"]')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });

    const plan = subscriptions.value[0];

    if (!plan) {
        upgradeNoticeKind.value = 'error';
        upgradeNotice.value = t('counter.upgrade_empty');

        return;
    }

    addToCart(plan);
    upgradeNoticeKind.value = 'success';
    upgradeNotice.value = t('counter.upgrade_added');
}

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(customerTimer);
});

onMounted(async () => {
    await loadCatalog();

    try {
        const response = await api.get('/terminals');
        terminals.value = response.data ?? [];
    } catch {
        terminals.value = [];
    }
});

function addToCart(item) {
    const existing = cart.value.find((line) => lineKey(line) === lineKey(item));

    if (existing) {
        existing.qty += 1;
    } else {
        cart.value.push({ ...item, qty: 1 });
    }

    completedSale.value = null;
    prefillAmount();
}

function setQty(line, value) {
    const qty = Math.floor(Number(value));

    line.qty = Number.isFinite(qty) && qty >= 1 ? qty : 1;
}

function removeLine(index) {
    cart.value.splice(index, 1);
}

function clearCart() {
    cart.value = [];
    payments.value = [];
    draft.value = { method: 'cash', amount: '', terminal_id: '' };
    paymentError.value = '';
    submitError.value = '';
    resetDiscount();
}

function prefillAmount() {
    draft.value.amount = remaining.value > 0 ? centsToInput(remaining.value) : '';
}

watch(remaining, prefillAmount);

function selectMethod(method) {
    draft.value.method = method;

    if (method !== 'card') {
        draft.value.terminal_id = '';
    }

    prefillAmount();
}

function addPayment() {
    paymentError.value = '';

    const amount = parseCents(draft.value.amount);

    if (amount <= 0) {
        paymentError.value = t('counter.invalid_amount');

        return;
    }

    if (draft.value.method === 'card' && !draft.value.terminal_id) {
        paymentError.value = t('counter.terminal_required');

        return;
    }

    payments.value.push({
        method: draft.value.method,
        amount,
        terminal_id: draft.value.method === 'card' ? Number(draft.value.terminal_id) : null,
    });

    prefillAmount();
}

function payRemainingQuick() {
    if (remaining.value <= 0) {
        return;
    }

    draft.value.amount = centsToInput(remaining.value);
    addPayment();
}

function removePayment(index) {
    payments.value.splice(index, 1);
}

function terminalName(id) {
    return terminals.value.find((terminal) => terminal.id === id)?.name ?? `#${id}`;
}

function resetSale() {
    clearCart();
    customer.value = null;
    customerQuery.value = '';
    customerResults.value = [];
    customerHasSearched.value = false;
    customerSearchError.value = '';
    historySeq += 1;
    history.value = [];
    historyError.value = '';
    historyLoading.value = false;
    upgradeNotice.value = '';
    highlightPlans.value = false;
}

function completedPayable(sale) {
    const gross = sale.totals?.gross ?? 0;
    const discount = sale.totals?.discount ?? 0;

    return Math.max(0, gross - discount);
}

async function finalize() {
    submitError.value = '';

    if (needsCustomer.value) {
        submitError.value = t('counter.customer_hint');

        return;
    }

    if (remaining.value !== 0) {
        submitError.value = t('counter.payment_mismatch');

        return;
    }

    submitting.value = true;

    try {
        const payload = {
            items: cart.value.map((line) => ({
                type: line.type,
                source_type: line.source_type,
                source_id: String(line.source_id),
                description: line.description,
                qty: line.qty,
                unit_amount: line.unit_amount,
                metadata: line.metadata ?? {},
            })),
            payments: payments.value.map((line) => ({
                method: line.method,
                amount: line.amount,
                terminal_id: line.terminal_id,
            })),
        };

        if (customer.value) {
            payload.customer_type = customer.value.type;
            payload.customer_id = String(customer.value.id);
        }

        const discount = discountPayload();

        if (discount !== null) {
            payload.discount = discount;
        }

        const response = await api.post('/sales', payload);

        completedSale.value = response.data;
        resetSale();
    } catch (error) {
        submitError.value = error instanceof ApiError ? error.firstError : t('common.error');
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
    <div class="pdv-counter pdv-counter--floor" data-testid="pdv-counter">
        <div v-if="completedSale" class="pdv-alert pdv-alert--success" role="status" data-testid="pdv-sale-success">
            {{ t('counter.success') }}
            <strong class="ms-1">{{ formatMoney(completedPayable(completedSale)) }}</strong>
            <button type="button" class="btn btn-sm btn-success ms-2" @click="completedSale = null">{{ t('counter.new_sale') }}</button>
        </div>

        <div class="pdv-floor">
            <!-- Coluna 1: aluno -->
            <aside class="pdv-col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-user-check me-1 text-primary"></i>
                            {{ t('counter.customer') }}
                        </h5>
                        <span class="badge badge-light text-dark">{{ t('counter.customer_plans_badge') }}</span>
                    </div>
                    <div class="card-body">
                        <div v-if="customer" class="pdv-customer-chip" data-testid="pdv-customer-selected">
                            <div>
                                <div class="fw-bold">{{ customer.label }}</div>
                                <small class="pdv-muted">{{ t('counter.customer_selected') }}</small>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger" data-testid="pdv-customer-clear" @click="clearCustomer">
                                {{ t('counter.customer_clear') }}
                            </button>
                        </div>

                        <template v-else>
                            <label class="form-label" for="pdv-customer-search">{{ t('counter.customer_optional') }}</label>
                            <input
                                id="pdv-customer-search"
                                v-model="customerQuery"
                                type="search"
                                class="form-control"
                                autocomplete="off"
                                data-testid="pdv-customer-search"
                                :placeholder="t('counter.customer_placeholder')"
                            >

                            <p v-if="customerSearching" class="pdv-muted mt-2 mb-0">{{ t('counter.customer_searching') }}</p>
                            <div v-else-if="customerSearchError" class="pdv-alert pdv-alert--error mt-2" role="alert">
                                {{ customerSearchError }}
                                <button type="button" class="btn btn-sm btn-light ms-2" @click="runCustomerSearch">{{ t('common.retry') }}</button>
                            </div>
                            <p v-else-if="!customerHasSearched" class="pdv-muted mt-2 mb-0">{{ t('counter.customer_type_to_search') }}</p>
                            <p v-else-if="customerResults.length === 0" class="pdv-muted mt-2 mb-0">{{ t('counter.customer_no_results') }}</p>

                            <ul v-if="customerResults.length > 0 && !customerSearching" class="pdv-customer-results mt-2">
                                <li v-for="row in customerResults" :key="`${row.type}|${row.id}`">
                                    <button type="button" @click="selectCustomer(row)">{{ row.label }}</button>
                                </li>
                            </ul>
                        </template>

                        <p v-if="needsCustomer" class="text-warning mt-3 mb-0 small">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            {{ t('counter.customer_hint') }}
                        </p>

                        <template v-if="customer">
                            <button
                                type="button"
                                class="btn btn-outline-primary w-100 mt-3"
                                data-testid="pdv-upgrade"
                                @click="addRenewalPlan"
                            >
                                <i class="fa-solid fa-rotate me-1"></i>
                                {{ t('counter.upgrade') }}
                            </button>
                            <div
                                v-if="upgradeNotice"
                                class="pdv-alert mt-2"
                                :class="upgradeNoticeKind === 'error' ? 'pdv-alert--error' : 'pdv-alert--success'"
                                role="status"
                            >
                                {{ upgradeNotice }}
                            </div>

                            <h6 class="pdv-section-title mt-4">
                                <span class="pdv-accent"></span>
                                {{ t('counter.history') }}
                            </h6>
                            <p v-if="historyLoading" class="pdv-muted mb-0">{{ t('counter.history_loading') }}</p>
                            <div v-else-if="historyError" class="pdv-alert pdv-alert--error" role="alert">
                                {{ historyError }}
                                <button type="button" class="btn btn-sm btn-light ms-2" @click="loadHistory">{{ t('common.retry') }}</button>
                            </div>
                            <p v-else-if="history.length === 0" class="pdv-muted mb-0">{{ t('counter.history_empty') }}</p>
                            <ul v-else class="list-group list-group-flush pdv-history" data-testid="pdv-history">
                                <li v-for="sale in history" :key="sale.id" class="list-group-item px-0">
                                    <div class="d-flex justify-content-between gap-2">
                                        <span class="fw-semibold">{{ formatHistoryDate(sale.occurred_at) }}</span>
                                        <span>{{ formatMoney(historyPayable(sale)) }}</span>
                                    </div>
                                    <small class="pdv-muted">
                                        {{ (sale.items ?? []).map((item) => item.description).join(', ') || t(`sales.statuses.${sale.status}`, sale.status) }}
                                    </small>
                                </li>
                            </ul>
                        </template>
                    </div>
                </div>
            </aside>

            <!-- Coluna 2: catálogo exposto -->
            <section class="pdv-col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">{{ t('counter.catalog') }}</h5>
                        <div class="input-group pdv-catalog-search">
                            <span class="input-group-text"><i class="fa-solid fa-magnifying-glass"></i></span>
                            <input
                                id="pdv-search"
                                v-model="query"
                                type="search"
                                class="form-control"
                                autocomplete="off"
                                data-testid="pdv-search"
                                :placeholder="t('counter.search_placeholder')"
                            >
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="pdv-tabs" role="tablist">
                            <button
                                v-for="filter in TYPE_FILTERS"
                                :key="filter"
                                type="button"
                                class="btn btn-sm"
                                :class="typeFilter === filter ? 'btn-primary' : 'btn-outline-primary'"
                                :data-testid="`pdv-filter-${filter}`"
                                @click="typeFilter = filter"
                            >
                                {{ t(`counter.filters.${filter}`) }}
                            </button>
                        </div>

                        <p v-if="searching" class="pdv-muted">{{ t('counter.searching') }}</p>
                        <div v-else-if="searchError" class="pdv-alert pdv-alert--error" role="alert">
                            {{ searchError }}
                            <button type="button" class="btn btn-sm btn-light ms-2" @click="loadCatalog">{{ t('common.retry') }}</button>
                        </div>
                        <p v-else-if="visibleCatalog.length === 0" class="pdv-muted">{{ t('counter.no_results') }}</p>

                        <div v-else class="pdv-scroll">
                            <template v-if="typeFilter === 'all'">
                                <div
                                    v-if="subscriptions.length"
                                    class="mb-3"
                                    :class="{ 'pdv-plans-highlight': highlightPlans }"
                                    data-testid="pdv-plans-section"
                                >
                                    <h6 class="pdv-section-title">
                                        <span class="pdv-accent"></span>
                                        {{ t('counter.sections.subscriptions') }}
                                    </h6>
                                    <div class="pdv-item-grid">
                                        <button
                                            v-for="(item, index) in subscriptions"
                                            :key="lineKey(item)"
                                            type="button"
                                            class="pdv-item-card"
                                            :class="{ 'is-highlight': highlightPlans && index === 0 }"
                                            data-testid="pdv-catalog-item"
                                            @click="addToCart(item)"
                                        >
                                            <span class="pdv-item-card__type text-primary">{{ typeLabel(item.type) }}</span>
                                            <span class="pdv-item-card__name">{{ item.description }}</span>
                                            <div class="pdv-item-card__footer">
                                                <span class="pdv-item-card__price">{{ formatMoney(item.unit_amount) }}</span>
                                                <span class="badge badge-primary">{{ t('counter.add') }}</span>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="services.length" class="mb-3">
                                    <h6 class="pdv-section-title">
                                        <span class="pdv-accent pdv-accent--success"></span>
                                        {{ t('counter.sections.services') }}
                                    </h6>
                                    <div class="pdv-item-grid">
                                        <button
                                            v-for="item in services"
                                            :key="lineKey(item)"
                                            type="button"
                                            class="pdv-item-card"
                                            data-testid="pdv-catalog-item"
                                            @click="addToCart(item)"
                                        >
                                            <span class="pdv-item-card__type text-success">{{ typeLabel(item.type) }}</span>
                                            <span class="pdv-item-card__name">{{ item.description }}</span>
                                            <div class="pdv-item-card__footer">
                                                <span class="pdv-item-card__price">{{ formatMoney(item.unit_amount) }}</span>
                                                <span class="badge badge-success">{{ t('counter.add') }}</span>
                                            </div>
                                        </button>
                                    </div>
                                </div>

                                <div v-if="products.length">
                                    <h6 class="pdv-section-title">
                                        <span class="pdv-accent pdv-accent--info"></span>
                                        {{ t('counter.sections.products') }}
                                    </h6>
                                    <div class="pdv-item-grid">
                                        <button
                                            v-for="item in products"
                                            :key="lineKey(item)"
                                            type="button"
                                            class="pdv-item-card"
                                            data-testid="pdv-catalog-item"
                                            @click="addToCart(item)"
                                        >
                                            <span class="pdv-item-card__type text-info">{{ typeLabel(item.type) }}</span>
                                            <span class="pdv-item-card__name">{{ item.description }}</span>
                                            <div class="pdv-item-card__footer">
                                                <span class="pdv-item-card__price">{{ formatMoney(item.unit_amount) }}</span>
                                                <span class="badge badge-info">{{ t('counter.add') }}</span>
                                            </div>
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <div v-else class="pdv-item-grid" :class="{ 'pdv-plans-highlight': highlightPlans && typeFilter === 'subscription' }" :data-testid="typeFilter === 'subscription' ? 'pdv-plans-section' : undefined">
                                <button
                                    v-for="(item, index) in visibleCatalog"
                                    :key="lineKey(item)"
                                    type="button"
                                    class="pdv-item-card"
                                    :class="{ 'is-highlight': highlightPlans && typeFilter === 'subscription' && index === 0 }"
                                    data-testid="pdv-catalog-item"
                                    @click="addToCart(item)"
                                >
                                    <span class="pdv-item-card__type text-primary">{{ typeLabel(item.type) }}</span>
                                    <span class="pdv-item-card__name">{{ item.description }}</span>
                                    <div class="pdv-item-card__footer">
                                        <span class="pdv-item-card__price">{{ formatMoney(item.unit_amount) }}</span>
                                        <span class="badge badge-primary">{{ t('counter.add') }}</span>
                                    </div>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Coluna 3: carrinho + pagamento -->
            <aside class="pdv-col">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fa-solid fa-cart-shopping me-1 text-success"></i>
                            {{ t('counter.cart') }}
                            <span class="badge badge-light text-dark ms-1">{{ cart.length }}</span>
                        </h5>
                        <button v-if="cart.length" type="button" class="btn btn-sm btn-outline-danger" @click="clearCart">
                            <i class="fa-regular fa-trash-can me-1"></i>{{ t('counter.clear_cart') }}
                        </button>
                    </div>
                    <div class="card-body">
                        <p v-if="cart.length === 0" class="pdv-muted">{{ t('counter.cart_empty') }}</p>

                        <div v-else class="pdv-cart-list" data-testid="pdv-cart">
                            <div v-for="(line, index) in cart" :key="lineKey(line)" class="pdv-cart-line">
                                <div class="pdv-cart-line__top">
                                    <div>
                                        <div class="fw-semibold">{{ line.description }}</div>
                                        <small class="pdv-muted">{{ formatMoney(line.unit_amount) }} · {{ typeLabel(line.type) }}</small>
                                    </div>
                                    <strong>{{ formatMoney(line.qty * line.unit_amount) }}</strong>
                                </div>
                                <div class="pdv-cart-line__qty">
                                    <label class="visually-hidden" :for="`pdv-qty-${index}`">{{ t('counter.qty') }}</label>
                                    <input
                                        :id="`pdv-qty-${index}`"
                                        type="number"
                                        min="1"
                                        step="1"
                                        class="form-control form-control-sm"
                                        :value="line.qty"
                                        :aria-label="t('counter.qty')"
                                        @input="setQty(line, $event.target.value)"
                                    >
                                    <button type="button" class="btn btn-sm btn-outline-danger" @click="removeLine(index)">{{ t('counter.remove') }}</button>
                                </div>
                            </div>
                        </div>

                        <div class="pdv-discount mb-3" data-testid="pdv-discount">
                            <div class="mb-2 small text-uppercase fw-semibold pdv-muted">{{ t('counter.discount') }}</div>
                            <div class="pdv-tabs" role="tablist">
                                <button
                                    v-for="mode in DISCOUNT_MODES"
                                    :key="mode"
                                    type="button"
                                    class="btn btn-sm"
                                    :class="discountMode === mode ? 'btn-primary' : 'btn-outline-primary'"
                                    :data-testid="`pdv-discount-${mode}`"
                                    @click="setDiscountMode(mode)"
                                >
                                    {{ t(`counter.discount_modes.${mode}`) }}
                                </button>
                            </div>

                            <div v-if="discountMode === 'manual_fixed'" class="mt-2">
                                <label class="form-label" for="pdv-discount-fixed">{{ t('counter.discount_fixed') }}</label>
                                <input
                                    id="pdv-discount-fixed"
                                    v-model="discountFixed"
                                    type="text"
                                    inputmode="decimal"
                                    class="form-control"
                                    placeholder="0,00"
                                    data-testid="pdv-discount-fixed"
                                >
                            </div>

                            <div v-if="discountMode === 'manual_percent'" class="mt-2">
                                <label class="form-label" for="pdv-discount-percent">{{ t('counter.discount_percent') }}</label>
                                <input
                                    id="pdv-discount-percent"
                                    v-model="discountPercent"
                                    type="number"
                                    min="0.01"
                                    max="100"
                                    step="0.01"
                                    class="form-control"
                                    data-testid="pdv-discount-percent"
                                >
                            </div>

                            <div v-if="discountMode === 'coupon'" class="mt-2">
                                <label class="form-label" for="pdv-coupon-code">{{ t('counter.discount_code') }}</label>
                                <div class="input-group">
                                    <input
                                        id="pdv-coupon-code"
                                        v-model="couponCode"
                                        type="text"
                                        class="form-control text-uppercase"
                                        maxlength="64"
                                        autocomplete="off"
                                        data-testid="pdv-coupon-code"
                                        @keyup.enter="applyCoupon"
                                    >
                                    <button
                                        type="button"
                                        class="btn btn-secondary"
                                        data-testid="pdv-coupon-apply"
                                        :disabled="couponApplying"
                                        @click="applyCoupon"
                                    >
                                        {{ couponApplying ? t('common.loading') : t('counter.discount_apply') }}
                                    </button>
                                </div>
                                <div v-if="appliedCoupon" class="pdv-alert pdv-alert--success mt-2 mb-0" role="status">
                                    {{ t('counter.discount_applied') }}
                                    <strong class="ms-1">{{ appliedCoupon.code }}</strong>
                                </div>
                                <div v-if="couponError" class="pdv-alert pdv-alert--error mt-2 mb-0" role="alert">{{ couponError }}</div>
                            </div>
                        </div>

                        <div class="pdv-summary">
                            <div class="pdv-summary__row">
                                <span>{{ t('counter.gross') }}</span>
                                <span data-testid="pdv-gross">{{ formatMoney(total) }}</span>
                            </div>
                            <div v-if="discountAmount > 0" class="pdv-summary__row text-success">
                                <span>{{ t('counter.discount') }}</span>
                                <span data-testid="pdv-discount-amount">− {{ formatMoney(discountAmount) }}</span>
                            </div>
                            <div class="pdv-summary__row">
                                <span>{{ t('counter.paid') }}</span>
                                <span>{{ formatMoney(paid) }}</span>
                            </div>
                            <div class="pdv-summary__row">
                                <span>{{ t('counter.remaining') }}</span>
                                <span>{{ formatMoney(remaining) }}</span>
                            </div>
                            <div class="pdv-summary__total">
                                <span class="text-uppercase small fw-bold">{{ t('counter.total') }}</span>
                                <strong data-testid="pdv-total">{{ formatMoney(payable) }}</strong>
                            </div>
                        </div>

                        <div class="mb-2 small text-uppercase fw-semibold pdv-muted">{{ t('counter.payments') }}</div>
                        <div class="pdv-pay-grid">
                            <button
                                v-for="method in ['card', 'pix', 'cash']"
                                :key="method"
                                type="button"
                                class="pdv-pay-btn"
                                :class="{ 'is-active': draft.method === method }"
                                :data-testid="`pdv-pay-${method}`"
                                @click="selectMethod(method)"
                            >
                                <i
                                    class="fa-solid"
                                    :class="{
                                        'fa-credit-card': method === 'card',
                                        'fa-qrcode': method === 'pix',
                                        'fa-money-bill-wave': method === 'cash',
                                    }"
                                ></i>
                                <span>{{ t(`counter.methods.${method}`) }}</span>
                            </button>
                        </div>

                        <div v-if="draft.method === 'card'" class="mb-2">
                            <label class="form-label" for="pdv-terminal">{{ t('counter.terminal') }}</label>
                            <select id="pdv-terminal" v-model="draft.terminal_id" class="form-select" data-testid="pdv-terminal">
                                <option value="">{{ t('counter.select_terminal') }}</option>
                                <option v-for="terminal in activeTerminals" :key="terminal.id" :value="terminal.id">{{ terminal.name }}</option>
                            </select>
                        </div>

                        <div class="row g-2 align-items-end mb-2">
                            <div class="col">
                                <label class="form-label" for="pdv-amount">{{ t('counter.amount') }}</label>
                                <input
                                    id="pdv-amount"
                                    v-model="draft.amount"
                                    type="text"
                                    inputmode="decimal"
                                    class="form-control"
                                    placeholder="0,00"
                                    data-testid="pdv-amount"
                                    @focus="draft.amount === '' && prefillAmount()"
                                >
                            </div>
                            <div class="col-auto">
                                <button type="button" class="btn btn-secondary" data-testid="pdv-add-payment" @click="addPayment">
                                    {{ t('counter.add_payment') }}
                                </button>
                            </div>
                        </div>

                        <button
                            v-if="remaining > 0 && draft.method !== 'card'"
                            type="button"
                            class="btn btn-link btn-sm px-0 mb-2"
                            @click="payRemainingQuick"
                        >
                            {{ t('counter.pay_remaining') }}
                        </button>

                        <div v-if="paymentError" class="pdv-alert pdv-alert--error" role="alert">{{ paymentError }}</div>

                        <ul v-if="payments.length" class="list-group list-group-flush mb-3">
                            <li v-for="(line, index) in payments" :key="index" class="list-group-item d-flex justify-content-between align-items-center px-0">
                                <span>
                                    {{ t(`counter.methods.${line.method}`) }}
                                    <small v-if="line.terminal_id" class="pdv-muted">· {{ terminalName(line.terminal_id) }}</small>
                                </span>
                                <span>
                                    {{ formatMoney(line.amount) }}
                                    <button type="button" class="btn btn-sm btn-link text-danger" @click="removePayment(index)">{{ t('counter.remove') }}</button>
                                </span>
                            </li>
                        </ul>

                        <div v-if="submitError" class="pdv-alert pdv-alert--error" role="alert">{{ submitError }}</div>

                        <button
                            type="button"
                            class="btn btn-success btn-lg pdv-finalize"
                            data-testid="pdv-finalize"
                            :disabled="!canFinalize"
                            @click="finalize"
                        >
                            <i class="fa-solid fa-check-circle me-1"></i>
                            {{ submitting ? t('counter.finalizing') : t('counter.finalize') }}
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
