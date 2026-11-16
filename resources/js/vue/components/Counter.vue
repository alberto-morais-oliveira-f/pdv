<script setup>
import { computed, inject, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ApiError } from '../../api.js';
import { centsToInput, formatMoney, parseCents } from '../../money.js';

const api = inject('pdvApi');
const t = inject('pdvT');

const DEBOUNCE_MS = 300;

const query = ref('');
const results = ref([]);
const searching = ref(false);
const searchError = ref('');
const hasSearched = ref(false);
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

const submitting = ref(false);
const submitError = ref('');
const completedSale = ref(null);

const total = computed(() => cart.value.reduce((sum, line) => sum + line.qty * line.unit_amount, 0));
const paid = computed(() => payments.value.reduce((sum, line) => sum + line.amount, 0));
const remaining = computed(() => total.value - paid.value);
const canFinalize = computed(() => !submitting.value && cart.value.length > 0 && payments.value.length > 0 && remaining.value === 0);
const activeTerminals = computed(() => terminals.value.filter((terminal) => terminal.active));
const needsCustomer = computed(() => cart.value.some((line) => line.type === 'subscription') && customer.value === null);

function lineKey(item) {
    return `${item.type}|${item.source_type}|${item.source_id}`;
}

async function runSearch() {
    const term = query.value.trim();
    const seq = ++searchSeq;

    if (term === '') {
        results.value = [];
        hasSearched.value = false;
        searching.value = false;
        searchError.value = '';

        return;
    }

    searching.value = true;
    searchError.value = '';

    try {
        const data = await api.get('/catalog', { q: term });

        if (seq === searchSeq) {
            results.value = data;
            hasSearched.value = true;
        }
    } catch (error) {
        if (seq === searchSeq) {
            results.value = [];
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
    searchTimer = setTimeout(runSearch, DEBOUNCE_MS);
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
}

function clearCustomer() {
    customer.value = null;
}

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    clearTimeout(customerTimer);
});

onMounted(async () => {
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
}

function setQty(line, value) {
    const qty = Math.floor(Number(value));

    line.qty = Number.isFinite(qty) && qty >= 1 ? qty : 1;
}

function removeLine(index) {
    cart.value.splice(index, 1);
}

function prefillAmount() {
    draft.value.amount = remaining.value > 0 ? centsToInput(remaining.value) : '';
}

watch(remaining, prefillAmount);

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
}

function removePayment(index) {
    payments.value.splice(index, 1);
}

function terminalName(id) {
    return terminals.value.find((terminal) => terminal.id === id)?.name ?? `#${id}`;
}

function resetSale() {
    cart.value = [];
    payments.value = [];
    draft.value = { method: 'cash', amount: '', terminal_id: '' };
    submitError.value = '';
    paymentError.value = '';
    customer.value = null;
    customerQuery.value = '';
    customerResults.value = [];
    customerHasSearched.value = false;
    customerSearchError.value = '';
}

