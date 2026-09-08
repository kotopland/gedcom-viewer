<script setup lang="ts">
import { computed } from 'vue';
import { User, RefreshCcw } from '@lucide/vue';

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

// Calculate portrait size based on ancestor generation (10% smaller per generation)
const portraitScale = computed(() => {
    const lvl = Math.max(0, props.ancestorLevel || 0);
    // 10% reduction per generation (additional 5%), clamped to minimum 0.50
    return Math.max(0.50, 1 - lvl * 0.10);
});

const portraitDiameter = computed(() => {
    return Math.round(260 * portraitScale.value);
});

const placeholderIconSize = computed(() => {
    return Math.round(96 * portraitScale.value);
});

// Compute stylized display name (e.g. "BODIL HOLVIK (TOPLAND)")
const displayName = computed(() => {
    if (!props.person) return '';
    const p = props.person;

    // Check if name already has parentheses
    if (p.name && p.name.includes('(') && p.name.includes(')')) {
        return p.name.toUpperCase();
    }

    // Check all_names for married name / maiden name
    if (p.all_names && Array.isArray(p.all_names)) {
        const marriedName = p.all_names.find((n: any) => n.type === 'married' || (n.surname && n.surname !== p.surname));
        if (marriedName && marriedName.surname && p.surname) {
            return `${p.given_name || ''} ${p.surname} (${marriedName.surname})`.trim().toUpperCase();
        }
    }

    // Check if spouse surname differs and person is female with marriage
    if (props.spouse && props.spouse.surname && p.surname && p.sex === 'F' && props.spouse.surname !== p.surname) {
        return `${p.name} (${props.spouse.surname})`.toUpperCase();
    }

    return (p.name || '').toUpperCase();
});

// Format date strings neatly
const formatVal = (val?: string | null) => {
    if (!val) return '';
    return val.trim();
};

// Clean and format place strings (removes empty hierarchy commas like ",,,", trims, and joins neatly)
const formatPlace = (place?: string | null) => {
    if (!place || typeof place !== 'string') return '';
    return place
        .split(',')
        .map((p) => p.trim())
        .filter((p) => p.length > 0)
        .join(', ');
};
</script>

