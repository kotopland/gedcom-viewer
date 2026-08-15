<script setup lang="ts">
import GedcomHeritageNode from './GedcomHeritageNode.vue';
import { ChevronDown } from '@lucide/vue';

defineProps<{
    person: any;
    level: number;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();
</script>

<template>
    <div class="flex flex-col items-center relative">
        <!-- Person & Spouse(s) Row -->
        <div class="flex items-start gap-3 sm:gap-5 relative">
            <!-- Main Person Node -->
            <GedcomHeritageNode
                :person="person"
                :spouse="person.spouses?.[0]"
                @select-person="emit('select-person', $event)"
                @change-root="emit('change-root', $event)"
            />

            <!-- Marriage Horizontal Bar with Anchor Pins -->
            <div
                v-if="person.spouses && person.spouses.length > 0"
                class="absolute top-[148px] left-[210px] right-[210px] h-[3px] bg-slate-900 dark:bg-slate-300 z-0 flex items-center justify-between pointer-events-none"
            >
                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -ml-1"></span>
                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -mr-1"></span>
            </div>

            <!-- Spouse Node -->
            <GedcomHeritageNode
                v-if="person.spouses && person.spouses.length > 0"
                :person="person.spouses[0]"
                :spouse="person"
                @select-person="emit('select-person', $event)"
                @change-root="emit('change-root', $event)"
            />
        </div>

        <!-- Children Section Below (Recursive) -->
        <div v-if="person.children && person.children.length > 0" class="flex flex-col items-center relative mt-3">
            <!-- Vertical Drop Line from Couple down to Sibling Bar -->
            <div class="w-[3px] h-12 bg-slate-900 dark:bg-slate-300 mt-[-14px] z-0"></div>

            <!-- Horizontal Distribution Sibling Bracket -->
            <div
                v-if="person.children.length > 1"
                class="h-[3px] bg-slate-900 dark:bg-slate-300 z-0 relative mb-4"
                :style="{
                    width: `calc(100% - ${person.children.length === 2 ? '240px' : '260px'})`
                }"
            ></div>

            <!-- Row of Children -->
            <div class="flex items-start justify-center gap-10 sm:gap-14 flex-wrap">
                <div
                    v-for="child in person.children"
                    :key="child.id"
                    class="flex flex-col items-center relative"
                >
                    <!-- Vertical Drop Line into Child -->
                    <div class="w-[3px] h-6 bg-slate-900 dark:bg-slate-300 -mt-4 mb-1 z-0"></div>

                    <!-- Recursive Child Descendant Node -->
                    <GedcomHeritageDescendantNode
                        :person="child"
                        :level="level + 1"
                        @select-person="emit('select-person', $event)"
                        @change-root="emit('change-root', $event)"
                    />
                </div>
            </div>
        </div>

        <!-- Load More Descendants Button if at leaf of tree -->
        <div v-else class="mt-3">
            <button
                @click.stop="emit('change-root', person.id)"
                class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-bold text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/80 border border-emerald-300 dark:border-emerald-800 hover:bg-emerald-100 dark:hover:bg-emerald-900 transition-all shadow-xs hover:scale-105 cursor-pointer"
                title="Focus tree on this person to load more descendants"
            >
                <ChevronDown class="w-3 h-3" />
                <span>More Descendants</span>
            </button>
        </div>
    </div>
</template>
