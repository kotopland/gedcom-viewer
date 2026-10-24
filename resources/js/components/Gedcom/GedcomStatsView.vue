<script setup lang="ts">
import {
    Award,
    BarChart2,
    Clock,
    Globe,
    Heart,
    MapPin,
    TrendingUp,
    User,
    Users,
} from '@lucide/vue';
import { ref, onMounted } from 'vue';

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
}>();

const loading = ref<boolean>(true);
const statsData = ref<any>(null);

const fetchStats = async () => {
    loading.value = true;

    try {
        const res = await fetch('/api/gedcom/stats');

        if (res.ok) {
            statsData.value = await res.json();
        }
    } catch (e) {
        console.error('Failed to load genealogy stats:', e);
    } finally {
        loading.value = false;
    }
};

onMounted(() => {
    fetchStats();
});
</script>

<template>
    <div class="space-y-6">
        <!-- Header Banner -->
        <div
            class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 p-6 text-white shadow-2xl sm:p-8"
        >
            <div
                class="pointer-events-none absolute -top-10 -right-10 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl"
            ></div>
            <div
                class="relative z-10 flex flex-col justify-between gap-4 md:flex-row md:items-center"
            >
                <div class="space-y-2">
                    <div
                        class="inline-flex items-center gap-2 rounded-full border border-indigo-500/30 bg-indigo-500/20 px-3 py-1 text-xs font-extrabold text-indigo-300"
                    >
                        <BarChart2 class="h-3.5 w-3.5" />
                        <span>Permission-Scoped Lineage Analytics</span>
                    </div>
                    <h2
                        class="text-2xl font-black tracking-tight text-white sm:text-3xl"
                    >
                        Genealogy Statistics & Insights
                    </h2>
                    <p
                        class="max-w-2xl text-xs leading-relaxed text-indigo-200/80 sm:text-sm"
                    >
                        Explore surname breakdowns, geographical locations,
                        lifespan benchmarks, and notable records strictly
                        authorized for your account.
                    </p>
                </div>

                <button
                    @click="fetchStats"
                    class="flex cursor-pointer items-center gap-2 self-start rounded-2xl border border-white/20 bg-white/10 px-4 py-2.5 text-xs font-bold text-white backdrop-blur-md transition-all hover:bg-white/20 md:self-auto"
                >
                    <TrendingUp class="h-4 w-4" />
                    <span>Refresh Stats</span>
                </button>
            </div>
        </div>

        <div
            v-if="loading"
            class="flex flex-col items-center justify-center py-20 text-slate-400"
        >
            <div
                class="h-10 w-10 animate-spin rounded-full border-3 border-indigo-600 border-t-transparent"
            ></div>
            <p
                class="mt-4 text-xs font-semibold text-slate-600 dark:text-slate-300"
            >
                Calculating genealogy statistics...
            </p>
        </div>

        <template v-else-if="statsData">
            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                <div
                    class="space-y-1 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex items-center justify-between text-slate-400"
                    >
                        <span
                            class="text-[11px] font-bold tracking-wider uppercase"
                            >Individuals</span
                        >
                        <User class="h-4 w-4 text-indigo-500" />
                    </div>
                    <div
                        class="text-2xl font-black text-slate-900 sm:text-3xl dark:text-white"
                    >
                        {{ statsData.totals?.total_individuals || 0 }}
                    </div>
                    <div
                        class="flex items-center gap-1 text-[10px] font-semibold text-slate-500 dark:text-slate-400"
                    >
                        <span>{{ statsData.totals?.males || 0 }} males</span>
                        <span>•</span>
                        <span
                            >{{ statsData.totals?.females || 0 }} females</span
                        >
                    </div>
                </div>

                <div
                    class="space-y-1 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex items-center justify-between text-slate-400"
                    >
                        <span
                            class="text-[11px] font-bold tracking-wider uppercase"
                            >Families</span
                        >
                        <Users class="h-4 w-4 text-purple-500" />
                    </div>
                    <div
                        class="text-2xl font-black text-slate-900 sm:text-3xl dark:text-white"
                    >
                        {{ statsData.totals?.total_families || 0 }}
                    </div>
                    <div
                        class="text-[10px] font-semibold text-slate-500 dark:text-slate-400"
                    >
                        Recorded marriages & lineages
                    </div>
                </div>

                <div
                    class="space-y-1 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex items-center justify-between text-slate-400"
                    >
                        <span
                            class="text-[11px] font-bold tracking-wider uppercase"
                            >Countries</span
                        >
                        <Globe class="h-4 w-4 text-emerald-500" />
                    </div>
                    <div
                        class="text-2xl font-black text-slate-900 sm:text-3xl dark:text-white"
                    >
                        {{ statsData.top_countries?.length || 0 }}
                    </div>
                    <div
                        class="truncate text-[10px] font-semibold text-slate-500 dark:text-slate-400"
                    >
                        Global locations recorded
                    </div>
                </div>

                <div
                    class="space-y-1 rounded-3xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-900"
                >
                    <div
                        class="flex items-center justify-between text-slate-400"
                    >
                        <span
                            class="text-[11px] font-bold tracking-wider uppercase"
                            >Lifespan Span</span
                        >
                        <Clock class="h-4 w-4 text-amber-500" />
                    </div>
                    <div
                        class="text-2xl font-black text-slate-900 sm:text-3xl dark:text-white"
                    >
                        {{
                            statsData.generations_span?.total_years_span || '—'
                        }}
                        <span class="text-xs font-bold text-slate-400"
                            >yrs</span
                        >
                    </div>
                    <div
                        class="text-[10px] font-semibold text-slate-500 dark:text-slate-400"
                    >
                        {{
                            statsData.generations_span?.earliest_birth_year ||
                            '?'
                        }}
                        –
                        {{
                            statsData.generations_span?.latest_birth_year ||
                            'Present'
                        }}
                    </div>
                </div>
            </div>

            <!-- Featured Records Spotlight Grid -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <!-- Oldest Verified Person Card -->
                <div
                    v-if="statsData.oldest_person"
                    @click="emit('select-person', statsData.oldest_person.id)"
                    class="group relative flex cursor-pointer items-center gap-5 overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-xl transition-all hover:border-indigo-500 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-500"
                >
                    <div
                        class="absolute top-0 right-0 bottom-0 w-2 bg-indigo-600"
                    ></div>

                    <!-- Photo Thumbnail -->
                    <img
                        v-if="statsData.oldest_person.primary_media?.url"
                        :src="
                            statsData.oldest_person.portrait_url ||
                            statsData.oldest_person.primary_media
                                .portrait_url ||
                            statsData.oldest_person.primary_media.url
                        "
                        class="h-16 w-16 shrink-0 rounded-2xl border-2 border-indigo-500/40 object-cover shadow-md transition-transform group-hover:scale-105"
                    />
                    <div
                        v-else
                        class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-indigo-600/10 font-bold text-indigo-600 dark:text-indigo-400"
                    >
                        <Award class="h-8 w-8" />
                    </div>

                    <div class="min-w-0 flex-1 space-y-1">
                        <div
                            class="inline-flex items-center gap-1 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-[10px] font-extrabold tracking-wide text-amber-600 uppercase dark:text-amber-400"
                        >
                            <Award class="h-3 w-3" />
                            <span>Oldest Verified Person</span>
                        </div>
                        <h3
                            class="truncate text-base font-extrabold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                        >
                            {{ statsData.oldest_person.name }}
                        </h3>
                        <p
                            class="text-xs font-medium text-slate-500 dark:text-slate-400"
                        >
                            Lived
                            <span
                                class="text-sm font-black text-indigo-600 dark:text-indigo-400"
                                >{{ statsData.oldest_person.age }} years</span
                            >
                            ({{ statsData.oldest_person.birth_year }} –
                            {{ statsData.oldest_person.death_year }})
                        </p>
                    </div>
                </div>

                <!-- Biggest Age Gap Married Couple Card -->
                <div
                    v-if="statsData.biggest_age_difference_couple"
                    class="relative space-y-3 overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <div
                            class="inline-flex items-center gap-1 rounded-full bg-rose-500/10 px-2.5 py-0.5 text-[10px] font-extrabold tracking-wide text-rose-600 uppercase dark:text-rose-400"
                        >
                            <Heart class="h-3 w-3" />
                            <span>Largest Couple Age Difference</span>
                        </div>
                        <span
                            class="rounded-xl border border-rose-200 bg-rose-50 px-2.5 py-1 text-xs font-black text-rose-600 dark:border-rose-800 dark:bg-rose-950/60 dark:text-rose-400"
                        >
                            {{
                                statsData.biggest_age_difference_couple
                                    .age_difference
                            }}
                            yrs age gap
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <!-- Husband -->
                        <div
                            @click="
                                emit(
                                    'select-person',
                                    statsData.biggest_age_difference_couple
                                        .husband.id,
                                )
                            "
                            class="flex cursor-pointer items-center gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 p-2.5 transition-all hover:border-indigo-500 dark:border-slate-700/60 dark:bg-slate-800/60"
                        >
                            <img
                                v-if="
                                    statsData.biggest_age_difference_couple
                                        .husband.primary_media?.url
                                "
                                :src="
                                    statsData.biggest_age_difference_couple
                                        .husband.portrait_url ||
                                    statsData.biggest_age_difference_couple
                                        .husband.primary_media.portrait_url ||
                                    statsData.biggest_age_difference_couple
                                        .husband.primary_media.url
                                "
                                class="h-9 w-9 shrink-0 rounded-xl object-cover"
                            />
                            <div
                                v-else
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600"
                            >
                                <User class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="truncate text-[11px] font-bold text-slate-900 dark:text-white"
                                >
                                    {{
                                        statsData.biggest_age_difference_couple
                                            .husband.name
                                    }}
                                </div>
                                <div
                                    class="text-[10px] text-slate-500 dark:text-slate-400"
                                >
                                    b.{{
                                        statsData.biggest_age_difference_couple
                                            .husband.birth_year
                                    }}
                                </div>
                            </div>
                        </div>

                        <!-- Wife -->
                        <div
                            @click="
                                emit(
                                    'select-person',
                                    statsData.biggest_age_difference_couple.wife
                                        .id,
                                )
                            "
                            class="flex cursor-pointer items-center gap-2.5 rounded-2xl border border-slate-200 bg-slate-50 p-2.5 transition-all hover:border-indigo-500 dark:border-slate-700/60 dark:bg-slate-800/60"
                        >
                            <img
                                v-if="
                                    statsData.biggest_age_difference_couple.wife
                                        .primary_media?.url
                                "
                                :src="
                                    statsData.biggest_age_difference_couple.wife
                                        .portrait_url ||
                                    statsData.biggest_age_difference_couple.wife
                                        .primary_media.portrait_url ||
                                    statsData.biggest_age_difference_couple.wife
                                        .primary_media.url
                                "
                                class="h-9 w-9 shrink-0 rounded-xl object-cover"
                            />
                            <div
                                v-else
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-pink-500/10 text-pink-600"
                            >
                                <User class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div
                                    class="truncate text-[11px] font-bold text-slate-900 dark:text-white"
                                >
                                    {{
                                        statsData.biggest_age_difference_couple
                                            .wife.name
                                    }}
                                </div>
                                <div
                                    class="text-[10px] text-slate-500 dark:text-slate-400"
                                >
                                    b.{{
                                        statsData.biggest_age_difference_couple
                                            .wife.birth_year
                                    }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Breakdowns: Surnames, Countries, Place Names -->
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <!-- Top Surnames -->
                <div
                    class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="flex items-center gap-2 text-sm font-extrabold text-slate-900 dark:text-white"
                        >
                            <User
                                class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                            />
                            Top Surnames
                        </h3>
                        <span class="text-xs font-bold text-slate-400"
                            >Frequency</span
                        >
                    </div>

                    <div class="space-y-2.5">
                        <div
                            v-for="(item, idx) in statsData.top_surnames?.slice(
                                0,
                                10,
                            )"
                            :key="'sn-' + idx"
                            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-2 text-xs dark:border-slate-800 dark:bg-slate-800/50"
                        >
                            <span
                                class="font-extrabold text-slate-800 dark:text-slate-200"
                            >
                                #{{ idx + 1 }} {{ item.surname }}
                            </span>
                            <span
                                class="rounded-lg bg-indigo-100 px-2 py-0.5 text-[11px] font-extrabold text-indigo-700 dark:bg-indigo-950 dark:text-indigo-300"
                            >
                                {{ item.count }} people
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Top Countries -->
                <div
                    class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="flex items-center gap-2 text-sm font-extrabold text-slate-900 dark:text-white"
                        >
                            <Globe
                                class="h-4 w-4 text-emerald-600 dark:text-emerald-400"
                            />
                            Top Countries
                        </h3>
                        <span class="text-xs font-bold text-slate-400"
                            >Locations</span
                        >
                    </div>

                    <div class="space-y-2.5">
                        <div
                            v-for="(
                                item, idx
                            ) in statsData.top_countries?.slice(0, 10)"
                            :key="'co-' + idx"
                            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-2 text-xs dark:border-slate-800 dark:bg-slate-800/50"
                        >
                            <span
                                class="flex items-center gap-2 font-extrabold text-slate-800 dark:text-slate-200"
                            >
                                <span>🌐</span> {{ item.country }}
                            </span>
                            <span
                                class="rounded-lg bg-emerald-100 px-2 py-0.5 text-[11px] font-extrabold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
                            >
                                {{ item.count }} events
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Top Place Names -->
                <div
                    class="space-y-4 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl dark:border-slate-800 dark:bg-slate-900"
                >
                    <div class="flex items-center justify-between">
                        <h3
                            class="flex items-center gap-2 text-sm font-extrabold text-slate-900 dark:text-white"
                        >
                            <MapPin
                                class="h-4 w-4 text-rose-600 dark:text-rose-400"
                            />
                            Top Place Names
                        </h3>
                        <span class="text-xs font-bold text-slate-400"
                            >Events</span
                        >
                    </div>

                    <div class="space-y-2.5">
                        <div
                            v-for="(item, idx) in statsData.top_places?.slice(
                                0,
                                10,
                            )"
                            :key="'pl-' + idx"
                            class="flex items-center justify-between rounded-xl border border-slate-100 bg-slate-50 p-2 text-xs dark:border-slate-800 dark:bg-slate-800/50"
                        >
                            <span
                                class="max-w-[200px] truncate font-bold text-slate-800 dark:text-slate-200"
                                :title="item.place"
                            >
                                {{ item.place }}
                            </span>
                            <span
                                class="shrink-0 rounded-lg bg-rose-100 px-2 py-0.5 text-[11px] font-extrabold text-rose-700 dark:bg-rose-950 dark:text-rose-300"
                            >
                                {{ item.count }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lifespan Averages & Demographics Footer -->
            <div
                v-if="statsData.lifespan_averages"
                class="flex flex-col items-center justify-between gap-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-xl md:flex-row dark:border-slate-800 dark:bg-slate-900"
            >
                <div class="space-y-1">
                    <h4
                        class="flex items-center gap-2 text-sm font-extrabold text-slate-900 dark:text-white"
                    >
                        <Clock
                            class="h-4 w-4 text-indigo-600 dark:text-indigo-400"
                        />
                        Average Lifespan Benchmarks
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Calculated from verified birth and death dates in your
                        authorized lineage.
                    </p>
                </div>

                <div class="flex items-center gap-4 sm:gap-8">
                    <div class="text-center">
                        <div
                            class="text-xl font-black text-indigo-600 dark:text-indigo-400"
                        >
                            {{ statsData.lifespan_averages.overall || '—' }}
                            <span class="text-xs font-normal">years</span>
                        </div>
                        <div
                            class="mt-0.5 text-[10px] font-bold text-slate-400 uppercase"
                        >
                            Overall Avg
                        </div>
                    </div>
                    <div class="h-8 w-px bg-slate-200 dark:bg-slate-800"></div>
                    <div class="text-center">
                        <div
                            class="text-xl font-black text-blue-600 dark:text-blue-400"
                        >
                            {{ statsData.lifespan_averages.male || '—' }}
                            <span class="text-xs font-normal">years</span>
                        </div>
                        <div
                            class="mt-0.5 text-[10px] font-bold text-slate-400 uppercase"
                        >
                            Male Avg
                        </div>
                    </div>
                    <div class="h-8 w-px bg-slate-200 dark:bg-slate-800"></div>
                    <div class="text-center">
                        <div
                            class="text-xl font-black text-pink-600 dark:text-pink-400"
                        >
                            {{ statsData.lifespan_averages.female || '—' }}
                            <span class="text-xs font-normal">years</span>
                        </div>
                        <div
                            class="mt-0.5 text-[10px] font-bold text-slate-400 uppercase"
                        >
                            Female Avg
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
