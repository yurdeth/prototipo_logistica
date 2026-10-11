<script setup lang="ts">
// Fila de tabla. Si es seleccionable, responde a clic y a la tecla Enter.
withDefaults(defineProps<{ selectable?: boolean }>(), { selectable: true });
defineEmits<{ select: [] }>();
</script>

<template>
    <tr
        class="border-b border-sidebar-border/40 transition-colors last:border-b-0"
        :class="
            selectable
                ? 'cursor-pointer hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none'
                : ''
        "
        :tabindex="selectable ? 0 : undefined"
        @click="selectable && $emit('select')"
        @keydown.enter="selectable && $emit('select')"
    >
        <slot />
    </tr>
</template>
