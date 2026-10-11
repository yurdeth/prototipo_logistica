<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronLeft } from '@/UI/Icons';

defineProps<{
    title: string;
    description?: string;
    crumbs?: { label: string; href?: string }[];
    backHref: string;
    backLabel: string;
}>();
</script>

<template>
    <div class="min-h-full bg-slate-50/70 p-4 sm:p-6 lg:p-8">
        <div class="mx-auto max-w-[1600px] space-y-6">
            <header
                class="flex flex-col justify-between gap-4 border-b border-slate-200 pb-5 sm:flex-row sm:items-center"
            >
                <div>
                    <div
                        v-if="crumbs?.length"
                        class="mb-2 flex items-center gap-2 text-xs font-medium tracking-wider text-slate-500 uppercase"
                    >
                        <template v-for="(c, i) in crumbs" :key="c.label">
                            <span v-if="i > 0">/</span>
                            <Link
                                v-if="c.href"
                                :href="c.href"
                                class="transition hover:text-blue-700"
                            >
                                {{ c.label }}
                            </Link>
                            <span v-else class="text-slate-400">{{
                                c.label
                            }}</span>
                        </template>
                    </div>

                    <h1
                        class="text-2xl font-semibold tracking-tight text-slate-900"
                    >
                        {{ title }}
                    </h1>
                    <p v-if="description" class="mt-1 text-sm text-slate-500">
                        {{ description }}
                    </p>
                </div>

                <Link
                    :href="backHref"
                    class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 shadow-sm transition hover:bg-slate-100"
                >
                    <ChevronLeft class="h-4 w-4" />
                    {{ backLabel }}
                </Link>
            </header>

            <slot />
        </div>
    </div>
</template>
