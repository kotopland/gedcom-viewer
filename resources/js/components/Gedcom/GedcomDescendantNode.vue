<script setup lang="ts">
import { User, RefreshCcw, Heart, ChevronDown } from '@lucide/vue';

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
                    'group relative shrink-0 cursor-pointer rounded-xl border shadow-md transition-all hover:scale-105',
                    level === 1
                        ? 'dark:hover:bg-slate-750 w-48 border-2 border-emerald-500/70 bg-white p-3 hover:bg-emerald-50 dark:border-emerald-500/40 dark:bg-slate-800'
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
                    class="absolute -top-1.5 -right-1.5 z-10 cursor-pointer rounded-full bg-emerald-600 p-1 text-white opacity-100 shadow-md transition-opacity hover:bg-emerald-500 sm:opacity-0 sm:group-hover:opacity-100"
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
                            'flex shrink-0 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-slate-700 dark:text-slate-400',
                            level <= 2 ? 'h-8 w-8' : 'h-6 w-6',
                        ]"
                    >
                        <User :class="level <= 2 ? 'h-4 w-4' : 'h-3 w-3'" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div
                            :class="[
                                'truncate font-bold group-hover:text-emerald-600 dark:group-hover:text-emerald-400',
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
                                    ? 'text-emerald-600 dark:text-emerald-300'
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
                                v-if="
                                    person.marriage_date || person.marriage_year
                                "
                                class="truncate text-rose-600 dark:text-rose-400"
                            >
                                <span class="font-bold opacity-75">{{
                                    person.marriage_type ===
                                        'Civil Partnership' ||
                                    person.relationship_type ===
                                        'Civil Partnership'
                                        ? 'cp.'
                                        : 'm.'
                                }}</span>
                                {{
                                    person.marriage_date || person.marriage_year
                                }}
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

            <!-- Spouse Cards (if any) -->
            <template v-if="person.spouses && person.spouses.length > 0">
                <div
                    v-for="spouse in person.spouses"
                    :key="spouse.id"
                    class="flex items-center gap-1.5"
                >
                    <Heart
                        class="h-3 w-3 shrink-0 text-rose-500 dark:text-rose-400"
                    />
                    <div
                        @click="emit('select-person', spouse.id)"
                        :class="[
                            'group relative w-36 shrink-0 cursor-pointer rounded-xl border border-rose-300 bg-white p-2 text-xs shadow-md transition-all hover:scale-105 dark:border-rose-500/40 dark:bg-slate-900/90',
                        ]"
                    >
                        <button
                            @click.stop="emit('change-root', spouse.id)"
                            class="absolute -top-1.5 -right-1.5 rounded-full bg-rose-600 p-0.5 text-white opacity-0 shadow-xs transition-opacity group-hover:opacity-100 hover:bg-rose-500"
                            title="Center tree on this spouse"
                        >
                            <RefreshCcw class="h-3 w-3" />
                        </button>
                        <div class="flex items-center gap-2">
                            <img
                                v-if="spouse.primary_media"
                                :src="
                                    spouse.portrait_url ||
                                    spouse.primary_media.portrait_url ||
                                    spouse.primary_media.url
                                "
                                class="h-6 w-6 shrink-0 rounded-lg object-cover"
                            />
                            <div
                                v-else
                                class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-rose-50 text-rose-500 dark:bg-slate-800 dark:text-rose-400"
                            >
                                <User class="h-3 w-3" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="truncate font-bold text-slate-900 group-hover:text-rose-600 dark:text-slate-200 dark:group-hover:text-rose-300"
                                >
                                    {{ spouse.name }}
                                </div>
                                <div
                                    class="mt-0.5 space-y-0.5 text-[10px] leading-tight font-medium text-rose-600 dark:text-rose-300"
                                >
                                    <div
                                        v-if="
                                            spouse.birth_date ||
                                            spouse.birth_year
                                        "
                                        class="truncate"
                                    >
                                        <span class="font-bold opacity-75"
                                            >b.</span
                                        >
                                        {{
                                            spouse.birth_date ||
                                            spouse.birth_year
                                        }}
                                    </div>
                                    <div
                                        v-if="
                                            spouse.marriage_date ||
                                            spouse.marriage_year
                                        "
                                        class="truncate"
                                    >
                                        <span class="font-bold opacity-75">{{
                                            spouse.marriage_type ===
                                                'Civil Partnership' ||
                                            spouse.relationship_type ===
                                                'Civil Partnership'
                                                ? 'cp.'
                                                : 'm.'
                                        }}</span>
                                        {{
                                            spouse.marriage_date ||
                                            spouse.marriage_year
                                        }}
                                    </div>
                                    <div
                                        v-if="
                                            spouse.death_date ||
                                            spouse.death_year
                                        "
                                        class="truncate"
                                    >
                                        <span class="font-bold opacity-75"
                                            >d.</span
                                        >
                                        {{
                                            spouse.death_date ||
                                            spouse.death_year
                                        }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Render children recursively below this person -->
        <div
            v-if="person.children && person.children.length > 0"
            class="flex flex-wrap items-start justify-center gap-2.5 sm:gap-4"
        >
            <GedcomDescendantNode
                v-for="child in person.children"
                :key="child.id"
                :person="child"
                :level="level + 1"
                @select-person="emit('select-person', $event)"
                @change-root="emit('change-root', $event)"
            />
        </div>

        <!-- Load More button below each bottom descendant node -->
        <button
            v-else
            @click.stop="emit('change-root', person.id)"
            class="group mt-1 flex cursor-pointer items-center gap-1 rounded-full border border-emerald-300 bg-emerald-50 px-2.5 py-1 text-[10px] font-extrabold text-emerald-700 shadow-sm transition-all hover:scale-105 hover:bg-emerald-100 dark:border-emerald-700/70 dark:bg-emerald-950/90 dark:text-emerald-300 dark:hover:bg-emerald-900"
            title="Set as Tree Focus to load descendants below this person"
        >
            <ChevronDown
                class="h-3 w-3 text-emerald-600 transition-transform group-hover:translate-y-0.5 dark:text-emerald-400"
            />
            <span>Load More</span>
        </button>
    </div>
</template>
