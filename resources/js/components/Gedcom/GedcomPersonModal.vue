<script setup lang="ts">
import {
    X,
    User,
    Calendar,
    MapPin,
    Heart,
    Users,
    FileText,
    Image as ImageIcon,
    FileCode,
    Music,
    Volume2,
    ExternalLink,
    ChevronRight,
    Target,
    Baby,
    Sparkles,
    Briefcase,
    Home,
    GraduationCap,
    Globe,
    ClipboardList,
    Cross,
    Send,
    Upload,
    FileUp,
    CheckCircle2,
    AlertCircle,
    Trash2,
    Printer,
} from '@lucide/vue';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';

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
const activeTab = ref<'overview' | 'family' | 'media' | 'notes' | 'contribute'>(
    'overview',
);
const activeMediaPreview = ref<any>(null);

const contributionNote = ref('');
const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
const submittingContribution = ref(false);
const contributionSuccess = ref(false);
const contributionError = ref<string | null>(null);

const handleFileSelect = (e: Event) => {
    const target = e.target as HTMLInputElement;

    if (target.files && target.files.length > 0) {
        selectedFile.value = target.files[0];
    }
};

const removeSelectedFile = () => {
    selectedFile.value = null;

    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const submitContribution = async () => {
    if (!props.personId) {
        return;
    }

    if (!contributionNote.value.trim() && !selectedFile.value) {
        contributionError.value =
            'Please write a note or select a file to upload.';

        return;
    }

    submittingContribution.value = true;
    contributionError.value = null;
    contributionSuccess.value = false;

    try {
        const formData = new FormData();

        if (contributionNote.value.trim()) {
            formData.append('note', contributionNote.value.trim());
        }

        if (selectedFile.value) {
            formData.append('media', selectedFile.value);
        }

        const csrfToken =
            (
                document.querySelector(
                    'meta[name="csrf-token"]',
                ) as HTMLMetaElement
            )?.content || '';

        const res = await fetch(
            `/api/gedcom/person/${props.personId}/contribution`,
            {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
                body: formData,
            },
        );

        const data = await res.json();

        if (res.ok) {
            contributionSuccess.value = true;
            contributionNote.value = '';
            selectedFile.value = null;

            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
        } else {
            contributionError.value =
                data.error || data.message || 'Failed to send contribution.';
        }
    } catch (e: any) {
        console.error('Failed to submit contribution:', e);
        contributionError.value =
            'An unexpected error occurred while sending your contribution.';
    } finally {
        submittingContribution.value = false;
    }
};

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

watch(
    () => props.personId,
    (newId) => {
        if (newId) {
            fetchPersonDetails(newId);
            activeTab.value = 'overview';
            activeMediaPreview.value = null;
        } else {
            personData.value = null;
        }
    },
    { immediate: true },
);

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
    if (sex === 'M') {
        return 'bg-blue-500/10 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800';
    }

    if (sex === 'F') {
        return 'bg-pink-500/10 text-pink-600 dark:text-pink-400 border-pink-200 dark:border-pink-800';
    }

    return 'bg-gray-500/10 text-gray-600 dark:text-gray-400 border-gray-200 dark:border-gray-800';
};

const displayName = computed(() => {
    if (!personData.value?.person) {
        return 'Loading...';
    }

    const p = personData.value.person;

    // Check if name already has parentheses
    if (p.name && p.name.includes('(') && p.name.includes(')')) {
        return p.name;
    }

    // Check all_names for married name / maiden name
    if (p.all_names && Array.isArray(p.all_names)) {
        const marriedName = p.all_names.find(
            (n: any) =>
                (n.type === 'married' ||
                    (n.surname && n.surname !== p.surname)) &&
                n.surname,
        );

        if (marriedName && marriedName.surname && p.surname) {
            return `${p.given_name || ''} ${p.surname} (${marriedName.surname})`.trim();
        }
    }

    // Check if spouse surname differs and person is female with marriage
    const firstSpouse = personData.value.relations?.spouses?.[0];

    if (
        firstSpouse &&
        firstSpouse.surname &&
        p.surname &&
        p.sex === 'F' &&
        firstSpouse.surname !== p.surname
    ) {
        return `${p.name} (${firstSpouse.surname})`;
    }

    return p.name || 'Unknown';
});

