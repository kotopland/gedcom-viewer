<script setup lang="ts">
import { User, RefreshCcw, Heart } from '@lucide/vue';

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
    <div class="flex flex-col items-center gap-4">
        <!-- Person & Spouse(s) Pair Row -->
        <div class="flex items-center gap-2">
            <!-- Main Person Card -->
            <div
                @click="emit('select-person', person.id)"
                :class="[
                    'relative border rounded-xl shadow-md cursor-pointer transition-all hover:scale-105 group shrink-0',
                    level === 1
                        ? 'bg-white dark:bg-slate-800 hover:bg-emerald-50 dark:hover:bg-slate-750 border-2 border-emerald-500/70 dark:border-emerald-500/40 p-3 w-48'
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
                    class="absolute -top-1.5 -right-1.5 p-0.5 bg-emerald-600 hover:bg-emerald-500 text-white rounded-full shadow-xs opacity-0 group-hover:opacity-100 transition-opacity"
                    title="Center tree on this person"
                >
                    <RefreshCcw class="w-3 h-3" />
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
                            'rounded-lg bg-emerald-50 dark:bg-slate-700 flex items-center justify-center text-emerald-600 dark:text-slate-400 shrink-0',
                            level <= 2 ? 'w-8 h-8' : 'w-6 h-6'
                        ]"
                    >
                        <User :class="level <= 2 ? 'w-4 h-4' : 'w-3 h-3'" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div
                            :class="[
                                'font-bold truncate group-hover:text-emerald-600 dark:group-hover:text-emerald-400',
                                level === 1 ? 'text-xs text-slate-900 dark:text-white' : level <= 3 ? 'text-xs text-slate-800 dark:text-slate-200' : 'text-[11px] text-slate-800 dark:text-slate-200'
                            ]"
                        >
                            {{ person.name }}
                        </div>
                        <div
                            :class="[
                                'font-medium text-[10px] leading-tight space-y-0.5 mt-0.5',
                                level === 1 ? 'text-emerald-600 dark:text-emerald-300' : 'text-slate-500 dark:text-slate-400'
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

            <!-- Spouse Cards (if any) -->
            <template v-if="person.spouses && person.spouses.length > 0">
                <div v-for="spouse in person.spouses" :key="spouse.id" class="flex items-center gap-1.5">
                    <Heart class="w-3 h-3 text-rose-500 dark:text-rose-400 shrink-0" />
                    <div
                        @click="emit('select-person', spouse.id)"
                        :class="[
                            'relative border rounded-xl shadow-md cursor-pointer transition-all hover:scale-105 group shrink-0 bg-white dark:bg-slate-900/90 border-rose-300 dark:border-rose-500/40 p-2 w-36 text-xs'
                        ]"
                    >
                        <button
                            @click.stop="emit('change-root', spouse.id)"
                            class="absolute -top-1.5 -right-1.5 p-0.5 bg-rose-600 hover:bg-rose-500 text-white rounded-full shadow-xs opacity-0 group-hover:opacity-100 transition-opacity"
                            title="Center tree on this spouse"
                        >
                            <RefreshCcw class="w-3 h-3" />
                        </button>
                        <div class="flex items-center gap-2">
                            <img
                                v-if="spouse.primary_media"
                                :src="spouse.primary_media.url"
                                class="w-6 h-6 rounded-lg object-cover shrink-0"
                            />
                            <div v-else class="w-6 h-6 rounded-lg bg-rose-50 dark:bg-slate-800 flex items-center justify-center text-rose-500 dark:text-rose-400 shrink-0">
                                <User class="w-3 h-3" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="font-bold truncate text-slate-900 dark:text-slate-200 group-hover:text-rose-600 dark:group-hover:text-rose-300">
                                    {{ spouse.name }}
                                </div>
                                <div class="font-medium text-[10px] leading-tight space-y-0.5 mt-0.5 text-rose-600 dark:text-rose-300">
                                    <div v-if="spouse.birth_date || spouse.birth_year" class="truncate">
                                        <span class="font-bold opacity-75">b.</span> {{ spouse.birth_date || spouse.birth_year }}
                                    </div>
                                    <div v-if="spouse.marriage_date || spouse.marriage_year" class="truncate">
                                        <span class="font-bold opacity-75">m.</span> {{ spouse.marriage_date || spouse.marriage_year }}
                                    </div>
                                    <div v-if="spouse.death_date || spouse.death_year" class="truncate">
                                        <span class="font-bold opacity-75">d.</span> {{ spouse.death_date || spouse.death_year }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Render children recursively below this person -->
        <div v-if="person.children && person.children.length > 0" class="flex flex-wrap justify-center items-start gap-2.5 sm:gap-4">
            <GedcomDescendantNode
                v-for="child in person.children"
                :key="child.id"
                :person="child"
                :level="level + 1"
                @select-person="emit('select-person', $event)"
                @change-root="emit('change-root', $event)"
            />
        </div>
    </div>
</template>
