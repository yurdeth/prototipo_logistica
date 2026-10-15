<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import type { Opcion } from '@/config/Envios/envioForm';
import FormPage from '@/components/Forms/FormPage.vue';
import ClientesForms from '@/components/Forms/ClientesForms.vue';

defineProps<{ territorios: Opcion[] }>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Clientes', href: '/clientes' },
            { title: 'Nuevo cliente', href: '#' },
        ],
    },
});
</script>

<template>
    <Head title="Nuevo cliente" />

    <FormPage
        title="Registrar cliente"
        description="Registra a la persona o empresa que envía o recibe paquetes."
        :crumbs="[
            { label: 'Gestión logística', href: '/envios' },
            { label: 'Clientes', href: '/clientes' },
        ]"
        back-href="/clientes"
        back-label="Volver a clientes"
    >
        <ClientesForms :territorios="territorios" />
    </FormPage>
</template>