const getEventBadgeStyle = (tag: string, title?: string) => {
    if (
        tag === 'MARR' ||
        tag === 'ENG' ||
        tag === '_PRS' ||
        (title &&
            /\b(marriage|married|wedding|partner|civil partnership)\b/i.test(
                title,
            ))
    ) {
        return {
            icon: Heart,
            bg: 'bg-pink-500',
            ring: 'border-pink-200 dark:border-pink-900',
            text: 'text-pink-600 dark:text-pink-400',
        };
    }

    switch (tag) {
        case 'BIRT':
            return {
                icon: Baby,
                bg: 'bg-indigo-600',
                ring: 'border-indigo-200 dark:border-indigo-900',
                text: 'text-indigo-600 dark:text-indigo-400',
            };
        case 'CHR':
        case 'BAPT':
        case 'CONF':
        case 'FCOM':
            return {
                icon: Sparkles,
                bg: 'bg-blue-500',
                ring: 'border-blue-200 dark:border-blue-900',
                text: 'text-blue-600 dark:text-blue-400',
            };
        case 'MARR':
        case 'ENG':
            return {
                icon: Heart,
                bg: 'bg-pink-500',
                ring: 'border-pink-200 dark:border-pink-900',
                text: 'text-pink-600 dark:text-pink-400',
            };
        case 'DIV':
        case 'ANUL':
            return {
                icon: Heart,
                bg: 'bg-rose-600',
                ring: 'border-rose-200 dark:border-rose-900',
                text: 'text-rose-600 dark:text-rose-400',
            };
        case 'OCCU':
            return {
                icon: Briefcase,
                bg: 'bg-purple-600',
                ring: 'border-purple-200 dark:border-purple-900',
                text: 'text-purple-600 dark:text-purple-400',
            };
        case 'RESI':
            return {
                icon: Home,
                bg: 'bg-teal-600',
                ring: 'border-teal-200 dark:border-teal-900',
                text: 'text-teal-600 dark:text-teal-400',
            };
        case 'EDUC':
        case 'GRAD':
            return {
                icon: GraduationCap,
                bg: 'bg-emerald-600',
                ring: 'border-emerald-200 dark:border-emerald-900',
                text: 'text-emerald-600 dark:text-emerald-400',
            };
        case 'EMIG':
        case 'IMMI':
            return {
                icon: Globe,
                bg: 'bg-cyan-600',
                ring: 'border-cyan-200 dark:border-cyan-900',
                text: 'text-cyan-600 dark:text-cyan-400',
            };
        case 'CENS':
        case 'PROB':
        case 'WILL':
            return {
                icon: ClipboardList,
                bg: 'bg-amber-600',
                ring: 'border-amber-200 dark:border-amber-900',
                text: 'text-amber-600 dark:text-amber-400',
            };
        case 'DEAT':
            return {
                icon: Cross,
                bg: 'bg-slate-700 dark:bg-slate-600',
                ring: 'border-slate-300 dark:border-slate-800',
                text: 'text-slate-600 dark:text-slate-400',
            };
        case 'BURI':
        case 'CREM':
            return {
                icon: Cross,
                bg: 'bg-amber-700',
                ring: 'border-amber-300 dark:border-amber-900',
                text: 'text-amber-600 dark:text-amber-400',
            };
        default:
            return {
                icon: Calendar,
                bg: 'bg-indigo-500',
                ring: 'border-indigo-200 dark:border-indigo-900',
                text: 'text-indigo-600 dark:text-indigo-400',
            };
    }
};
</script>

