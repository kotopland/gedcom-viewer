<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue';
import {
    X, User, Calendar, MapPin, Heart, Users, FileText, Image as ImageIcon,
    FileCode, Music, Volume2, Download, ExternalLink, ChevronRight,
    Baby, Sparkles, Briefcase, Home, GraduationCap, Globe, ClipboardList, Cross, Activity
} from '@lucide/vue';


const props = defineProps<{
    personId: string | null;
}>();

const emit = defineEmits<{
    (e: 'close'): void;
    (e: 'select-person', id: string): void;
    (e: 'open-in-tree', id: string): void;
}>();

const loading = ref(false);
const personData = ref<any>(null);
const activeTab = ref<'overview' | 'family' | 'media' | 'notes'>('overview');
const activeMediaPreview = ref<any>(null);

const fetchPersonDetails = async (id: string) => {
    loading.value = true;
    try {
        const res = await fetch(`/api/gedcom/person/${id}`);
        if (res.ok) {
            personData.value = await res.json();
        }
    } catch (e) {
        console.error('Failed to fetch person details:', e);
    } finally {
        loading.value = false;
    }
};

watch(() => props.personId, (newId) => {
    if (newId) {
        fetchPersonDetails(newId);
        activeTab.value = 'overview';
        activeMediaPreview.value = null;
    } else {
        personData.value = null;
    }
}, { immediate: true });

const handleKeyDown = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && props.personId) {
        emit('close');
    }
};

onMounted(() => {
    window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
    window.removeEventListener('keydown', handleKeyDown);
});

const getGenderColor = (sex: string) => {
    if (sex === 'M') return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800';
    if (sex === 'F') return 'bg-pink-500/10 text-pink-600 dark:text-pink-400 border-pink-200 dark:border-pink-800';
    return 'bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-800';
};

const getEventBadgeStyle = (tag: string) => {
    switch (tag) {
        case 'BIRT':
            return { icon: Baby, bg: 'bg-indigo-600', ring: 'border-indigo-200 dark:border-indigo-900', text: 'text-indigo-600 dark:text-indigo-400' };
        case 'CHR':
        case 'BAPT':
        case 'CONF':
        case 'FCOM':
            return { icon: Sparkles, bg: 'bg-blue-500', ring: 'border-blue-200 dark:border-blue-900', text: 'text-blue-600 dark:text-blue-400' };
        case 'MARR':
        case 'ENG':
            return { icon: Heart, bg: 'bg-pink-500', ring: 'border-pink-200 dark:border-pink-900', text: 'text-pink-600 dark:text-pink-400' };
        case 'DIV':
        case 'ANUL':
            return { icon: Heart, bg: 'bg-rose-600', ring: 'border-rose-200 dark:border-rose-900', text: 'text-rose-600 dark:text-rose-400' };
        case 'OCCU':
            return { icon: Briefcase, bg: 'bg-purple-600', ring: 'border-purple-200 dark:border-purple-900', text: 'text-purple-600 dark:text-purple-400' };
        case 'RESI':
            return { icon: Home, bg: 'bg-teal-600', ring: 'border-teal-200 dark:border-teal-900', text: 'text-teal-600 dark:text-teal-400' };
        case 'EDUC':
        case 'GRAD':
            return { icon: GraduationCap, bg: 'bg-emerald-600', ring: 'border-emerald-200 dark:border-emerald-900', text: 'text-emerald-600 dark:text-emerald-400' };
        case 'EMIG':
        case 'IMMI':
            return { icon: Globe, bg: 'bg-cyan-600', ring: 'border-cyan-200 dark:border-cyan-900', text: 'text-cyan-600 dark:text-cyan-400' };
        case 'CENS':
        case 'PROB':
        case 'WILL':
            return { icon: ClipboardList, bg: 'bg-amber-600', ring: 'border-amber-200 dark:border-amber-900', text: 'text-amber-600 dark:text-amber-400' };
        case 'DEAT':
            return { icon: Cross, bg: 'bg-slate-700 dark:bg-slate-600', ring: 'border-slate-300 dark:border-slate-800', text: 'text-slate-600 dark:text-slate-400' };
        case 'BURI':
        case 'CREM':
            return { icon: Cross, bg: 'bg-amber-700', ring: 'border-amber-300 dark:border-amber-900', text: 'text-amber-600 dark:text-amber-400' };
        default:
            return { icon: Calendar, bg: 'bg-indigo-500', ring: 'border-indigo-200 dark:border-indigo-900', text: 'text-indigo-600 dark:text-indigo-400' };
    }
};
</script>

