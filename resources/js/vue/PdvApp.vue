<script setup>
import { computed } from 'vue';
import Counter from './components/Counter.vue';
import Terminals from './components/Terminals.vue';
import Sales from './components/Sales.vue';

const props = defineProps({
    page: { type: String, default: 'counter' },
    routes: { type: Object, default: () => ({}) },
});

const screens = { counter: Counter, terminals: Terminals, sales: Sales };
const screen = computed(() => screens[props.page] ?? Counter);
</script>

<template>
    <component :is="screen" />
</template>
