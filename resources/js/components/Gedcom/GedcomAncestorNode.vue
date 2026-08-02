<script setup lang="ts">
import { User, RefreshCcw } from '@lucide/vue';

defineProps<{
    person: any;
    level: number;
    parentIndex?: number;
    isSpouseSide?: boolean;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();
</script>

<template>
    <div class="flex flex-col items-center gap-2 sm:gap-3">
        <!-- Optional Father/Mother Branch Badge at Level 1 -->
        <div v-if="level === 1 && parentIndex !== undefined" class="mb-1">
            <span
                v-if="parentIndex === 0"
                class="inline-flex items-center gap-1 text-[10px] font-bold text-blue-700 dark:text-blue-300 bg-blue-100 dark:bg-blue-950/90 px-2.5 py-0.5 rounded-full border border-blue-300 dark:border-blue-700/60 shadow-xs"
            >
                ♂ {{ isSpouseSide ? "Spouse's Father" : "Father's Side" }}
            </span>
            <span
                v-else-if="parentIndex === 1"
                class="inline-flex items-center gap-1 text-[10px] font-bold text-pink-700 dark:text-pink-300 bg-pink-100 dark:bg-pink-950/90 px-2.5 py-0.5 rounded-full border border-pink-300 dark:border-pink-700/60 shadow-xs"
            >
                ♀ {{ isSpouseSide ? "Spouse's Mother" : "Mother's Side" }}
            </span>
        </div>

        <!-- Render parents recursively above this person -->
        <div v-if="person.parents && person.parents.length > 0" class="flex items-start gap-2 sm:gap-3">
            <GedcomAncestorNode
                v-for="(parent, idx) in person.parents"
                :key="parent.id"
                :person="parent"
                :level="level + 1"
                :parent-index="idx"
                :is-spouse-side="isSpouseSide"
                @select-person="emit('select-person', $event)"
                @change-root="emit('change-root', $event)"
            />
        </div>

        <!-- Person Card -->
        <div
            @click="emit('select-person', person.id)"
            :class="[
                'relative border rounded-xl shadow-md cursor-pointer transition-all hover:scale-105 group shrink-0',
                level === 1
                    ? 'bg-white dark:bg-slate-800 hover:bg-indigo-50 dark:hover:bg-slate-750 border-2 border-indigo-500/70 dark:border-indigo-500/50 p-3 w-48'
                    : level === 2
                    ? 'bg-white/90 dark:bg-slate-800/90 hover:bg-slate-50 dark:hover:bg-slate-800 border-slate-300 dark:border-slate-700/80 p-2.5 w-40'
                    : level === 3
                    ? 'bg-white/90 dark:bg-slate-850/90 hover:bg-slate-50 dark:hover:bg-slate-800 border-slate-300 dark:border-slate-700/70 p-2 w-32 text-xs'
                    : level === 4
                    ? 'bg-white/90 dark:bg-slate-850/90 hover:bg-slate-50 dark:hover:bg-slate-800 border-slate-300 dark:border-slate-700/60 p-1.5 w-28 text-[11px]'
                    : 'bg-white/90 dark:bg-slate-850/90 hover:bg-slate-50 dark:hover:bg-slate-800 border-slate-300 dark:border-slate-700/60 p-1.5 w-24 text-[10px]'
            ]"
        >
            <button
                @click.stop="emit('change-root', person.id)"
                class="absolute -top-1.5 -right-1.5 p-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-full shadow-md opacity-100 sm:opacity-0 sm:group-hover:opacity-100 transition-opacity z-10 cursor-pointer"
                title="Center tree on this person"
            >
                <RefreshCcw class="w-3.5 h-3.5" />
            </button>

            <div class="flex items-center gap-2">
                <img
                    v-if="person.primary_media"
                    :src="person.primary_media.url"
                    :class="[
                        'rounded-lg object-cover shrink-0',
                        level <= 2 ? 'w-8 h-8' : 'w-6 h-6'
                    ]"
                />
                <div
                    v-else
                    :class="[
                        'rounded-lg bg-indigo-50 dark:bg-slate-700 flex items-center justify-center text-indigo-600 dark:text-slate-400 shrink-0',
                        level <= 2 ? 'w-8 h-8' : 'w-6 h-6'
                    ]"
                >
                    <User :class="level <= 2 ? 'w-4 h-4' : 'w-3 h-3'" />
                </div>
                <div class="min-w-0 flex-1">
                    <div
                        :class="[
                            'font-bold truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400',
                            level === 1 ? 'text-xs text-slate-900 dark:text-white' : level <= 3 ? 'text-xs text-slate-800 dark:text-slate-200' : 'text-[11px] text-slate-800 dark:text-slate-200'
                        ]"
                    >
                        {{ person.name }}
                    </div>
                    <div
                        :class="[
                            'font-medium text-[10px] leading-tight space-y-0.5 mt-0.5',
                            level === 1 ? 'text-indigo-600 dark:text-indigo-300' : 'text-slate-500 dark:text-slate-400'
                        ]"
                    >
                        <div v-if="person.birth_date || person.birth_year" class="truncate">
                            <span class="font-bold opacity-75">b.</span> {{ person.birth_date || person.birth_year }}
                        </div>
                        <div v-if="person.marriage_date || person.marriage_year" class="truncate text-rose-600 dark:text-rose-400">
                            <span class="font-bold opacity-75">m.</span> {{ person.marriage_date || person.marriage_year }}
                        </div>
                        <div v-if="person.death_date || person.death_year" class="truncate">
                            <span class="font-bold opacity-75">d.</span> {{ person.death_date || person.death_year }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
