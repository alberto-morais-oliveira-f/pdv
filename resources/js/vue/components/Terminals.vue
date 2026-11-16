<script setup>
import { inject, onMounted, ref } from 'vue';
import { ApiError } from '../../api.js';

const api = inject('pdvApi');
const t = inject('pdvT');

const terminals = ref([]);
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
    return { name: '', settlement_days: 30, fee_percent: 0, fee_fixed: 0, active: true };
}

async function load() {
    loading.value = true;
    loadError.value = '';

    try {
        const response = await api.get('/terminals');
        terminals.value = response.data ?? [];
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

function startEdit(terminal) {
    editingId.value = terminal.id;
    form.value = {
        name: terminal.name,
        settlement_days: terminal.settlement_days,
        fee_percent: terminal.fee_percent,
        fee_fixed: terminal.fee_fixed,
        active: terminal.active,
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

async function save() {
    resetFormState();
    saving.value = true;

    const payload = {
        name: form.value.name,
        settlement_days: Number(form.value.settlement_days),
        fee_percent: Number(form.value.fee_percent),
        fee_fixed: Number(form.value.fee_fixed) || 0,
        active: Boolean(form.value.active),
    };

    try {
        if (editingId.value === null) {
            await api.post('/terminals', payload);
        } else {
            await api.put(`/terminals/${editingId.value}`, payload);
        }

        notice.value = t('terminals.saved');
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

async function remove(terminal) {
    actionError.value = '';

    try {
        await api.delete(`/terminals/${terminal.id}`);
        confirmDeleteId.value = null;
        notice.value = t('terminals.deleted');
        await load();
    } catch (error) {
        actionError.value = error instanceof ApiError ? error.firstError : t('common.error');
    }
}
</script>

<template>
    <div class="pdv-terminals">
        <div class="pdv-row">
            <h4>{{ t('terminals.title') }}</h4>
            <button v-if="!showForm" type="button" class="btn btn-primary btn-sm" @click="startCreate">{{ t('terminals.new') }}</button>
        </div>

        <div v-if="notice" class="pdv-alert pdv-alert--success" role="status">{{ notice }}</div>
        <div v-if="actionError" class="pdv-alert pdv-alert--error" role="alert">{{ actionError }}</div>

        <form v-if="showForm" class="pdv-panel" @submit.prevent="save">
            <h5>{{ editingId === null ? t('terminals.new') : t('terminals.edit') }}</h5>
            <div v-if="formError" class="pdv-alert pdv-alert--error" role="alert">{{ formError }}</div>

            <div class="pdv-field">
                <label for="terminal-name">{{ t('terminals.name') }}</label>
                <input id="terminal-name" v-model="form.name" type="text" class="form-control" maxlength="255" required>
                <small v-if="fieldErrors.name" class="text-danger">{{ fieldErrors.name[0] }}</small>
            </div>
            <div class="pdv-field">
                <label for="terminal-days">{{ t('terminals.settlement_days') }}</label>
                <input id="terminal-days" v-model="form.settlement_days" type="number" min="0" max="3650" step="1" class="form-control" required>
                <small v-if="fieldErrors.settlement_days" class="text-danger">{{ fieldErrors.settlement_days[0] }}</small>
            </div>
            <div class="pdv-field">
                <label for="terminal-percent">{{ t('terminals.fee_percent') }}</label>
                <input id="terminal-percent" v-model="form.fee_percent" type="number" min="0" max="100" step="0.01" class="form-control" required>
                <small v-if="fieldErrors.fee_percent" class="text-danger">{{ fieldErrors.fee_percent[0] }}</small>
            </div>
            <div class="pdv-field">
                <label for="terminal-fixed">{{ t('terminals.fee_fixed') }}</label>
                <input id="terminal-fixed" v-model="form.fee_fixed" type="number" min="0" step="1" class="form-control">
                <small v-if="fieldErrors.fee_fixed" class="text-danger">{{ fieldErrors.fee_fixed[0] }}</small>
            </div>
            <div class="pdv-field">
                <label><input v-model="form.active" type="checkbox"> {{ t('terminals.active') }}</label>
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
        <p v-else-if="terminals.length === 0" class="pdv-muted">{{ t('terminals.empty') }}</p>

        <table v-else class="pdv-table">
            <thead>
                <tr>
                    <th>{{ t('terminals.name') }}</th>
                    <th class="pdv-num">{{ t('terminals.settlement_days') }}</th>
                    <th class="pdv-num">{{ t('terminals.fee_percent') }}</th>
                    <th class="pdv-num">{{ t('terminals.fee_fixed') }}</th>
                    <th>{{ t('terminals.active') }}</th>
                    <th class="pdv-num">{{ t('common.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="terminal in terminals" :key="terminal.id">
                    <td>{{ terminal.name }}</td>
                    <td class="pdv-num">{{ terminal.settlement_days }}</td>
                    <td class="pdv-num">{{ terminal.fee_percent }}</td>
                    <td class="pdv-num">{{ terminal.fee_fixed }}</td>
                    <td>{{ terminal.active ? t('terminals.active') : t('terminals.inactive') }}</td>
                    <td class="pdv-num">
                        <template v-if="confirmDeleteId === terminal.id">
                            <span>{{ t('terminals.confirm_delete') }}</span>
                            <button type="button" class="btn btn-sm btn-danger" @click="remove(terminal)">{{ t('common.yes') }}</button>
                            <button type="button" class="btn btn-sm btn-light" @click="confirmDeleteId = null">{{ t('common.no') }}</button>
                        </template>
                        <template v-else>
                            <button type="button" class="btn btn-sm btn-outline-primary" @click="startEdit(terminal)">{{ t('common.edit') }}</button>
                            <button type="button" class="btn btn-sm btn-outline-danger" @click="confirmDeleteId = terminal.id">{{ t('common.delete') }}</button>
                        </template>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