<template>
    <div
        v-if="personId"
        @click="emit('close')"
        class="fixed inset-0 z-50 flex cursor-pointer justify-end overflow-hidden bg-black/65 p-0 backdrop-blur-xs transition-opacity sm:p-0"
    >
        <div
            @click.stop
            class="relative flex h-full max-h-[100dvh] w-full max-w-[100vw] animate-in cursor-default flex-col overflow-hidden border-l border-slate-200 bg-white shadow-2xl duration-300 slide-in-from-right sm:max-w-2xl dark:border-slate-800 dark:bg-slate-900"
        >
            <!-- Header section -->
            <div
                class="relative z-20 shrink-0 border-b border-slate-800 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-4 pb-4 text-white sm:p-6"
            >
                <!-- Prominent Close Pill Button -->
                <button
                    @click="emit('close')"
                    class="absolute top-3 right-3 z-30 inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-indigo-400/40 bg-indigo-600 px-3 py-1.5 text-xs font-extrabold text-white shadow-xl transition-all hover:bg-indigo-500 active:scale-95 sm:top-4 sm:right-4"
                    title="Close Drawer (Esc)"
                >
                    <X class="h-4 w-4" />
                    <span>Close</span>
                </button>

                <div class="mt-1 flex items-start gap-4 sm:mt-2 sm:gap-5">
                    <div class="relative shrink-0">
                        <img
                            v-if="personData?.person?.primary_media"
                            :src="
                                personData.person.portrait_url ||
                                personData.person.primary_media.portrait_url ||
                                personData.person.primary_media.url
                            "
                            :alt="personData.person.name"
                            class="h-20 w-20 rounded-2xl border-2 border-white/20 bg-slate-800 object-cover shadow-md sm:h-24 sm:w-24"
                        />
                        <div
                            v-else
                            class="flex h-20 w-20 items-center justify-center rounded-2xl border-2 border-white/10 bg-slate-800 text-slate-400 sm:h-24 sm:w-24"
                        >
                            <User class="h-10 w-10 sm:h-12 sm:w-12" />
                        </div>
                        <span
                            class="absolute -right-1 -bottom-2 rounded-full border px-2 py-0.5 text-[10px] font-semibold tracking-wider uppercase shadow-xs sm:px-2.5 sm:text-xs"
                            :class="getGenderColor(personData?.person?.sex)"
                        >
                            {{
                                personData?.person?.sex === 'M'
                                    ? 'Male'
                                    : personData?.person?.sex === 'F'
                                      ? 'Female'
                                      : 'Unknown'
                            }}
                        </span>
                    </div>

                    <div class="min-w-0 flex-1 pr-8">
                        <h2
                            class="truncate text-xl font-bold tracking-tight text-white sm:text-2xl"
                        >
                            {{ displayName }}
                        </h2>

                        <p
                            class="mt-1 flex items-center gap-2 text-xs font-medium text-slate-300 sm:text-sm"
                        >
                            <Calendar
                                class="h-3.5 w-3.5 shrink-0 text-indigo-400 sm:h-4 sm:w-4"
                            />
                            <span>
                                {{ personData?.person?.birth_year || '?' }} –
                                {{
                                    personData?.person?.death_year ||
                                    'Present/Unknown'
                                }}
                            </span>
                        </p>

                        <p
                            v-if="personData?.person?.birth_place"
                            class="mt-1 flex items-center gap-1.5 truncate text-xs text-slate-400"
                        >
                            <MapPin
                                class="h-3.5 w-3.5 shrink-0 text-indigo-400"
                            />
                            <span class="truncate">{{
                                personData.person.birth_place
                            }}</span>
                        </p>

                        <!-- Spouse / Partner Header Link -->
                        <p
                            v-if="personData?.relations?.spouses?.length"
                            class="mt-1 flex items-center gap-1.5 truncate text-xs text-slate-300"
                        >
                            <Heart class="h-3.5 w-3.5 shrink-0 text-pink-400" />
                            <span class="text-slate-400">
                                {{
                                    personData.relations.spouses.length > 1
                                        ? 'Spouses:'
                                        : personData.relations.spouses[0]
                                              .relationship_type || 'Spouse:'
                                }}
                            </span>
                            <span class="flex items-center gap-1.5 truncate">
                                <template
                                    v-for="(sp, sIdx) in personData.relations
                                        .spouses"
                                    :key="sp.id"
                                >
                                    <button
                                        @click="emit('select-person', sp.id)"
                                        class="inline-flex cursor-pointer items-center gap-1 font-semibold text-pink-300 hover:text-pink-200 hover:underline"
                                    >
                                        {{ sp.name }}
                                        <span
                                            v-if="
                                                sp.marriage_year ||
                                                sp.marriage_date
                                            "
                                            class="text-[11px] font-normal text-slate-300"
                                        >
                                            (m.
                                            {{
                                                sp.marriage_year ||
                                                sp.marriage_date
                                            }})
                                        </span>
                                    </button>
                                    <span
                                        v-if="
                                            sIdx <
                                            personData.relations.spouses
                                                .length -
                                                1
                                        "
                                        class="text-slate-500"
                                        >,</span
                                    >
                                </template>
                            </span>
                        </p>

                        <div
                            class="mt-3 flex flex-wrap items-center gap-2 sm:mt-4"
                        >
                            <button
                                @click="
                                    emit('open-in-tree', personId!);
                                    emit('close');
                                "
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-indigo-600 px-3 py-1.5 text-xs font-extrabold text-white shadow-md transition-all hover:bg-indigo-500 active:scale-95"
                                title="Center family tree on this individual"
                            >
                                <Target class="h-4 w-4 text-white" />
                                <span>Set as Tree Focus</span>
                            </button>
                            <button
                                @click="
                                    activeTab = 'contribute';
                                    contributionSuccess = false;
                                    contributionError = null;
                                "
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-emerald-500 sm:px-3"
                            >
                                <Send class="h-3.5 w-3.5" />
                                Add Note / Media
                            </button>
                            <button
                                @click="window.print()"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-slate-700 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-slate-600 sm:px-3"
                                title="Print this individual profile"
                            >
                                <Printer class="h-3.5 w-3.5" />
                                Print
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tabs navigation -->
                <div
                    class="mt-4 flex scrollbar-none items-center gap-1 overflow-x-auto border-b border-white/10 text-xs font-medium whitespace-nowrap sm:mt-6"
                >
                    <button
                        @click="activeTab = 'overview'"
                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-t-lg border-b-2 px-3 py-2.5 font-semibold transition-colors sm:px-4"
                        :class="
                            activeTab === 'overview'
                                ? 'border-indigo-400 bg-white/5 text-indigo-300'
                                : 'border-transparent text-slate-400 hover:text-slate-200'
                        "
                    >
                        <User class="h-3.5 w-3.5" />
                        Overview
                    </button>
                    <button
                        @click="activeTab = 'family'"
                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-t-lg border-b-2 px-3 py-2.5 font-semibold transition-colors sm:px-4"
                        :class="
                            activeTab === 'family'
                                ? 'border-indigo-400 bg-white/5 text-indigo-300'
                                : 'border-transparent text-slate-400 hover:text-slate-200'
                        "
                    >
                        <Heart class="h-3.5 w-3.5" />
                        Family
                    </button>
                    <button
                        @click="activeTab = 'media'"
                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-t-lg border-b-2 px-3 py-2.5 font-semibold transition-colors sm:px-4"
                        :class="
                            activeTab === 'media'
                                ? 'border-indigo-400 bg-white/5 text-indigo-300'
                                : 'border-transparent text-slate-400 hover:text-slate-200'
                        "
                    >
                        <ImageIcon class="h-3.5 w-3.5" />
                        Media ({{
                            personData?.person?.media_items?.length || 0
                        }})
                    </button>
                    <button
                        @click="
                            activeTab = 'contribute';
                            contributionSuccess = false;
                            contributionError = null;
                        "
                        class="flex shrink-0 cursor-pointer items-center gap-1.5 rounded-t-lg border-b-2 px-3 py-2.5 font-semibold transition-colors sm:px-4"
                        :class="
                            activeTab === 'contribute'
                                ? 'border-emerald-400 bg-white/5 text-emerald-300'
                                : 'border-transparent text-slate-400 hover:text-slate-200'
                        "
                    >
                        <Send class="h-3.5 w-3.5" />
                        Contribute
                    </button>
                </div>
            </div>

            <!-- Content Area -->
            <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                <div
                    v-if="loading"
                    class="flex flex-col items-center justify-center py-16 text-slate-400"
                >
                    <div
                        class="h-8 w-8 animate-spin rounded-full border-3 border-indigo-600 border-t-transparent"
                    ></div>
                    <p class="mt-3 text-sm">Loading profile details...</p>
                </div>

                <div v-else-if="personData">
                    <!-- Overview Tab -->
                    <div v-if="activeTab === 'overview'" class="space-y-6">
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-800/50"
                        >
                            <div class="mb-4 flex items-center justify-between">
                                <h3
                                    class="flex items-center gap-1.5 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                                >
                                    <Calendar class="h-4 w-4 text-indigo-500" />
                                    Life Events & Timeline ({{
                                        personData.person.events?.length || 0
                                    }})
                                </h3>
                            </div>

                            <div
                                v-if="
                                    personData.person.events &&
                                    personData.person.events.length > 0
                                "
                                class="relative ml-3.5 space-y-6 border-l-2 border-indigo-200 pt-1 dark:border-indigo-900/50"
                            >
                                <div
                                    v-for="ev in personData.person.events"
                                    :key="ev.id"
                                    class="group relative pl-6"
                                >
                                    <!-- Badge Indicator -->
                                    <span
                                        class="absolute top-1 -left-[9px] flex h-4 w-4 items-center justify-center rounded-full border-2 border-white text-white dark:border-slate-900"
                                        :class="
                                            getEventBadgeStyle(ev.tag, ev.title)
                                                .bg
                                        "
                                    ></span>

                                    <div
                                        class="flex flex-wrap items-baseline justify-between gap-x-2"
                                    >
                                        <div
                                            class="flex items-center gap-2 text-sm font-bold text-slate-900 dark:text-white"
                                        >
                                            <span>{{ ev.title }}</span>
                                            <span
                                                v-if="ev.value"
                                                class="rounded-md bg-slate-200/60 px-2 py-0.5 text-xs font-normal text-slate-600 dark:bg-slate-700/60 dark:text-slate-300"
                                            >
                                                {{ ev.value }}
                                            </span>
                                        </div>

                                        <div
                                            v-if="ev.date"
                                            class="text-xs font-semibold text-indigo-600 dark:text-indigo-400"
                                        >
                                            {{ ev.date }}
                                        </div>
                                        <div
                                            v-else-if="ev.year"
                                            class="text-xs font-semibold text-indigo-600 dark:text-indigo-400"
                                        >
                                            {{ ev.year }}
                                        </div>
                                    </div>

                                    <!-- Spouse Link if Marriage Event -->
                                    <div
                                        v-if="ev.spouse"
                                        class="mt-1.5 flex items-center gap-2"
                                    >
                                        <span
                                            class="text-xs text-slate-500 dark:text-slate-400"
                                        >
                                            {{
                                                ev.tag === '_PRS' ||
                                                (ev.title &&
                                                    ev.title
                                                        .toLowerCase()
                                                        .includes('partner'))
                                                    ? 'Partner:'
                                                    : 'Spouse:'
                                            }}
                                        </span>
                                        <button
                                            @click="
                                                emit(
                                                    'select-person',
                                                    ev.spouse.id,
                                                )
                                            "
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-pink-200 bg-pink-500/10 px-2.5 py-1 text-xs font-semibold text-pink-600 hover:underline dark:border-pink-900/50 dark:text-pink-400"
                                        >
                                            <Heart
                                                class="h-3 w-3 text-pink-500"
                                            />
                                            {{ ev.spouse.name }}
                                        </button>
                                    </div>

                                    <!-- Place -->
                                    <div
                                        v-if="ev.place"
                                        class="mt-1 flex items-center gap-1 text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        <MapPin
                                            class="h-3.5 w-3.5 shrink-0 text-slate-400"
                                        />
                                        <span>{{ ev.place }}</span>
                                    </div>

                                    <!-- Age / Cause -->
                                    <div
                                        v-if="ev.age || ev.cause"
                                        class="mt-0.5 flex flex-wrap gap-2 text-[11px] text-slate-500 dark:text-slate-400"
                                    >
                                        <span v-if="ev.age"
                                            >Age: {{ ev.age }}</span
                                        >
                                        <span v-if="ev.cause"
                                            >Cause: {{ ev.cause }}</span
                                        >
                                    </div>

                                    <!-- Note -->
                                    <div
                                        v-if="ev.note"
                                        class="mt-1.5 rounded-lg border border-slate-200 bg-white p-2.5 text-xs leading-relaxed whitespace-pre-line text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300"
                                    >
                                        {{ ev.note }}
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="py-4 text-xs text-slate-400 italic"
                            >
                                No life events recorded.
                            </div>
                        </div>

                        <!-- Notes Section -->
                        <div
                            v-if="
                                personData.person.notes &&
                                personData.person.notes.length > 0
                            "
                            class="rounded-xl border border-amber-500/20 bg-amber-500/5 p-5"
                        >
                            <h3
                                class="mb-2 flex items-center gap-1.5 text-xs font-bold tracking-wider text-amber-600 uppercase dark:text-amber-400"
                            >
                                <FileText class="h-4 w-4" />
                                Historic Notes & Records
                            </h3>
                            <div class="space-y-3">
                                <p
                                    v-for="(note, idx) in personData.person
                                        .notes"
                                    :key="idx"
                                    class="text-xs leading-relaxed whitespace-pre-line text-slate-700 dark:text-slate-300"
                                >
                                    {{ note }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Family Tab -->
                    <div v-if="activeTab === 'family'" class="space-y-6">
                        <!-- Parents -->
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50"
                        >
                            <h4
                                class="mb-3 flex items-center gap-1.5 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                <Users class="h-3.5 w-3.5 text-indigo-500" />
                                Parents
                            </h4>
                            <div
                                v-if="personData.relations.parents.length > 0"
                                class="grid grid-cols-1 gap-2.5 sm:grid-cols-2"
                            >
                                <div
                                    v-for="p in personData.relations.parents"
                                    :key="p.id"
                                    @click="emit('select-person', p.id)"
                                    class="group flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white p-2.5 shadow-2xs transition-all hover:border-indigo-500 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-indigo-500"
                                >
                                    <img
                                        v-if="p.primary_media"
                                        :src="
                                            p.portrait_url ||
                                            p.primary_media.portrait_url ||
                                            p.primary_media.url
                                        "
                                        class="h-10 w-10 shrink-0 rounded-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500 dark:bg-slate-800"
                                    >
                                        <User class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="truncate text-xs font-semibold text-slate-900 group-hover:text-indigo-600 dark:text-white dark:group-hover:text-indigo-400"
                                        >
                                            {{ p.name }}
                                        </div>
                                        <div
                                            class="text-[11px] text-slate-500 dark:text-slate-400"
                                        >
                                            {{ p.birth_year || '?' }} –
                                            {{ p.death_year || '?' }}
                                        </div>
                                    </div>
                                    <ChevronRight
                                        class="h-4 w-4 text-slate-400 transition-transform group-hover:translate-x-0.5"
                                    />
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic">
                                No parents recorded
                            </div>
                        </div>

                        <!-- Spouses -->
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50"
                        >
                            <h4
                                class="mb-3 flex items-center gap-1.5 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                <Heart class="h-3.5 w-3.5 text-pink-500" />
                                Spouses / Partners
                            </h4>
                            <div
                                v-if="personData.relations.spouses.length > 0"
                                class="grid grid-cols-1 gap-2.5 sm:grid-cols-2"
                            >
                                <div
                                    v-for="s in personData.relations.spouses"
                                    :key="s.id"
                                    @click="emit('select-person', s.id)"
                                    class="group flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white p-2.5 shadow-2xs transition-all hover:border-pink-500 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-pink-500"
                                >
                                    <img
                                        v-if="s.primary_media"
                                        :src="
                                            s.portrait_url ||
                                            s.primary_media.portrait_url ||
                                            s.primary_media.url
                                        "
                                        class="h-10 w-10 shrink-0 rounded-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500 dark:bg-slate-800"
                                    >
                                        <User class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="truncate text-xs font-semibold text-slate-900 group-hover:text-pink-600 dark:text-white dark:group-hover:text-pink-400"
                                        >
                                            {{ s.name }}
                                        </div>
                                        <div
                                            class="text-[11px] text-slate-500 dark:text-slate-400"
                                        >
                                            {{ s.birth_year || '?' }} –
                                            {{ s.death_year || '?' }}
                                        </div>
                                    </div>
                                    <ChevronRight
                                        class="h-4 w-4 text-slate-400 transition-transform group-hover:translate-x-0.5"
                                    />
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic">
                                No spouse recorded
                            </div>
                        </div>

                        <!-- Children -->
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50"
                        >
                            <h4
                                class="mb-3 flex items-center gap-1.5 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                <Users class="h-3.5 w-3.5 text-emerald-500" />
                                Children ({{
                                    personData.relations.children.length
                                }})
                            </h4>
                            <div
                                v-if="personData.relations.children.length > 0"
                                class="grid grid-cols-1 gap-2.5 sm:grid-cols-2"
                            >
                                <div
                                    v-for="c in personData.relations.children"
                                    :key="c.id"
                                    @click="emit('select-person', c.id)"
                                    class="group flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white p-2.5 shadow-2xs transition-all hover:border-emerald-500 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-emerald-500"
                                >
                                    <img
                                        v-if="c.primary_media"
                                        :src="
                                            c.portrait_url ||
                                            c.primary_media.portrait_url ||
                                            c.primary_media.url
                                        "
                                        class="h-10 w-10 shrink-0 rounded-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500 dark:bg-slate-800"
                                    >
                                        <User class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="truncate text-xs font-semibold text-slate-900 group-hover:text-emerald-600 dark:text-white dark:group-hover:text-emerald-400"
                                        >
                                            {{ c.name }}
                                        </div>
                                        <div
                                            class="text-[11px] text-slate-500 dark:text-slate-400"
                                        >
                                            {{ c.birth_year || '?' }} –
                                            {{ c.death_year || '?' }}
                                        </div>
                                    </div>
                                    <ChevronRight
                                        class="h-4 w-4 text-slate-400 transition-transform group-hover:translate-x-0.5"
                                    />
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-400 italic">
                                No children recorded
                            </div>
                        </div>

                        <!-- Siblings -->
                        <div
                            v-if="personData.relations.siblings.length > 0"
                            class="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-800/50"
                        >
                            <h4
                                class="mb-3 flex items-center gap-1.5 text-xs font-bold tracking-wider text-slate-500 uppercase dark:text-slate-400"
                            >
                                <Users class="h-3.5 w-3.5 text-blue-500" />
                                Siblings ({{
                                    personData.relations.siblings.length
                                }})
                            </h4>
                            <div
                                class="grid grid-cols-1 gap-2.5 sm:grid-cols-2"
                            >
                                <div
                                    v-for="sb in personData.relations.siblings"
                                    :key="sb.id"
                                    @click="emit('select-person', sb.id)"
                                    class="group flex cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white p-2.5 shadow-2xs transition-all hover:border-blue-500 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-blue-500"
                                >
                                    <img
                                        v-if="sb.primary_media"
                                        :src="
                                            sb.portrait_url ||
                                            sb.primary_media.portrait_url ||
                                            sb.primary_media.url
                                        "
                                        class="h-10 w-10 shrink-0 rounded-full object-cover"
                                    />
                                    <div
                                        v-else
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-slate-200 text-slate-500 dark:bg-slate-800"
                                    >
                                        <User class="h-5 w-5" />
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="truncate text-xs font-semibold text-slate-900 group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400"
                                        >
                                            {{ sb.name }}
                                        </div>
                                        <div
                                            class="text-[11px] text-slate-500 dark:text-slate-400"
                                        >
                                            {{ sb.birth_year || '?' }} –
                                            {{ sb.death_year || '?' }}
                                        </div>
                                    </div>
                                    <ChevronRight
                                        class="h-4 w-4 text-slate-400 transition-transform group-hover:translate-x-0.5"
                                    />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Media Tab -->
                    <div v-if="activeTab === 'media'" class="space-y-4">
                        <div
                            v-if="
                                personData.person.media_items &&
                                personData.person.media_items.length > 0
                            "
                            class="grid grid-cols-2 gap-3 sm:grid-cols-3"
                        >
                            <div
                                v-for="m in personData.person.media_items"
                                :key="m.id"
                                @click="activeMediaPreview = m"
                                class="group relative aspect-square cursor-pointer overflow-hidden rounded-xl border border-slate-200 bg-slate-900 dark:border-slate-800"
                            >
                                <img
                                    v-if="
                                        m.file.match(
                                            /\.(jpg|jpeg|png|gif|webp)$/i,
                                        )
                                    "
                                    :src="m.url"
                                    :alt="m.title"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                />
                                <div
                                    v-else-if="m.file.match(/\.pdf$/i)"
                                    class="flex h-full w-full flex-col items-center justify-center bg-red-950/40 p-4 text-red-400"
                                >
                                    <FileCode class="mb-2 h-10 w-10" />
                                    <span
                                        class="max-w-full truncate px-2 text-xs font-semibold"
                                        >{{ m.title }}</span
                                    >
                                </div>
                                <div
                                    v-else-if="
                                        m.file.match(/\.(m4a|mp3|wav|ogg)$/i)
                                    "
                                    class="flex h-full w-full flex-col items-center justify-center bg-amber-950/40 p-4 text-amber-400"
                                >
                                    <Volume2 class="mb-2 h-10 w-10" />
                                    <span
                                        class="max-w-full truncate px-2 text-xs font-semibold"
                                        >{{ m.title }}</span
                                    >
                                </div>
                                <div
                                    v-else
                                    class="flex h-full w-full flex-col items-center justify-center bg-slate-800 p-4 text-slate-400"
                                >
                                    <FileText class="mb-2 h-10 w-10" />
                                    <span
                                        class="max-w-full truncate px-2 text-xs font-semibold"
                                        >{{ m.title }}</span
                                    >
                                </div>

                                <div
                                    v-if="m.is_portrait"
                                    class="absolute top-2 left-2 z-10 flex items-center gap-1 rounded-md bg-indigo-600/90 px-2 py-0.5 text-[10px] font-bold tracking-wider text-white uppercase shadow-md"
                                >
                                    <Sparkles class="h-2.5 w-2.5" />
                                    Portrait
                                </div>
                                <div
                                    v-else-if="m.crop"
                                    class="absolute top-2 left-2 z-10 flex items-center gap-1 rounded-md bg-emerald-600/90 px-2 py-0.5 text-[10px] font-bold tracking-wider text-white uppercase shadow-md"
                                >
                                    Tagged Face
                                </div>

                                <div
                                    class="absolute inset-0 flex items-end bg-gradient-to-t from-black/80 via-transparent to-transparent p-2.5 opacity-0 transition-opacity group-hover:opacity-100"
                                >
                                    <span
                                        class="truncate text-[11px] font-medium text-white"
                                        >{{ m.title }}</span
                                    >
                                </div>
                            </div>
                        </div>

                        <div
                            v-else
                            class="rounded-xl border border-dashed border-slate-300 bg-slate-50 py-12 text-center text-slate-400 dark:border-slate-800 dark:bg-slate-800/30"
                        >
                            <ImageIcon
                                class="mx-auto mb-2 h-10 w-10 text-slate-400 opacity-50"
                            />
                            <p class="text-sm">
                                No media files attached to this person
                            </p>
                        </div>
                    </div>

                    <!-- Contribute Tab -->
                    <div v-if="activeTab === 'contribute'" class="space-y-6">
                        <div
                            class="rounded-xl border border-slate-200 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-800/50"
                        >
                            <div class="mb-4 flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-emerald-500/20 bg-emerald-500/10 text-emerald-500"
                                >
                                    <Send class="h-5 w-5" />
                                </div>
                                <div>
                                    <h3
                                        class="text-sm font-bold text-slate-900 dark:text-white"
                                    >
                                        Send Note or Upload Media
                                    </h3>
                                    <p
                                        class="text-xs text-slate-500 dark:text-slate-400"
                                    >
                                        Submit corrections, stories, or photos
                                        for {{ personData?.person?.name }}. This
                                        is stored and emailed to the
                                        administrator.
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="contributionSuccess"
                                class="my-4 flex animate-in items-start gap-2.5 rounded-xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-xs text-emerald-600 duration-200 fade-in dark:text-emerald-400"
                            >
                                <CheckCircle2 class="mt-0.5 h-5 w-5 shrink-0" />
                                <div>
                                    <strong class="block text-sm font-bold"
                                        >Contribution Sent Successfully!</strong
                                    >
                                    Your note/media file has been stored safely
                                    and emailed directly to the administrator
                                    for review.
                                </div>
                            </div>

                            <div
                                v-if="contributionError"
                                class="my-4 flex items-center gap-2 rounded-xl border border-red-500/20 bg-red-500/10 p-3.5 text-xs text-red-600 dark:text-red-400"
                            >
                                <AlertCircle class="h-4 w-4 shrink-0" />
                                {{ contributionError }}
                            </div>

                            <div class="mt-2 space-y-4">
                                <div>
                                    <label
                                        class="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                                    >
                                        Note / Details (Optional if uploading a
                                        file)
                                    </label>
                                    <textarea
                                        v-model="contributionNote"
                                        rows="4"
                                        placeholder="Add facts, birth/death place corrections, notes, or memory descriptions..."
                                        class="w-full rounded-xl border border-slate-300 bg-white p-3 text-xs text-slate-900 placeholder:text-slate-400 focus:ring-2 focus:ring-emerald-500 focus:outline-none dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100"
                                    ></textarea>
                                </div>

                                <div>
                                    <label
                                        class="mb-1.5 block text-xs font-semibold text-slate-700 dark:text-slate-300"
                                    >
                                        Upload Media File (Optional)
                                    </label>

                                    <div
                                        v-if="!selectedFile"
                                        class="group relative cursor-pointer rounded-xl border-2 border-dashed border-slate-300 bg-white/50 p-5 text-center transition-colors hover:border-emerald-500 dark:border-slate-700 dark:bg-slate-900/50"
                                    >
                                        <input
                                            ref="fileInputRef"
                                            type="file"
                                            @change="handleFileSelect"
                                            accept="image/*,.pdf,audio/*"
                                            class="absolute inset-0 h-full w-full cursor-pointer opacity-0"
                                        />
                                        <FileUp
                                            class="mx-auto mb-1.5 h-7 w-7 text-slate-400 transition-colors group-hover:text-emerald-500"
                                        />
                                        <p
                                            class="text-xs font-medium text-slate-700 dark:text-slate-300"
                                        >
                                            Click or drag file here to upload
                                        </p>
                                        <p
                                            class="mt-1 text-[11px] text-slate-400"
                                        >
                                            Photos (JPG, PNG, WebP), PDFs, or
                                            Audio (MP3, M4A). Max 20MB.
                                        </p>
                                    </div>

                                    <div
                                        v-else
                                        class="flex items-center justify-between rounded-xl border border-slate-200 bg-slate-100 p-3 dark:border-slate-800 dark:bg-slate-900"
                                    >
                                        <div
                                            class="flex items-center gap-2.5 truncate"
                                        >
                                            <Upload
                                                class="h-4 w-4 shrink-0 text-emerald-500"
                                            />
                                            <span
                                                class="truncate text-xs font-medium text-slate-800 dark:text-slate-200"
                                            >
                                                {{ selectedFile.name }}
                                            </span>
                                            <span
                                                class="shrink-0 text-[10px] text-slate-400"
                                            >
                                                ({{
                                                    (
                                                        selectedFile.size /
                                                        1024 /
                                                        1024
                                                    ).toFixed(2)
                                                }}
                                                MB)
                                            </span>
                                        </div>
                                        <button
                                            @click="removeSelectedFile"
                                            class="rounded-lg p-1 text-slate-400 transition-colors hover:bg-slate-200 hover:text-red-500 dark:hover:bg-slate-800"
                                            title="Remove file"
                                        >
                                            <Trash2 class="h-4 w-4" />
                                        </button>
                                    </div>
                                </div>

                                <div class="flex justify-end pt-2">
                                    <button
                                        @click="submitContribution"
                                        :disabled="
                                            submittingContribution ||
                                            (!contributionNote.trim() &&
                                                !selectedFile)
                                        "
                                        class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-2.5 text-xs font-semibold text-white shadow-xs transition-colors hover:bg-emerald-500 disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <div
                                            v-if="submittingContribution"
                                            class="h-3.5 w-3.5 animate-spin rounded-full border-2 border-white border-t-transparent"
                                        ></div>
                                        <Send v-else class="h-3.5 w-3.5" />
                                        {{
                                            submittingContribution
                                                ? 'Sending Contribution...'
                                                : 'Submit to Administrator'
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Media Preview Lightbox Modal -->
            <div
                v-if="activeMediaPreview"
                class="fixed inset-0 z-60 flex animate-in flex-col items-center justify-center bg-black/90 p-6 fade-in"
            >
                <button
                    @click="activeMediaPreview = null"
                    class="absolute top-6 right-6 rounded-full bg-white/10 p-3 text-white transition-colors hover:bg-white/20"
                >
                    <X class="h-6 w-6" />
                </button>

                <div
                    class="flex max-h-[80vh] w-full max-w-4xl flex-col items-center justify-center"
                >
                    <img
                        v-if="
                            activeMediaPreview.file.match(
                                /\.(jpg|jpeg|png|gif|webp)$/i,
                            )
                        "
                        :src="activeMediaPreview.url"
                        :alt="activeMediaPreview.title"
                        class="max-h-[70vh] max-w-full rounded-lg object-contain shadow-2xl"
                    />

                    <iframe
                        v-else-if="activeMediaPreview.file.match(/\.pdf$/i)"
                        :src="activeMediaPreview.url"
                        class="h-[70vh] w-full rounded-lg border border-slate-800 shadow-2xl"
                    ></iframe>

                    <div
                        v-else-if="
                            activeMediaPreview.file.match(
                                /\.(m4a|mp3|wav|ogg)$/i,
                            )
                        "
                        class="flex w-full max-w-md flex-col items-center gap-4 rounded-2xl border border-slate-800 bg-slate-900 p-8"
                    >
                        <Music class="h-16 w-16 text-amber-400" />
                        <span class="text-center font-medium text-white">{{
                            activeMediaPreview.title
                        }}</span>
                        <audio controls class="w-full">
                            <source
                                :src="activeMediaPreview.url"
                                type="audio/mp4"
                            />
                            Your browser does not support audio playback.
                        </audio>
                    </div>

                    <div
                        class="mt-4 flex w-full items-center justify-between text-sm text-slate-300"
                    >
                        <span class="max-w-lg truncate font-medium">{{
                            activeMediaPreview.title
                        }}</span>
                        <a
                            :href="activeMediaPreview.url"
                            target="_blank"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-2 text-xs font-semibold text-white transition-colors hover:bg-indigo-500"
                        >
                            <ExternalLink class="h-4 w-4" />
                            Open Original
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
