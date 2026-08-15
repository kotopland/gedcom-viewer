<script setup lang="ts">
import GedcomHeritageNode from './GedcomHeritageNode.vue';
import { ChevronUp } from '@lucide/vue';

defineProps<{
    person: any;
    level: number;
    parentIndex?: number;
    spouse?: any;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();
</script>

<template>
    <div class="flex flex-col items-center relative">
        <!-- Render Parents Above Recursively -->
        <div v-if="person.parents && person.parents.length > 0" class="flex flex-col items-center">
            <!-- Row of Parent Nodes -->
            <div class="flex items-start justify-center gap-8 sm:gap-14 relative">
                <!-- Parent 1 (e.g. Father) -->
                <GedcomHeritageAncestorNode
                    :person="person.parents[0]"
                    :level="level + 1"
                    :parent-index="0"
                    :spouse="person.parents[1]"
                    @select-person="emit('select-person', $event)"
                    @change-root="emit('change-root', $event)"
                />

                <!-- Marriage Bar between Parents if both exist -->
                <div
                    v-if="person.parents.length > 1"
                    class="absolute top-[148px] left-[210px] right-[210px] h-[3px] bg-slate-900 dark:bg-slate-300 z-0 flex items-center justify-between pointer-events-none"
                >
                    <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -ml-1"></span>
                    <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -mr-1"></span>
                </div>

                <!-- Parent 2 (e.g. Mother) -->
                <GedcomHeritageAncestorNode
                    v-if="person.parents.length > 1"
                    :person="person.parents[1]"
                    :level="level + 1"
                    :parent-index="1"
                    :spouse="person.parents[0]"
                    @select-person="emit('select-person', $event)"
                    @change-root="emit('change-root', $event)"
                />
            </div>

            <!-- Vertical Drop Line from Parents' Marriage Bar down to This Person -->
            <div class="w-[3px] h-12 sm:h-14 bg-slate-900 dark:bg-slate-300 mt-[-10px] z-0"></div>
        </div>

        <!-- Load More Ancestors Button if no parents loaded for this top ancestor -->
        <div v-else class="mb-2">
            <button
                @click.stop="emit('change-root', person.id)"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold text-indigo-700 dark:text-indigo-300 bg-indigo-50 dark:bg-indigo-950/80 border border-indigo-300 dark:border-indigo-800 hover:bg-indigo-100 dark:hover:bg-indigo-900 transition-all shadow-xs hover:scale-105 cursor-pointer"
                title="Focus tree on this ancestor to load more generations"
            >
                <ChevronUp class="w-3 h-3" />
                <span>More Ancestors</span>
            </button>
        </div>

        <!-- This Individual Node Card -->
        <GedcomHeritageNode
            :person="person"
            :spouse="spouse"
            @select-person="emit('select-person', $event)"
            @change-root="emit('change-root', $event)"
        />
    </div>
</template>
