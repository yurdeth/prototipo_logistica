<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import type { Opcion } from '@/config/Envios/envioForm';
import FormPage from '@/components/Forms/FormPage.vue';
import ClientesForms from '@/components/Forms/ClientesForms.vue';

defineProps<{
    cliente: {
        id_cliente: number;
        nombre: string;
        tipo_cliente: 'PERSONA' | 'EMPRESA';
        contacto: string | null;
        territorio_id: number;
    };
    total_envios: number;
    territorios: Opcion[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Clientes', href: '/clientes' },
            { title: 'Editar cliente', href: '#' },
        ],
    },
});
</script>

<template>
    <Head :title="`Editar ${cliente.nombre}`" />

    <FormPage
        :title="`Editar cliente`"
        :description="cliente.nombre"
        :crumbs="[
            { label: 'Gestión logística', href: '/envios' },
            { label: 'Clientes', href: '/clientes' },
            { label: cliente.nombre },
        ]"
        back-href="/clientes"
        back-label="Volver a clientes"
    >
        <ClientesForms
            :territorios="territorios"
            :cliente="cliente"
            :total-envios="total_envios"
        />
    </FormPage>
</template>
