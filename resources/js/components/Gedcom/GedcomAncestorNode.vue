<script setup lang="ts">
import { User, RefreshCcw, ChevronUp } from '@lucide/vue';

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
                class="inline-flex items-center gap-1 rounded-full border border-blue-300 bg-blue-100 px-2.5 py-0.5 text-[10px] font-bold text-blue-700 shadow-xs dark:border-blue-700/60 dark:bg-blue-950/90 dark:text-blue-300"
            >
                ♂ {{ isSpouseSide ? "Spouse's Father" : "Father's Side" }}
            </span>
            <span
                v-else-if="parentIndex === 1"
                class="inline-flex items-center gap-1 rounded-full border border-pink-300 bg-pink-100 px-2.5 py-0.5 text-[10px] font-bold text-pink-700 shadow-xs dark:border-pink-700/60 dark:bg-pink-950/90 dark:text-pink-300"
            >
                ♀ {{ isSpouseSide ? "Spouse's Mother" : "Mother's Side" }}
            </span>
        </div>

        <!-- Render parents recursively above this person -->
        <div
            v-if="person.parents && person.parents.length > 0"
            class="flex items-start gap-2 sm:gap-3"
        >
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

        <!-- Load More button above each top person -->
        <button
            v-else
            @click.stop="emit('change-root', person.id)"
            class="group mb-1 flex cursor-pointer items-center gap-1 rounded-full border border-indigo-300 bg-indigo-50 px-2.5 py-1 text-[10px] font-extrabold text-indigo-700 shadow-sm transition-all hover:scale-105 hover:bg-indigo-100 dark:border-indigo-700/70 dark:bg-indigo-950/90 dark:text-indigo-300 dark:hover:bg-indigo-900"
            title="Set as Tree Focus to load ancestors above this person"
        >
            <ChevronUp
                class="h-3 w-3 text-indigo-600 transition-transform group-hover:-translate-y-0.5 dark:text-indigo-400"
            />
            <span>Load More</span>
        </button>

        <!-- Person Card -->
        <div
            @click="emit('select-person', person.id)"
            :class="[
                'group relative shrink-0 cursor-pointer rounded-xl border shadow-md transition-all hover:scale-105',
                level === 1
                    ? 'dark:hover:bg-slate-750 w-48 border-2 border-indigo-500/70 bg-white p-3 hover:bg-indigo-50 dark:border-indigo-500/50 dark:bg-slate-800'
                    : level === 2
                      ? 'w-40 border-slate-300 bg-white/90 p-2.5 hover:bg-slate-50 dark:border-slate-700/80 dark:bg-slate-800/90 dark:hover:bg-slate-800'
                      : level === 3
                        ? 'w-32 border-slate-300 bg-white/90 p-2 text-xs hover:bg-slate-50 dark:border-slate-700/70 dark:bg-slate-800/90 dark:hover:bg-slate-800'
                        : level === 4
                          ? 'w-28 border-slate-300 bg-white/90 p-1.5 text-[11px] hover:bg-slate-50 dark:border-slate-700/60 dark:bg-slate-800/90 dark:hover:bg-slate-800'
                          : 'w-24 border-slate-300 bg-white/90 p-1.5 text-[10px] hover:bg-slate-50 dark:border-slate-700/60 dark:bg-slate-800/90 dark:hover:bg-slate-800',
            ]"
        >
            <button
                @click.stop="emit('change-root', person.id)"
                class="absolute -top-1.5 -right-1.5 z-10 cursor-pointer rounded-full bg-indigo-600 p-1 text-white opacity-100 shadow-md transition-opacity hover:bg-indigo-500 sm:opacity-0 sm:group-hover:opacity-100"
                title="Center tree on this person"
            >
                <RefreshCcw class="h-3.5 w-3.5" />
            </button>

            <div class="flex items-center gap-2">
                <img
                    v-if="person.primary_media"
                    :src="
                        person.portrait_url ||
                        person.primary_media.portrait_url ||
                        person.primary_media.url
                    "
                    :class="[
                        'shrink-0 rounded-lg object-cover',
                        level <= 2 ? 'h-8 w-8' : 'h-6 w-6',
                    ]"
                />
                <div
                    v-else
                    :class="[
                        'flex shrink-0 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-slate-700 dark:text-slate-400',
                        level <= 2 ? 'h-8 w-8' : 'h-6 w-6',
                    ]"
                >
                    <User :class="level <= 2 ? 'h-4 w-4' : 'h-3 w-3'" />
                </div>
                <div class="min-w-0 flex-1">
                    <div
                        :class="[
                            'truncate font-bold group-hover:text-indigo-600 dark:group-hover:text-indigo-400',
                            level === 1
                                ? 'text-xs text-slate-900 dark:text-white'
                                : level <= 3
                                  ? 'text-xs text-slate-800 dark:text-slate-200'
                                  : 'text-[11px] text-slate-800 dark:text-slate-200',
                        ]"
                    >
                        {{ person.name }}
                    </div>
                    <div
                        :class="[
                            'mt-0.5 space-y-0.5 text-[10px] leading-tight font-medium',
                            level === 1
                                ? 'text-indigo-600 dark:text-indigo-300'
                                : 'text-slate-500 dark:text-slate-400',
                        ]"
                    >
                        <div
                            v-if="person.birth_date || person.birth_year"
                            class="truncate"
                        >
                            <span class="font-bold opacity-75">b.</span>
                            {{ person.birth_date || person.birth_year }}
                        </div>
                        <div
                            v-if="person.marriage_date || person.marriage_year"
                            class="truncate text-rose-600 dark:text-rose-400"
                        >
                            <span class="font-bold opacity-75">{{
                                person.marriage_type === 'Civil Partnership' ||
                                person.relationship_type === 'Civil Partnership'
                                    ? 'cp.'
                                    : 'm.'
                            }}</span>
                            {{ person.marriage_date || person.marriage_year }}
                        </div>
                        <div
                            v-if="person.death_date || person.death_year"
                            class="truncate"
                        >
                            <span class="font-bold opacity-75">d.</span>
                            {{ person.death_date || person.death_year }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
