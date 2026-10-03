<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { Truck } from '@/UI/Icons';

// El nombre viene de APP_NAME (.env) a través de Inertia
const page = usePage();
const name = page.props.name as string;
</script>

<template>
    <div
        class="flex aspect-square size-8 items-center justify-center rounded-md"
        :style="{ backgroundColor: colors.naranjaClaro }"
    >
        <Truck class="size-5 text-white" aria-hidden="true" />
    </div>
    <div class="ml-1 grid flex-1 text-left text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">{{
            name
        }}</span>
    </div>
</template>
