<script setup lang="ts">
import { ref, onMounted } from 'vue';
import {
    BarChart2, Users, MapPin, Globe, Award, Heart, Shield, Sparkles,
    Calendar, User, Clock, Flame, ChevronRight, TrendingUp
} from '@lucide/vue';

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
        <div class="bg-gradient-to-r from-indigo-900 via-indigo-800 to-slate-900 rounded-3xl p-6 sm:p-8 text-white shadow-2xl relative overflow-hidden">
            <div class="absolute -right-10 -top-10 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="relative z-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/20 text-indigo-300 text-xs font-extrabold border border-indigo-500/30">
                        <BarChart2 class="w-3.5 h-3.5" />
                        <span>Permission-Scoped Lineage Analytics</span>
                    </div>
                    <h2 class="text-2xl sm:text-3xl font-black tracking-tight text-white">
                        Genealogy Statistics & Insights
                    </h2>
                    <p class="text-xs sm:text-sm text-indigo-200/80 max-w-2xl leading-relaxed">
                        Explore surname breakdowns, geographical locations, lifespan benchmarks, and notable records strictly authorized for your account.
                    </p>
                </div>

                <button
                    @click="fetchStats"
                    class="self-start md:self-auto px-4 py-2.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white text-xs font-bold transition-all border border-white/20 flex items-center gap-2 cursor-pointer backdrop-blur-md"
                >
                    <TrendingUp class="w-4 h-4" />
                    <span>Refresh Stats</span>
                </button>
            </div>
        </div>

        <div v-if="loading" class="flex flex-col items-center justify-center py-20 text-slate-400">
            <div class="w-10 h-10 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
            <p class="mt-4 text-xs font-semibold text-slate-600 dark:text-slate-300">Calculating genealogy statistics...</p>
        </div>

        <template v-else-if="statsData">
            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4">
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wider">Individuals</span>
                        <User class="w-4 h-4 text-indigo-500" />
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ statsData.totals?.total_individuals || 0 }}
                    </div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold flex items-center gap-1">
                        <span>{{ statsData.totals?.males || 0 }} males</span>
                        <span>•</span>
                        <span>{{ statsData.totals?.females || 0 }} females</span>
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wider">Families</span>
                        <Users class="w-4 h-4 text-purple-500" />
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ statsData.totals?.total_families || 0 }}
                    </div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">
                        Recorded marriages & lineages
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wider">Countries</span>
                        <Globe class="w-4 h-4 text-emerald-500" />
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ statsData.top_countries?.length || 0 }}
                    </div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold truncate">
                        Global locations recorded
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-900 rounded-3xl p-4 sm:p-5 border border-slate-200 dark:border-slate-800 shadow-sm space-y-1">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-[11px] font-bold uppercase tracking-wider">Lifespan Span</span>
                        <Clock class="w-4 h-4 text-amber-500" />
                    </div>
                    <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white">
                        {{ statsData.generations_span?.total_years_span || '—' }} <span class="text-xs font-bold text-slate-400">yrs</span>
                    </div>
                    <div class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">
                        {{ statsData.generations_span?.earliest_birth_year || '?' }} – {{ statsData.generations_span?.latest_birth_year || 'Present' }}
                    </div>
                </div>
            </div>

            <!-- Featured Records Spotlight Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Oldest Verified Person Card -->
                <div
                    v-if="statsData.oldest_person"
                    @click="emit('select-person', statsData.oldest_person.id)"
                    class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xl flex items-center gap-5 hover:border-indigo-500 dark:hover:border-indigo-500 transition-all cursor-pointer group relative overflow-hidden"
                >
                    <div class="absolute right-0 top-0 bottom-0 w-2 bg-indigo-600"></div>

                    <!-- Photo Thumbnail -->
                    <img
                        v-if="statsData.oldest_person.primary_media?.url"
                        :src="statsData.oldest_person.primary_media.url"
                        class="w-16 h-16 rounded-2xl object-cover border-2 border-indigo-500/40 shrink-0 shadow-md group-hover:scale-105 transition-transform"
                    />
                    <div v-else class="w-16 h-16 rounded-2xl bg-indigo-600/10 text-indigo-600 dark:text-indigo-400 flex items-center justify-center font-bold shrink-0">
                        <Award class="w-8 h-8" />
                    </div>

                    <div class="min-w-0 flex-1 space-y-1">
                        <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 text-[10px] font-extrabold uppercase tracking-wide">
                            <Award class="w-3 h-3" />
                            <span>Oldest Verified Person</span>
                        </div>
                        <h3 class="font-extrabold text-base text-slate-900 dark:text-white truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                            {{ statsData.oldest_person.name }}
                        </h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium">
                            Lived <span class="font-black text-indigo-600 dark:text-indigo-400 text-sm">{{ statsData.oldest_person.age }} years</span>
                            ({{ statsData.oldest_person.birth_year }} – {{ statsData.oldest_person.death_year }})
                        </p>
                    </div>
                </div>

                <!-- Biggest Age Gap Married Couple Card -->
                <div
                    v-if="statsData.biggest_age_difference_couple"
                    class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-3 relative overflow-hidden"
                >
                    <div class="flex items-center justify-between">
                        <div class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400 text-[10px] font-extrabold uppercase tracking-wide">
                            <Heart class="w-3 h-3" />
                            <span>Largest Couple Age Difference</span>
                        </div>
                        <span class="text-xs font-black text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 px-2.5 py-1 rounded-xl border border-rose-200 dark:border-rose-800">
                            {{ statsData.biggest_age_difference_couple.age_difference }} yrs age gap
                        </span>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <!-- Husband -->
                        <div
                            @click="emit('select-person', statsData.biggest_age_difference_couple.husband.id)"
                            class="p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 hover:border-indigo-500 cursor-pointer flex items-center gap-2.5 transition-all"
                        >
                            <img
                                v-if="statsData.biggest_age_difference_couple.husband.primary_media?.url"
                                :src="statsData.biggest_age_difference_couple.husband.primary_media.url"
                                class="w-9 h-9 rounded-xl object-cover shrink-0"
                            />
                            <div v-else class="w-9 h-9 rounded-xl bg-blue-500/10 text-blue-600 flex items-center justify-center shrink-0">
                                <User class="w-4 h-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-[11px] font-bold text-slate-900 dark:text-white truncate">
                                    {{ statsData.biggest_age_difference_couple.husband.name }}
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">
                                    b.{{ statsData.biggest_age_difference_couple.husband.birth_year }}
                                </div>
                            </div>
                        </div>

                        <!-- Wife -->
                        <div
                            @click="emit('select-person', statsData.biggest_age_difference_couple.wife.id)"
                            class="p-2.5 rounded-2xl bg-slate-50 dark:bg-slate-800/60 border border-slate-200 dark:border-slate-700/60 hover:border-indigo-500 cursor-pointer flex items-center gap-2.5 transition-all"
                        >
                            <img
                                v-if="statsData.biggest_age_difference_couple.wife.primary_media?.url"
                                :src="statsData.biggest_age_difference_couple.wife.primary_media.url"
                                class="w-9 h-9 rounded-xl object-cover shrink-0"
                            />
                            <div v-else class="w-9 h-9 rounded-xl bg-pink-500/10 text-pink-600 flex items-center justify-center shrink-0">
                                <User class="w-4 h-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="text-[11px] font-bold text-slate-900 dark:text-white truncate">
                                    {{ statsData.biggest_age_difference_couple.wife.name }}
                                </div>
                                <div class="text-[10px] text-slate-500 dark:text-slate-400">
                                    b.{{ statsData.biggest_age_difference_couple.wife.birth_year }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Breakdowns: Surnames, Countries, Place Names -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Top Surnames -->
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                            <User class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                            Top Surnames
                        </h3>
                        <span class="text-xs font-bold text-slate-400">Frequency</span>
                    </div>

                    <div class="space-y-2.5">
                        <div
                            v-for="(item, idx) in statsData.top_surnames?.slice(0, 10)"
                            :key="'sn-' + idx"
                            class="flex items-center justify-between text-xs p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800"
                        >
                            <span class="font-extrabold text-slate-800 dark:text-slate-200">
                                #{{ idx + 1 }} {{ item.surname }}
                            </span>
                            <span class="px-2 py-0.5 rounded-lg bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 font-extrabold text-[11px]">
                                {{ item.count }} people
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Top Countries -->
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                            <Globe class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
                            Top Countries
                        </h3>
                        <span class="text-xs font-bold text-slate-400">Locations</span>
                    </div>

                    <div class="space-y-2.5">
                        <div
                            v-for="(item, idx) in statsData.top_countries?.slice(0, 10)"
                            :key="'co-' + idx"
                            class="flex items-center justify-between text-xs p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800"
                        >
                            <span class="font-extrabold text-slate-800 dark:text-slate-200 flex items-center gap-2">
                                <span>🌐</span> {{ item.country }}
                            </span>
                            <span class="px-2 py-0.5 rounded-lg bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-extrabold text-[11px]">
                                {{ item.count }} events
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Top Place Names -->
                <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                            <MapPin class="w-4 h-4 text-rose-600 dark:text-rose-400" />
                            Top Place Names
                        </h3>
                        <span class="text-xs font-bold text-slate-400">Events</span>
                    </div>

                    <div class="space-y-2.5">
                        <div
                            v-for="(item, idx) in statsData.top_places?.slice(0, 10)"
                            :key="'pl-' + idx"
                            class="flex items-center justify-between text-xs p-2 rounded-xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-800"
                        >
                            <span class="font-bold text-slate-800 dark:text-slate-200 truncate max-w-[200px]" :title="item.place">
                                {{ item.place }}
                            </span>
                            <span class="px-2 py-0.5 rounded-lg bg-rose-100 dark:bg-rose-950 text-rose-700 dark:text-rose-300 font-extrabold text-[11px] shrink-0">
                                {{ item.count }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Lifespan Averages & Demographics Footer -->
            <div
                v-if="statsData.lifespan_averages"
                class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-xl flex flex-col md:flex-row items-center justify-between gap-6"
            >
                <div class="space-y-1">
                    <h4 class="font-extrabold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                        <Clock class="w-4 h-4 text-indigo-600 dark:text-indigo-400" />
                        Average Lifespan Benchmarks
                    </h4>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        Calculated from verified birth and death dates in your authorized lineage.
                    </p>
                </div>

                <div class="flex items-center gap-4 sm:gap-8">
                    <div class="text-center">
                        <div class="text-xl font-black text-indigo-600 dark:text-indigo-400">
                            {{ statsData.lifespan_averages.overall || '—' }} <span class="text-xs font-normal">years</span>
                        </div>
                        <div class="text-[10px] font-bold uppercase text-slate-400 mt-0.5">Overall Avg</div>
                    </div>
                    <div class="h-8 w-px bg-slate-200 dark:bg-slate-800"></div>
                    <div class="text-center">
                        <div class="text-xl font-black text-blue-600 dark:text-blue-400">
                            {{ statsData.lifespan_averages.male || '—' }} <span class="text-xs font-normal">years</span>
                        </div>
                        <div class="text-[10px] font-bold uppercase text-slate-400 mt-0.5">Male Avg</div>
                    </div>
                    <div class="h-8 w-px bg-slate-200 dark:bg-slate-800"></div>
                    <div class="text-center">
                        <div class="text-xl font-black text-pink-600 dark:text-pink-400">
                            {{ statsData.lifespan_averages.female || '—' }} <span class="text-xs font-normal">years</span>
                        </div>
                        <div class="text-[10px] font-bold uppercase text-slate-400 mt-0.5">Female Avg</div>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
