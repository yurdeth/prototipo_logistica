<script setup lang="ts">
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

// Ventana de confirmación para acciones que no se pueden deshacer (por ejemplo, eliminar).
const props = defineProps<{
    title: string;
    description?: string;
    confirmLabel?: string;
    processingLabel?: string;
    processing?: boolean;
    error?: string | null;
}>();

const abierto = defineModel<boolean>({ default: false });
defineEmits<{ confirmar: [] }>();

const botonCancelar = ref<HTMLButtonElement | null>(null);

const cerrar = () => {
    if (!props.processing) abierto.value = false;
};

const alTecla = (e: KeyboardEvent) => {
    if (e.key === 'Escape') cerrar();
};

// Con la ventana abierta, Escape la cierra y el foco empieza en "Cancelar" (la opción segura).
watch(abierto, async (v) => {
    if (v) {
        document.addEventListener('keydown', alTecla);
        await nextTick();
        botonCancelar.value?.focus();
    } else {
        document.removeEventListener('keydown', alTecla);
    }
});

onBeforeUnmount(() => document.removeEventListener('keydown', alTecla));
</script>

<template>
    <Teleport to="body">
        <div
            v-if="abierto"
            class="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4"
            @mousedown.self="cerrar"
        >
            <div
                role="alertdialog"
                aria-modal="true"
                aria-labelledby="confirm-titulo"
                :aria-describedby="
                    description ? 'confirm-descripcion' : undefined
                "
                class="w-full max-w-md rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-xl"
            >
                <h2
                    id="confirm-titulo"
                    class="text-base font-semibold text-foreground"
                >
                    {{ title }}
                </h2>
                <p
                    v-if="description"
                    id="confirm-descripcion"
                    class="mt-2 text-sm text-muted-foreground"
                >
                    {{ description }}
                </p>
                <p v-if="error" class="mt-3 text-sm text-red-600" role="alert">
                    {{ error }}
                </p>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        ref="botonCancelar"
                        type="button"
                        :disabled="processing"
                        class="inline-flex h-10 items-center rounded-lg border border-sidebar-border/70 bg-card px-4 text-sm font-medium text-foreground transition hover:bg-muted disabled:opacity-50"
                        @click="cerrar"
                    >
                        Cancelar
                    </button>
                    <button
                        type="button"
                        :disabled="processing"
                        class="inline-flex h-10 items-center rounded-lg bg-red-600 px-4 text-sm font-medium text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="$emit('confirmar')"
                    >
                        {{
                            processing
                                ? (processingLabel ?? 'Eliminando...')
                                : (confirmLabel ?? 'Eliminar')
                        }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