async function finalize() {
    submitError.value = '';

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
    <div class="pdv-counter">
        <h4>{{ t('counter.title') }}</h4>

        <div v-if="completedSale" class="pdv-alert pdv-alert--success" role="status">
            {{ t('counter.success') }}
            <strong>{{ formatMoney(completedSale.totals.gross) }}</strong>
            <button type="button" class="btn btn-sm btn-success ms-2" @click="completedSale = null">{{ t('counter.new_sale') }}</button>
        </div>

        <div class="pdv-grid">
            <section class="pdv-panel">
                <div class="pdv-field">
                    <label for="pdv-customer-search">{{ t('counter.customer_optional') }}</label>
                    <div v-if="customer" class="pdv-row">
                        <strong>{{ customer.label }}</strong>
                        <button type="button" class="btn btn-sm btn-outline-danger" data-testid="pdv-customer-clear" @click="clearCustomer">{{ t('counter.customer_clear') }}</button>
                    </div>
                    <input v-else id="pdv-customer-search" v-model="customerQuery" type="search" class="form-control" autocomplete="off" data-testid="pdv-customer-search" :placeholder="t('counter.customer_placeholder')">
                </div>

                <template v-if="!customer">
                    <p v-if="customerSearching" class="pdv-muted">{{ t('counter.customer_searching') }}</p>
                    <div v-else-if="customerSearchError" class="pdv-alert pdv-alert--error" role="alert">
                        {{ customerSearchError }}
                        <button type="button" class="btn btn-sm btn-light ms-2" @click="runCustomerSearch">{{ t('common.retry') }}</button>
                    </div>
                    <p v-else-if="!customerHasSearched" class="pdv-muted">{{ t('counter.customer_type_to_search') }}</p>
                    <p v-else-if="customerResults.length === 0" class="pdv-muted">{{ t('counter.customer_no_results') }}</p>

                    <table v-if="customerResults.length > 0 && !customerSearching" class="pdv-table">
                        <tbody>
                            <tr v-for="row in customerResults" :key="`${row.type}|${row.id}`">
                                <td>{{ row.label }}</td>
                                <td class="pdv-num">
                                    <button type="button" class="btn btn-sm btn-outline-primary" @click="selectCustomer(row)">{{ t('counter.customer_select') }}</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </template>

                <p v-if="needsCustomer" class="pdv-muted">{{ t('counter.customer_hint') }}</p>

                <div class="pdv-field">
                    <label for="pdv-search">{{ t('counter.search') }}</label>
                    <input id="pdv-search" v-model="query" type="search" class="form-control" autocomplete="off" :placeholder="t('counter.search_placeholder')">
                </div>

                <p v-if="searching" class="pdv-muted">{{ t('counter.searching') }}</p>
                <div v-else-if="searchError" class="pdv-alert pdv-alert--error" role="alert">
                    {{ searchError }}
                    <button type="button" class="btn btn-sm btn-light ms-2" @click="runSearch">{{ t('common.retry') }}</button>
                </div>
                <p v-else-if="!hasSearched" class="pdv-muted">{{ t('counter.type_to_search') }}</p>
                <p v-else-if="results.length === 0" class="pdv-muted">{{ t('counter.no_results') }}</p>

                <table v-if="results.length > 0 && !searching" class="pdv-table">
                    <tbody>
                        <tr v-for="item in results" :key="lineKey(item)">
                            <td>{{ item.description }}</td>
                            <td class="pdv-num">{{ formatMoney(item.unit_amount) }}</td>
                            <td class="pdv-num">
                                <button type="button" class="btn btn-sm btn-primary" @click="addToCart(item)">{{ t('counter.add') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>

            <section class="pdv-panel">
                <h5>{{ t('counter.cart') }}</h5>
                <p v-if="cart.length === 0" class="pdv-muted">{{ t('counter.cart_empty') }}</p>
                <table v-else class="pdv-table">
                    <thead>
                        <tr>
                            <th></th>
                            <th>{{ t('counter.qty') }}</th>
                            <th class="pdv-num">{{ t('counter.total') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in cart" :key="lineKey(line)">
                            <td>{{ line.description }}<br><small class="pdv-muted">{{ formatMoney(line.unit_amount) }}</small></td>
                            <td>
                                <input type="number" min="1" step="1" class="form-control form-control-sm" :value="line.qty" :aria-label="t('counter.qty')" @input="setQty(line, $event.target.value)">
                            </td>
                            <td class="pdv-num">{{ formatMoney(line.qty * line.unit_amount) }}</td>
                            <td class="pdv-num">
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removeLine(index)">{{ t('counter.remove') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <p class="pdv-total">{{ t('counter.total') }}: {{ formatMoney(total) }}</p>
            </section>

            <section class="pdv-panel">
                <h5>{{ t('counter.payments') }}</h5>
                <p v-if="payments.length === 0" class="pdv-muted">{{ t('counter.payments_empty') }}</p>
                <table v-else class="pdv-table">
                    <tbody>
                        <tr v-for="(line, index) in payments" :key="index">
                            <td>
                                {{ t(`counter.methods.${line.method}`) }}
                                <small v-if="line.terminal_id" class="pdv-muted">· {{ terminalName(line.terminal_id) }}</small>
                            </td>
                            <td class="pdv-num">{{ formatMoney(line.amount) }}</td>
                            <td class="pdv-num">
                                <button type="button" class="btn btn-sm btn-outline-danger" @click="removePayment(index)">{{ t('counter.remove') }}</button>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <div class="pdv-row">
                    <div class="pdv-field">
                        <label for="pdv-method">{{ t('counter.method') }}</label>
                        <select id="pdv-method" v-model="draft.method" class="form-select">
                            <option v-for="method in ['cash', 'pix', 'card']" :key="method" :value="method">{{ t(`counter.methods.${method}`) }}</option>
                        </select>
                    </div>
                    <div v-if="draft.method === 'card'" class="pdv-field">
                        <label for="pdv-terminal">{{ t('counter.terminal') }}</label>
                        <select id="pdv-terminal" v-model="draft.terminal_id" class="form-select">
                            <option value="">{{ t('counter.select_terminal') }}</option>
                            <option v-for="terminal in activeTerminals" :key="terminal.id" :value="terminal.id">{{ terminal.name }}</option>
                        </select>
                    </div>
                    <div class="pdv-field">
                        <label for="pdv-amount">{{ t('counter.amount') }}</label>
                        <input id="pdv-amount" v-model="draft.amount" type="text" inputmode="decimal" class="form-control" placeholder="0,00" @focus="draft.amount === '' && prefillAmount()">
                    </div>
                    <button type="button" class="btn btn-secondary" @click="addPayment">{{ t('counter.add_payment') }}</button>
                </div>
                <div v-if="paymentError" class="pdv-alert pdv-alert--error" role="alert">{{ paymentError }}</div>

                <p>{{ t('counter.paid') }}: {{ formatMoney(paid) }} · {{ t('counter.remaining') }}: {{ formatMoney(remaining) }}</p>

                <div v-if="submitError" class="pdv-alert pdv-alert--error" role="alert">{{ submitError }}</div>
                <button type="button" class="btn btn-success" :disabled="!canFinalize" @click="finalize">
                    {{ submitting ? t('counter.finalizing') : t('counter.finalize') }}
                </button>
            </section>
        </div>
    </div>
</template>
