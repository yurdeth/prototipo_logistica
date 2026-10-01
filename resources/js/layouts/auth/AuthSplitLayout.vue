<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { Truck } from '@/UI/Icons';
import { landingContent } from '@/config/landing';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-2">
        <!-- Panel de marca -->
        <div
            class="relative hidden flex-col justify-between p-10 text-white lg:flex"
            :style="{ backgroundColor: colors.azulOscuro }"
        >
            <Link
                :href="home()"
                class="relative z-10 flex items-center gap-2 text-base font-medium"
            >
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-lg"
                    :style="{ backgroundColor: colors.naranjaClaro }"
                >
                    <Truck
                        class="h-5 w-5 text-white"
                        aria-hidden="true"
                    />
                </span>

                {{ name }}
            </Link>

            <div class="relative z-10 max-w-md">
                <span
                    class="inline-block rounded-full border px-3 py-1 text-xs text-orange-300"
                    :style="{ borderColor: colors.naranjaClaro }"
                >
                    {{ landingContent.badge }}
                </span>

                <h2
                    class="mt-4 text-2xl font-medium leading-tight md:text-3xl"
                >
                    {{ landingContent.title }}
                </h2>

                <p class="mt-3 text-sm leading-relaxed text-slate-300">
                    {{ landingContent.description }}
                </p>
            </div>

            <!-- Motivo decorativo de barras -->
            <div
                class="relative z-10 flex h-16 items-end gap-1.5"
                aria-hidden="true"
            >
                <div
                    v-for="(height, i) in [35, 55, 40, 70, 50, 85, 65, 100]"
                    :key="i"
                    class="w-6 rounded-t"
                    :style="{
                        height: `${height}%`,
                        backgroundColor:
                            i === 7 ? colors.naranjaClaro : colors.azulClaro,
                    }"
                />
            </div>
        </div>

        <!-- Panel del formulario -->
        <div
            class="flex flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
        >
            <div class="w-full max-w-sm">
                <!-- Logo en pantallas pequeñas -->
                <div class="mb-8 flex flex-col items-center gap-2 lg:hidden">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg"
                        :style="{ backgroundColor: colors.naranjaClaro }"
                    >
                        <Truck
                            class="h-5 w-5 text-white"
                            aria-hidden="true"
                        />
                    </span>

                    <span class="text-sm font-medium text-foreground">
                        {{ name }}
                    </span>
                </div>

                <div
                    class="flex flex-col space-y-2 text-center"
                    v-if="title || description"
                >
                    <h1
                        class="text-xl font-medium tracking-tight text-foreground"
                        v-if="title"
                    >
                        {{ title }}
                    </h1>

                    <p
                        class="text-sm text-muted-foreground"
                        v-if="description"
                    >
                        {{ description }}
                    </p>
                </div>

                <div class="mt-6">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>
