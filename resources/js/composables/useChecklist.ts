import { computed, type Ref } from 'vue';
import type { ChecklistItem } from '@/lib/formularios';

export function useChecklist(items: Readonly<Ref<ChecklistItem[]>>) {
    const completados = computed(() => items.value.filter((i) => i.ok).length);
    const total = computed(() => items.value.length);
    const completo = computed(() => completados.value === total.value);

    return { completados, total, completo };
}