<template>
    <div
        @click="emit('select-person', person.id)"
        data-clickable="true"
        class="heritage-node-card group relative flex flex-col items-center cursor-pointer transition-transform duration-200 hover:scale-[1.02] select-none h-[460px] shrink-0"
        :style="{ width: `${portraitDiameter}px` }"
    >
        <!-- Floating Focus Quick-Button on Hover -->
        <button
            @click.stop="emit('change-root', person.id)"
            class="absolute top-1 right-2 z-20 p-1.5 rounded-full bg-slate-900/90 hover:bg-indigo-600 text-white shadow-lg opacity-0 group-hover:opacity-100 transition-all cursor-pointer border border-slate-700 hover:scale-110"
            title="Focus tree on this person"
        >
            <RefreshCcw class="w-3.5 h-3.5" />
        </button>

        <!-- 1. Circular Portrait Avatar (matching name box width, 10% smaller per ancestor generation) -->
        <div class="relative z-10 w-full mb-[-36px] flex items-end justify-center h-[260px]">
            <div
                @click.stop="emit('select-person', person.id)"
                data-clickable="true"
                class="shrink-0 rounded-full overflow-hidden border-[3px] border-slate-400/80 dark:border-slate-500 shadow-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center ring-2 ring-slate-900/20 dark:ring-black/40 cursor-pointer transition-all duration-200 hover:scale-[1.03] hover:ring-4 hover:ring-indigo-500/50"
                :style="{
                    width: `${portraitDiameter}px`,
                    height: `${portraitDiameter}px`
                }"
                title="View person details"
            >
                <img
                    v-if="person.primary_media"
                    :src="person.portrait_url || person.primary_media.portrait_url || person.primary_media.url"
                    :alt="person.name"
                    class="w-full h-full object-cover pointer-events-none"
                    loading="lazy"
                />
                <div v-else class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-b from-slate-200 to-slate-300 dark:from-slate-800 dark:to-slate-900 text-slate-500 dark:text-slate-400 pointer-events-none">
                    <User
                        :style="{ width: `${placeholderIconSize}px`, height: `${placeholderIconSize}px` }"
                        class="stroke-[1.5]"
                    />
                    <span
                        class="font-serif uppercase tracking-widest opacity-75"
                        :class="portraitDiameter < 230 ? 'text-[10px] mt-1' : 'text-xs mt-2.5'"
                    >
                        {{ person.sex === 'M' ? 'Male' : (person.sex === 'F' ? 'Female' : 'Individual') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. Dark Stylized Name Banner (same width as profile picture) -->
        <div class="relative z-20 w-full">
            <div
                class="heritage-banner relative h-[52px] w-full flex items-center justify-center px-2.5 rounded-md text-center shadow-xl transition-colors"
                :class="[
                    isPrimary
                        ? 'bg-gradient-to-b from-slate-900 via-slate-850 to-slate-950 border border-slate-700 text-slate-100'
                        : 'bg-gradient-to-b from-slate-850 via-slate-900 to-slate-950 border border-slate-700/90 text-slate-200'
                ]"
            >
                <!-- 4 Corner Rivet / Metallic Accents -->
                <span class="absolute top-1 left-1 w-1.5 h-1.5 rounded-full bg-slate-300/70 dark:bg-amber-300/50"></span>
                <span class="absolute top-1 right-1 w-1.5 h-1.5 rounded-full bg-slate-300/70 dark:bg-amber-300/50"></span>
                <span class="absolute bottom-1 left-1 w-1.5 h-1.5 rounded-full bg-slate-300/70 dark:bg-amber-300/50"></span>
                <span class="absolute bottom-1 right-1 w-1.5 h-1.5 rounded-full bg-slate-300/70 dark:bg-amber-300/50"></span>

                <!-- Name in Stylized Serif Typography (larger, readable font) -->
                <div
                    class="heritage-font font-bold tracking-[0.07em] uppercase leading-[1.22] px-1.5 line-clamp-2 w-full text-center"
                    :class="portraitDiameter < 220 ? 'text-[12.5px] sm:text-[13px]' : (portraitDiameter < 240 ? 'text-[13px] sm:text-[14px]' : 'text-[14px] sm:text-[15px]')"
                    :title="displayName"
                >
                    {{ displayName }}
                </div>
            </div>
        </div>

        <!-- 3. Aligned Vital Statistics Table (same width as profile picture) -->
        <div class="w-full h-[184px] pt-3 px-1 pb-1 space-y-1.5 font-sans leading-tight text-slate-800 dark:text-slate-200 overflow-hidden flex flex-col justify-start"
            :class="portraitDiameter < 220 ? 'text-[11.5px] sm:text-[12px]' : (portraitDiameter < 240 ? 'text-[12px] sm:text-[12.5px]' : 'text-[13px] sm:text-[13.5px]')"
        >
            <!-- Birth -->
            <div v-if="person.birth_date || person.birth_year || formatPlace(person.birth_place)" class="flex items-start justify-between gap-1.5">
                <span class="font-medium text-slate-600 dark:text-slate-300 shrink-0 text-left text-[13px] sm:text-[13.5px]">Birth</span>
                <div class="text-right flex-1 min-w-0">
                    <div v-if="person.birth_date || person.birth_year" class="font-bold text-slate-900 dark:text-white text-[13.5px] sm:text-[14px]">
                        {{ formatVal(person.birth_date) || person.birth_year }}
                    </div>
                    <div v-if="formatPlace(person.birth_place)" class="text-[12px] sm:text-[12.5px] text-slate-600 dark:text-slate-400 truncate" :title="formatPlace(person.birth_place)">
                        {{ formatPlace(person.birth_place) }}
                    </div>
                </div>
            </div>

            <!-- Marriage / Civil Partnership -->
            <div v-if="person.marriage_date || person.marriage_year || (person.marriage_type && person.marriage_type !== 'Marriage')" class="flex items-start justify-between gap-1.5">
                <span class="font-medium text-slate-600 dark:text-slate-300 shrink-0 text-left text-[13px] sm:text-[13.5px]">
                    {{ person.marriage_type || person.relationship_type || 'Marriage' }}
                </span>
                <div class="text-right flex-1 min-w-0">
                    <div v-if="person.marriage_date || person.marriage_year" class="font-bold text-slate-900 dark:text-white text-[13.5px] sm:text-[14px]">
                        {{ formatVal(person.marriage_date) || person.marriage_year }}
                    </div>
                </div>
            </div>

            <!-- Occupation -->
            <div v-if="person.occupation" class="flex items-start justify-between gap-1.5">
                <span class="font-medium text-slate-600 dark:text-slate-300 shrink-0 text-left text-[13px] sm:text-[13.5px]">Occupation</span>
                <div class="text-right flex-1 min-w-0">
                    <div class="font-bold text-slate-900 dark:text-white text-[13.5px] sm:text-[14px] truncate" :title="person.occupation">
                        {{ person.occupation }}
                    </div>
                </div>
            </div>

            <!-- Death -->
            <div v-if="person.death_date || person.death_year || formatPlace(person.death_place)" class="flex items-start justify-between gap-1.5">
                <span class="font-medium text-slate-600 dark:text-slate-300 shrink-0 text-left text-[13px] sm:text-[13.5px]">Death</span>
                <div class="text-right flex-1 min-w-0">
                    <div v-if="person.death_date || person.death_year" class="font-bold text-slate-900 dark:text-white text-[13.5px] sm:text-[14px]">
                        {{ formatVal(person.death_date) || person.death_year }}
                    </div>
                    <div v-if="formatPlace(person.death_place)" class="text-[12px] sm:text-[12.5px] text-slate-600 dark:text-slate-400 truncate" :title="formatPlace(person.death_place)">
                        {{ formatPlace(person.death_place) }}
                    </div>
                </div>
            </div>

            <!-- Burial -->
            <div v-if="person.burial_date" class="flex items-start justify-between gap-1.5">
                <span class="font-medium text-slate-600 dark:text-slate-300 shrink-0 text-left text-[13px] sm:text-[13.5px]">Burial</span>
                <div class="text-right flex-1 min-w-0">
                    <div class="font-bold text-slate-900 dark:text-white text-[13.5px] sm:text-[14px]">
                        {{ formatVal(person.burial_date) }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800;900&family=Marcellus&display=swap');

.heritage-font {
    font-family: 'Cinzel', 'Marcellus', 'Trajan Pro', 'Papyrus', 'Georgia', serif;
    letter-spacing: 0.07em;
}

.heritage-banner {
    background-color: #1e293b;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}
</style>
