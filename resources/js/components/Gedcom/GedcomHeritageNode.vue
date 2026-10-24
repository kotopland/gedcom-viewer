<script setup lang="ts">
import { User, RefreshCcw, Image, ImageOff } from '@lucide/vue';
import { computed, inject } from 'vue';

const portraitState = inject<any>('heritagePortraitState', null);

const props = defineProps<{
    person: any;
    isPrimary?: boolean;
    spouse?: any;
    ancestorLevel?: number;
    hasMarriageConnectorRight?: boolean;
    hasMarriageConnectorLeft?: boolean;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();

const isPortraitHidden = computed(() => {
    if (!props.person?.id || !portraitState) {
        return false;
    }

    return portraitState.isPortraitHiddenForPerson(props.person.id);
});

// Calculate portrait size based on ancestor generation (5% smaller per generation)
const portraitScale = computed(() => {
    const lvl = Math.max(0, props.ancestorLevel || 0);

    // 5% reduction per generation, clamped to minimum 0.50
    return Math.max(0.5, 1 - lvl * 0.05);
});

const portraitDiameter = computed(() => {
    return Math.round(260 * portraitScale.value);
});

const placeholderIconSize = computed(() => {
    return Math.round(96 * portraitScale.value);
});

// Helper to capitalize only the first letter of each name part (Title Case, e.g. "Bodil Holvik (Topland)")
const formatNameTitleCase = (str?: string | null): string => {
    if (!str) {
        return '';
    }

    return str
        .toLowerCase()
        .replace(/(?:^|[\s\-(['"])\p{L}/gu, (char) => char.toUpperCase());
};

// Compute stylized display name (e.g. "Bodil Holvik (Topland)")
const displayName = computed(() => {
    if (!props.person) {
        return '';
    }

    const p = props.person;

    // Check if name already has parentheses
    if (p.name && p.name.includes('(') && p.name.includes(')')) {
        return formatNameTitleCase(p.name);
    }

    // Check all_names for married name / maiden name
    if (p.all_names && Array.isArray(p.all_names)) {
        const marriedName = p.all_names.find(
            (n: any) =>
                n.type === 'married' || (n.surname && n.surname !== p.surname),
        );

        if (marriedName && marriedName.surname && p.surname) {
            return formatNameTitleCase(
                `${p.given_name || ''} ${p.surname} (${marriedName.surname})`.trim(),
            );
        }
    }

    // Check if spouse surname differs and person is female with marriage
    if (
        props.spouse &&
        props.spouse.surname &&
        p.surname &&
        p.sex === 'F' &&
        props.spouse.surname !== p.surname
    ) {
        return formatNameTitleCase(`${p.name} (${props.spouse.surname})`);
    }

    return formatNameTitleCase(p.name || '');
});

// Format date strings neatly
const formatVal = (val?: string | null) => {
    if (!val) {
        return '';
    }

    return val.trim();
};

// Clean and format place strings (removes empty hierarchy commas like ",,,", trims, and joins neatly)
const formatPlace = (place?: string | null) => {
    if (!place || typeof place !== 'string') {
        return '';
    }

    return place
        .split(',')
        .map((p) => p.trim())
        .filter((p) => p.length > 0)
        .join(', ');
};

// Responsive font sizes for person details (prominently larger fonts on Heritage Chart while keeping place names compact)
const labelTextClass = computed(() => {
    if (portraitDiameter.value < 220) {
        return 'text-[14px] sm:text-[14.5px]';
    }

    if (portraitDiameter.value < 240) {
        return 'text-[15px] sm:text-[15.5px]';
    }

    return 'text-[16px] sm:text-[16.5px]';
});

const valueTextClass = computed(() => {
    if (portraitDiameter.value < 220) {
        return 'text-[14.5px] sm:text-[15px]';
    }

    if (portraitDiameter.value < 240) {
        return 'text-[15.5px] sm:text-[16px]';
    }

    return 'text-[16.5px] sm:text-[17px]';
});

const placeTextClass = computed(() => {
    if (portraitDiameter.value < 220) {
        return 'text-[11px] sm:text-[11.5px]';
    }

    return 'text-[11.5px] sm:text-[12px]';
});
</script>

<template>
    <div
        @click="emit('select-person', person.id)"
        data-clickable="true"
        class="heritage-node-card group relative flex h-[460px] shrink-0 cursor-pointer flex-col items-center transition-transform duration-200 select-none hover:scale-[1.02]"
        :style="{ width: `${portraitDiameter}px` }"
    >
        <!-- Floating Focus Quick-Button on Hover -->
        <button
            v-if="!portraitState?.isExportingPdf?.()"
            @click.stop="emit('change-root', person.id)"
            class="absolute top-1 right-2 z-20 cursor-pointer rounded-full border border-slate-700 bg-slate-900/90 p-1.5 text-white opacity-0 shadow-lg transition-all group-hover:opacity-100 hover:scale-110 hover:bg-indigo-600"
            title="Focus tree on this person"
        >
            <RefreshCcw class="h-3.5 w-3.5" />
        </button>

        <!-- Floating Quick-Button to Exclude/Include Portrait on Hover (Discrete) -->
        <button
            v-if="
                person.primary_media &&
                portraitState &&
                !portraitState?.isExportingPdf?.()
            "
            @click.stop="portraitState.toggleExcludePortrait(person.id)"
            class="absolute top-1 left-2 z-20 cursor-pointer rounded-full border p-1.5 text-white shadow-lg transition-all hover:scale-110"
            :class="[
                portraitState.isPortraitExcluded(person.id)
                    ? 'border-amber-400 bg-amber-600/95 opacity-90 hover:bg-amber-500'
                    : 'border-slate-700 bg-slate-900/90 opacity-0 group-hover:opacity-100 hover:bg-slate-700',
            ]"
            :title="
                portraitState.isPortraitExcluded(person.id)
                    ? 'Enable portrait picture for PDF export'
                    : 'Disable portrait picture for PDF export'
            "
        >
            <ImageOff
                v-if="portraitState.isPortraitExcluded(person.id)"
                class="h-3.5 w-3.5 text-amber-200"
            />
            <Image v-else class="h-3.5 w-3.5 text-slate-200" />
        </button>

        <!-- 1. Circular Portrait Avatar (matching name box width, 10% smaller per ancestor generation) -->
        <div
            class="relative z-10 mb-[-36px] flex h-[260px] w-full items-end justify-center"
        >
            <div
                @click.stop="emit('select-person', person.id)"
                data-clickable="true"
                class="flex shrink-0 cursor-pointer items-center justify-center overflow-hidden rounded-full border-[3px] border-slate-400/80 bg-slate-100 shadow-xl ring-2 ring-slate-900/20 transition-all duration-200 hover:scale-[1.03] hover:ring-4 hover:ring-indigo-500/50 dark:border-slate-500 dark:bg-slate-800 dark:ring-black/40"
                :style="{
                    width: `${portraitDiameter}px`,
                    height: `${portraitDiameter}px`,
                }"
                title="View person details"
            >
                <img
                    v-if="person.primary_media && !isPortraitHidden"
                    :src="
                        person.portrait_url ||
                        person.primary_media.portrait_url ||
                        person.primary_media.url
                    "
                    :alt="person.name"
                    class="pointer-events-none h-full w-full object-cover"
                    loading="lazy"
                />
                <div
                    v-else
                    class="pointer-events-none flex h-full w-full flex-col items-center justify-center bg-gradient-to-b from-slate-200 to-slate-300 text-slate-500 dark:from-slate-800 dark:to-slate-900 dark:text-slate-400"
                >
                    <User
                        :style="{
                            width: `${placeholderIconSize}px`,
                            height: `${placeholderIconSize}px`,
                        }"
                        class="stroke-[1.5]"
                    />
                    <span
                        class="font-serif tracking-widest uppercase opacity-75"
                        :class="
                            portraitDiameter < 230
                                ? 'mt-1 text-[10px]'
                                : 'mt-2.5 text-xs'
                        "
                    >
                        {{
                            person.sex === 'M'
                                ? 'Male'
                                : person.sex === 'F'
                                  ? 'Female'
                                  : 'Individual'
                        }}
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. Stylized Name Banner (same width as profile picture) -->
        <div class="relative z-20 w-full">
            <div
                class="heritage-banner relative flex h-[52px] w-full items-center justify-center rounded-md px-2.5 text-center shadow-md transition-colors"
                :class="[
                    isPrimary
                        ? 'border-2 border-black bg-white text-black'
                        : 'border border-black bg-white text-black',
                ]"
            >
                <!-- 4 Corner Rivet / Metallic Accents -->
                <span
                    class="absolute top-1 left-1 h-1.5 w-1.5 rounded-full border border-slate-400/60 bg-slate-300"
                ></span>
                <span
                    class="absolute top-1 right-1 h-1.5 w-1.5 rounded-full border border-slate-400/60 bg-slate-300"
                ></span>
                <span
                    class="absolute bottom-1 left-1 h-1.5 w-1.5 rounded-full border border-slate-400/60 bg-slate-300"
                ></span>
                <span
                    class="absolute right-1 bottom-1 h-1.5 w-1.5 rounded-full border border-slate-400/60 bg-slate-300"
                ></span>

                <!-- Name in Stylized Serif Typography (increased by 20%) -->
                <div
                    class="heritage-font line-clamp-2 w-full px-1.5 text-center leading-[1.18] font-bold tracking-[0.02em]"
                    :class="
                        portraitDiameter < 220
                            ? 'text-[15px] sm:text-[15.5px]'
                            : portraitDiameter < 240
                              ? 'text-[15.5px] sm:text-[17px]'
                              : 'text-[17px] sm:text-[18px]'
                    "
                    :title="displayName"
                >
                    {{ displayName }}
                </div>
            </div>
        </div>

        <!-- 3. Aligned Vital Statistics Table (same width as profile picture) -->
        <div
            class="flex h-[184px] w-full flex-col justify-start space-y-1.5 overflow-hidden px-1 pt-3 pb-1 font-sans leading-tight text-slate-800 dark:text-slate-200"
        >
            <!-- Birth -->
            <div
                v-if="
                    person.birth_date ||
                    person.birth_year ||
                    formatPlace(person.birth_place)
                "
                class="flex items-start justify-between gap-1.5"
            >
                <span
                    class="shrink-0 text-left font-semibold text-slate-700 dark:text-slate-300"
                    :class="labelTextClass"
                    >Birth</span
                >
                <div class="min-w-0 flex-1 text-right">
                    <div
                        v-if="person.birth_date || person.birth_year"
                        class="font-bold text-slate-950 dark:text-white"
                        :class="valueTextClass"
                    >
                        {{ formatVal(person.birth_date) || person.birth_year }}
                    </div>
                    <div
                        v-if="formatPlace(person.birth_place)"
                        class="truncate text-slate-600 dark:text-slate-400"
                        :class="placeTextClass"
                        :title="formatPlace(person.birth_place)"
                    >
                        {{ formatPlace(person.birth_place) }}
                    </div>
                </div>
            </div>

            <!-- Marriage / Civil Partnership -->
            <div
                v-if="
                    person.marriage_date ||
                    person.marriage_year ||
                    (person.marriage_type &&
                        person.marriage_type !== 'Marriage')
                "
                class="flex items-start justify-between gap-1.5"
            >
                <span
                    class="shrink-0 text-left font-semibold text-slate-700 dark:text-slate-300"
                    :class="labelTextClass"
                >
                    {{
                        person.marriage_type ||
                        person.relationship_type ||
                        'Marriage'
                    }}
                </span>
                <div class="min-w-0 flex-1 text-right">
                    <div
                        v-if="person.marriage_date || person.marriage_year"
                        class="font-bold text-slate-950 dark:text-white"
                        :class="valueTextClass"
                    >
                        {{
                            formatVal(person.marriage_date) ||
                            person.marriage_year
                        }}
                    </div>
                </div>
            </div>

            <!-- Occupation -->
            <div
                v-if="person.occupation"
                class="flex items-start justify-between gap-1.5"
            >
                <span
                    class="shrink-0 text-left font-semibold text-slate-700 dark:text-slate-300"
                    :class="labelTextClass"
                    >Occupation</span
                >
                <div class="min-w-0 flex-1 text-right">
                    <div
                        class="truncate font-bold text-slate-950 dark:text-white"
                        :class="valueTextClass"
                        :title="person.occupation"
                    >
                        {{ person.occupation }}
                    </div>
                </div>
            </div>

            <!-- Death -->
            <div
                v-if="
                    person.death_date ||
                    person.death_year ||
                    formatPlace(person.death_place)
                "
                class="flex items-start justify-between gap-1.5"
            >
                <span
                    class="shrink-0 text-left font-semibold text-slate-700 dark:text-slate-300"
                    :class="labelTextClass"
                    >Death</span
                >
                <div class="min-w-0 flex-1 text-right">
                    <div
                        v-if="person.death_date || person.death_year"
                        class="font-bold text-slate-950 dark:text-white"
                        :class="valueTextClass"
                    >
                        {{ formatVal(person.death_date) || person.death_year }}
                    </div>
                    <div
                        v-if="formatPlace(person.death_place)"
                        class="truncate text-slate-600 dark:text-slate-400"
                        :class="placeTextClass"
                        :title="formatPlace(person.death_place)"
                    >
                        {{ formatPlace(person.death_place) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800;900&family=Marcellus&display=swap');

.heritage-font {
    font-family:
        'Marcellus', 'Cinzel', 'Trajan Pro', 'Papyrus', 'Georgia', serif;
    letter-spacing: 0.02em;
}

.heritage-banner {
    background-color: #ffffff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}
</style>