<template>
    <div
        v-if="personId"
        @click="emit('close')"
        class="fixed inset-0 z-50 overflow-y-auto bg-black/60 backdrop-blur-xs flex justify-end transition-opacity cursor-pointer"
    >
        <div
            @click.stop
            class="relative w-full max-w-2xl bg-white dark:bg-slate-900 shadow-2xl min-h-screen flex flex-col border-l border-slate-200 dark:border-slate-800 animate-in slide-in-from-right duration-300 cursor-default"
        >
            <!-- Header section -->
            <div class="relative bg-gradient-to-r from-slate-900 to-indigo-950 text-white p-6 pb-8 border-b border-slate-800">
                <button
                    @click="emit('close')"
                    class="absolute top-4 right-4 p-2 rounded-full bg-white/10 hover:bg-white/20 text-slate-200 transition-colors"
                    title="Close (Esc)"
                >
                    <X class="w-5 h-5" />
                </button>

                <div class="flex items-start gap-5 mt-2">
                    <div class="relative shrink-0">
                        <img
                            v-if="personData?.person?.primary_media"
                            :src="personData.person.primary_media.url"
                            :alt="personData.person.name"
                            class="w-24 h-24 rounded-2xl object-cover border-2 border-white/20 shadow-md bg-slate-800"
                        />
                        <div
                            v-else
                            class="w-24 h-24 rounded-2xl bg-slate-800 border-2 border-white/10 flex items-center justify-center text-slate-400"
                        >
                            <User class="w-12 h-12" />
                        </div>
                        <span
                            class="absolute -bottom-2 -right-1 px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider border shadow-xs"
                            :class="getGenderColor(personData?.person?.sex)"
                        >
                            {{ personData?.person?.sex === 'M' ? 'Male' : (personData?.person?.sex === 'F' ? 'Female' : 'Unknown') }}
                        </span>
                    </div>

                    <div class="flex-1 min-w-0 pr-6">
                        <h2 class="text-2xl font-bold tracking-tight text-white truncate">
                            {{ personData?.person?.name || 'Loading...' }}
                        </h2>
                        
                        <p class="text-slate-300 font-medium text-sm mt-1 flex items-center gap-2">
                            <Calendar class="w-4 h-4 text-indigo-400 shrink-0" />
                            <span>
                                {{ personData?.person?.birth_year || '?' }} – {{ personData?.person?.death_year || 'Present/Unknown' }}
                            </span>
                        </p>

                        <p v-if="personData?.person?.birth_place" class="text-slate-400 text-xs mt-1.5 flex items-center gap-1.5 truncate">
                            <MapPin class="w-3.5 h-3.5 text-indigo-400 shrink-0" />
                            <span class="truncate">{{ personData.person.birth_place }}</span>
                        </p>

                        <div class="flex items-center gap-2 mt-4">
                            <button
                                @click="emit('open-in-tree', personId)"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition-colors shadow-xs"
                            >
                                <Users class="w-3.5 h-3.5" />
                                View in Tree
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabs navigation -->
                <div class="flex items-center gap-1 mt-6 border-b border-white/10 text-xs font-medium">
                    <button
                        @click="activeTab = 'overview'"
                        class="px-4 py-2.5 rounded-t-lg transition-colors flex items-center gap-1.5 border-b-2 font-semibold"
                        :class="activeTab === 'overview' ? 'border-indigo-400 text-indigo-300 bg-white/5' : 'border-transparent text-slate-400 hover:text-slate-200'"
                    >
                        <User class="w-3.5 h-3.5" />
                        Overview
                    </button>
                    <button
                        @click="activeTab = 'family'"
                        class="px-4 py-2.5 rounded-t-lg transition-colors flex items-center gap-1.5 border-b-2 font-semibold"
                        :class="activeTab === 'family' ? 'border-indigo-400 text-indigo-300 bg-white/5' : 'border-transparent text-slate-400 hover:text-slate-200'"
                    >
                        <Heart class="w-3.5 h-3.5" />
                        Family
                    </button>
                    <button
                        @click="activeTab = 'media'"
                        class="px-4 py-2.5 rounded-t-lg transition-colors flex items-center gap-1.5 border-b-2 font-semibold"
                        :class="activeTab === 'media' ? 'border-indigo-400 text-indigo-300 bg-white/5' : 'border-transparent text-slate-400 hover:text-slate-200'"
                    >
                        <ImageIcon class="w-3.5 h-3.5" />
                        Media ({{ personData?.person?.media_items?.length || 0 }})
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="flex-1 p-6 overflow-y-auto">
                <div v-if="loading" class="flex flex-col items-center justify-center py-16 text-slate-400">
                    <div class="w-8 h-8 border-3 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                    <p class="mt-3 text-sm">Loading profile details...</p>
                </div>

                <div v-else-if="personData">
                    <!-- Overview Tab -->
                    <div v-if="activeTab === 'overview'" class="space-y-6">
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-5 border border-slate-200 dark:border-slate-800">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-1.5">
                                    <Calendar class="w-4 h-4 text-indigo-500" />
                                    Life Events & Timeline ({{ personData.person.events?.length || 0 }})
                                </h3>
                            </div>

                            <div v-if="personData.person.events && personData.person.events.length > 0" class="relative border-l-2 border-indigo-200 dark:border-indigo-900/50 ml-3.5 space-y-6 pt-1">
                                <div
                                    v-for="ev in personData.person.events"
                                    :key="ev.id"
                                    class="relative pl-6 group"
                                >
                                    <!-- Badge Indicator -->
                                    <span
                                        class="absolute -left-[9px] top-1 w-4 h-4 rounded-full border-2 border-white dark:border-slate-900 flex items-center justify-center text-white"
                                        :class="getEventBadgeStyle(ev.tag).bg"
                                    ></span>

                                    <div class="flex flex-wrap items-baseline justify-between gap-x-2">
                                        <div class="font-bold text-slate-900 dark:text-white text-sm flex items-center gap-2">
                                            <span>{{ ev.title }}</span>
                                            <span v-if="ev.value" class="text-xs font-normal text-slate-600 dark:text-slate-300 bg-slate-200/60 dark:bg-slate-700/60 px-2 py-0.5 rounded-md">
                                                {{ ev.value }}
                                            </span>
                                        </div>

                                        <div v-if="ev.date" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                            {{ ev.date }}
                                        </div>
                                        <div v-else-if="ev.year" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                            {{ ev.year }}
                                        </div>
                                    </div>

                                    <!-- Spouse Link if Marriage Event -->
                                    <div v-if="ev.spouse" class="mt-1.5 flex items-center gap-2">
                                        <span class="text-xs text-slate-500 dark:text-slate-400">Spouse:</span>
                                        <button
                                            @click="emit('select-person', ev.spouse.id)"
                                            class="inline-flex items-center gap-1.5 text-xs font-semibold text-pink-600 dark:text-pink-400 hover:underline bg-pink-500/10 px-2.5 py-1 rounded-lg border border-pink-200 dark:border-pink-900/50"
                                        >
                                            <Heart class="w-3 h-3 text-pink-500" />
                                            {{ ev.spouse.name }}
                                        </button>
                                    </div>

                                    <!-- Place -->
                                    <div v-if="ev.place" class="text-xs text-slate-500 dark:text-slate-400 mt-1 flex items-center gap-1">
                                        <MapPin class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                        <span>{{ ev.place }}</span>
                                    </div>

                                    <!-- Age / Cause -->
                                    <div v-if="ev.age || ev.cause" class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5 flex flex-wrap gap-2">
                                        <span v-if="ev.age">Age: {{ ev.age }}</span>
                                        <span v-if="ev.cause">Cause: {{ ev.cause }}</span>
                                    </div>

                                    <!-- Note -->
                                    <div v-if="ev.note" class="mt-1.5 text-xs text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800 whitespace-pre-line leading-relaxed">
                                        {{ ev.note }}
                                    </div>
                                </div>
                            </div>

                            <div v-else class="text-xs text-slate-400 italic py-4">
                                No life events recorded.
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <div v-if="personData.person.notes && personData.person.notes.length > 0" class="bg-amber-500/5 rounded-xl p-5 border border-amber-500/20">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400 mb-2 flex items-center gap-1.5">
                                <FileText class="w-4 h-4" />
                                Historic Notes & Records
                            </h3>
                            <div class="space-y-3">
                                <p v-for="(note, idx) in personData.person.notes" :key="idx" class="text-xs text-slate-700 dark:text-slate-300 whitespace-pre-line leading-relaxed">
                                    {{ note }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Family Tab -->
                    <div v-if="activeTab === 'family'" class="space-y-6">
                        <!-- Parents -->
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4 border border-slate-200 dark:border-slate-800">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-1.5">
                                <Users class="w-3.5 h-3.5 text-indigo-500" />
                                Parents
                            </h4>
                            <div v-if="personData.relations.parents.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div
                                    v-for="p in personData.relations.parents"
                                    :key="p.id"
                                    @click="emit('select-person', p.id)"
                                    class="flex items-center gap-3 p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-indigo-500 dark:hover:border-indigo-500 cursor-pointer transition-all shadow-2xs group"
                                >
                                    <img v-if="p.primary_media" :src="p.primary_media.url" class="w-10 h-10 rounded-full object-cover shrink-0" />
                                    <div v-else class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-slate-500 shrink-0">
                                        <User class="w-5 h-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-semibold text-slate-900 dark:text-white truncate group-hover:text-indigo-600 dark:group-hover:text-indigo-400">
                                            {{ p.name }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ p.birth_year || '?' }} – {{ p.death_year || '?' }}
                                        </div>
                                    </div>
                                    <ChevronRight class="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic">No parents recorded</div>
                        </div>

                        <!-- Spouses -->
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4 border border-slate-200 dark:border-slate-800">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-1.5">
                                <Heart class="w-3.5 h-3.5 text-pink-500" />
                                Spouses / Partners
                            </h4>
                            <div v-if="personData.relations.spouses.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div
                                    v-for="s in personData.relations.spouses"
                                    :key="s.id"
                                    @click="emit('select-person', s.id)"
                                    class="flex items-center gap-3 p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-pink-500 dark:hover:border-pink-500 cursor-pointer transition-all shadow-2xs group"
                                >
                                    <img v-if="s.primary_media" :src="s.primary_media.url" class="w-10 h-10 rounded-full object-cover shrink-0" />
                                    <div v-else class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-slate-500 shrink-0">
                                        <User class="w-5 h-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-semibold text-slate-900 dark:text-white truncate group-hover:text-pink-600 dark:group-hover:text-pink-400">
                                            {{ s.name }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ s.birth_year || '?' }} – {{ s.death_year || '?' }}
                                        </div>
                                    </div>
                                    <ChevronRight class="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic">No spouse recorded</div>
                        </div>

                        <!-- Children -->
                        <div class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4 border border-slate-200 dark:border-slate-800">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-1.5">
                                <Users class="w-3.5 h-3.5 text-emerald-500" />
                                Children ({{ personData.relations.children.length }})
                            </h4>
                            <div v-if="personData.relations.children.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div
                                    v-for="c in personData.relations.children"
                                    :key="c.id"
                                    @click="emit('select-person', c.id)"
                                    class="flex items-center gap-3 p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-emerald-500 dark:hover:border-emerald-500 cursor-pointer transition-all shadow-2xs group"
                                >
                                    <img v-if="c.primary_media" :src="c.primary_media.url" class="w-10 h-10 rounded-full object-cover shrink-0" />
                                    <div v-else class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-slate-500 shrink-0">
                                        <User class="w-5 h-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-semibold text-slate-900 dark:text-white truncate group-hover:text-emerald-600 dark:group-hover:text-emerald-400">
                                            {{ c.name }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ c.birth_year || '?' }} – {{ c.death_year || '?' }}
                                        </div>
                                    </div>
                                    <ChevronRight class="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic">No children recorded</div>
                        </div>

                        <!-- Siblings -->
                        <div v-if="personData.relations.siblings.length > 0" class="bg-slate-50 dark:bg-slate-800/50 rounded-xl p-4 border border-slate-200 dark:border-slate-800">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3 flex items-center gap-1.5">
                                <Users class="w-3.5 h-3.5 text-blue-500" />
                                Siblings ({{ personData.relations.siblings.length }})
                            </h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div
                                    v-for="sb in personData.relations.siblings"
                                    :key="sb.id"
                                    @click="emit('select-person', sb.id)"
                                    class="flex items-center gap-3 p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-blue-500 dark:hover:border-blue-500 cursor-pointer transition-all shadow-2xs group"
                                >
                                    <img v-if="sb.primary_media" :src="sb.primary_media.url" class="w-10 h-10 rounded-full object-cover shrink-0" />
                                    <div v-else class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-slate-500 shrink-0">
                                        <User class="w-5 h-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-xs font-semibold text-slate-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400">
                                            {{ sb.name }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ sb.birth_year || '?' }} – {{ sb.death_year || '?' }}
                                        </div>
                                    </div>
                                    <ChevronRight class="w-4 h-4 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Media Tab -->
                    <div v-if="activeTab === 'media'" class="space-y-4">
                        <div v-if="personData.person.media_items && personData.person.media_items.length > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                            <div
                                v-for="m in personData.person.media_items"
                                :key="m.id"
                                @click="activeMediaPreview = m"
                                class="group relative rounded-xl border border-slate-200 dark:border-slate-800 overflow-hidden bg-slate-900 cursor-pointer aspect-square"
                            >
                                <img
                                    v-if="m.file.match(/\.(jpg|jpeg|png|gif|webp)$/i)"
                                    :src="m.url"
                                    :alt="m.title"
                                    class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                />
                                <div v-else-if="m.file.match(/\.pdf$/i)" class="w-full h-full flex flex-col items-center justify-center bg-red-950/40 text-red-400 p-4">
                                    <FileCode class="w-10 h-10 mb-2" />
                                    <span class="text-xs font-semibold truncate max-w-full px-2">{{ m.title }}</span>
                                </div>
                                <div v-else-if="m.file.match(/\.(m4a|mp3|wav|ogg)$/i)" class="w-full h-full flex flex-col items-center justify-center bg-amber-950/40 text-amber-400 p-4">
                                    <Volume2 class="w-10 h-10 mb-2" />
                                    <span class="text-xs font-semibold truncate max-w-full px-2">{{ m.title }}</span>
                                </div>
                                <div v-else class="w-full h-full flex flex-col items-center justify-center bg-slate-800 text-slate-400 p-4">
                                    <FileText class="w-10 h-10 mb-2" />
                                    <span class="text-xs font-semibold truncate max-w-full px-2">{{ m.title }}</span>
                                </div>

                                <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent opacity-0 group-hover:opacity-100 transition-opacity p-2.5 flex items-end">
                                    <span class="text-[11px] font-medium text-white truncate">{{ m.title }}</span>
                                </div>
                            </div>
                        </div>

                        <div v-else class="text-center py-12 text-slate-400 bg-slate-50 dark:bg-slate-800/30 rounded-xl border border-dashed border-slate-300 dark:border-slate-800">
                            <ImageIcon class="w-10 h-10 mx-auto mb-2 text-slate-400 opacity-50" />
                            <p class="text-sm">No media files attached to this person</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Media Preview Lightbox Modal -->
            <div v-if="activeMediaPreview" class="fixed inset-0 z-60 bg-black/90 flex flex-col items-center justify-center p-6 animate-in fade-in">
                <button
                    @click="activeMediaPreview = null"
                    class="absolute top-6 right-6 p-3 rounded-full bg-white/10 hover:bg-white/20 text-white transition-colors"
                >
                    <X class="w-6 h-6" />
                </button>

                <div class="max-w-4xl max-h-[80vh] flex flex-col items-center justify-center w-full">
                    <img
                        v-if="activeMediaPreview.file.match(/\.(jpg|jpeg|png|gif|webp)$/i)"
                        :src="activeMediaPreview.url"
                        :alt="activeMediaPreview.title"
                        class="max-w-full max-h-[70vh] rounded-lg object-contain shadow-2xl"
                    />

                    <iframe
                        v-else-if="activeMediaPreview.file.match(/\.pdf$/i)"
                        :src="activeMediaPreview.url"
                        class="w-full h-[70vh] rounded-lg border border-slate-800 shadow-2xl"
                    ></iframe>

                    <div v-else-if="activeMediaPreview.file.match(/\.(m4a|mp3|wav|ogg)$/i)" class="bg-slate-900 p-8 rounded-2xl border border-slate-800 flex flex-col items-center gap-4 w-full max-w-md">
                        <Music class="w-16 h-16 text-amber-400" />
                        <span class="text-white font-medium text-center">{{ activeMediaPreview.title }}</span>
                        <audio controls class="w-full">
                            <source :src="activeMediaPreview.url" type="audio/mp4" />
                            Your browser does not support audio playback.
                        </audio>
                    </div>

                    <div class="mt-4 flex items-center justify-between w-full text-slate-300 text-sm">
                        <span class="font-medium truncate max-w-lg">{{ activeMediaPreview.title }}</span>
                        <a
                            :href="activeMediaPreview.url"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold rounded-lg transition-colors"
                        >
                            <ExternalLink class="w-4 h-4" />
                            Open Original
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
