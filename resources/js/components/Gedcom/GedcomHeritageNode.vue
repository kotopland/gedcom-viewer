<script setup lang="ts">
import { computed } from 'vue';
import { User, RefreshCcw } from '@lucide/vue';

const props = defineProps<{
    person: any;
    isPrimary?: boolean;
    spouse?: any;
    hasMarriageConnectorRight?: boolean;
    hasMarriageConnectorLeft?: boolean;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();

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
</script>

<template>
    <div
        @click="emit('select-person', person.id)"
        class="heritage-node-card group relative flex flex-col items-center cursor-pointer transition-transform duration-200 hover:scale-[1.02] select-none"
        style="width: 220px;"
    >
        <!-- Floating Focus Quick-Button on Hover -->
        <button
            @click.stop="emit('change-root', person.id)"
            class="absolute -top-1 -right-1 z-20 p-1.5 rounded-full bg-slate-900/90 hover:bg-indigo-600 text-white shadow-lg opacity-0 group-hover:opacity-100 transition-all cursor-pointer border border-slate-700 hover:scale-110"
            title="Focus tree on this person"
        >
            <RefreshCcw class="w-3.5 h-3.5" />
        </button>

        <!-- 1. Circular Portrait Avatar -->
        <div class="relative z-10 mb-[-12px]">
            <div class="w-28 h-28 sm:w-32 sm:h-32 rounded-full overflow-hidden border-[3px] border-slate-400/80 dark:border-slate-500 shadow-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center ring-2 ring-slate-900/20 dark:ring-black/40">
                <img
                    v-if="person.primary_media"
                    :src="person.primary_media.url"
                    :alt="person.name"
                    class="w-full h-full object-cover"
                    loading="lazy"
                />
                <div v-else class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-b from-slate-200 to-slate-300 dark:from-slate-800 dark:to-slate-900 text-slate-500 dark:text-slate-400">
                    <User class="w-12 h-12 stroke-[1.5]" />
                    <span class="text-[10px] font-serif uppercase tracking-widest mt-1 opacity-70">
                        {{ person.sex === 'M' ? 'Male' : (person.sex === 'F' ? 'Female' : 'Individual') }}
                    </span>
                </div>
            </div>
        </div>

        <!-- 2. Dark Stylized Name Banner -->
        <div class="relative z-10 w-full px-1">
            <div
                class="heritage-banner relative py-1.5 px-2.5 rounded-md text-center shadow-lg transition-colors"
                :class="[
                    isPrimary
                        ? 'bg-gradient-to-b from-slate-900 via-slate-850 to-slate-950 border border-slate-700 text-slate-100'
                        : 'bg-gradient-to-b from-slate-850 via-slate-900 to-slate-950 border border-slate-700/90 text-slate-200'
                ]"
            >
                <!-- 4 Corner Rivet / Metallic Accents -->
                <span class="absolute top-1 left-1 w-1 h-1 rounded-full bg-slate-400/60 dark:bg-amber-300/40"></span>
                <span class="absolute top-1 right-1 w-1 h-1 rounded-full bg-slate-400/60 dark:bg-amber-300/40"></span>
                <span class="absolute bottom-1 left-1 w-1 h-1 rounded-full bg-slate-400/60 dark:bg-amber-300/40"></span>
                <span class="absolute bottom-1 right-1 w-1 h-1 rounded-full bg-slate-400/60 dark:bg-amber-300/40"></span>

                <!-- Name in Stylized Serif Typography -->
                <div class="heritage-font text-[11.5px] sm:text-[12.5px] font-bold tracking-[0.14em] uppercase leading-snug px-1 line-clamp-2">
                    {{ displayName }}
                </div>
            </div>
        </div>

        <!-- 3. Aligned Vital Statistics Table -->
        <div class="w-full pt-2.5 px-1 pb-1 space-y-1.5 text-[11px] font-sans leading-tight text-slate-800 dark:text-slate-200">
            <!-- Birth -->
            <div v-if="person.birth_date || person.birth_year || person.birth_place" class="flex items-start justify-between gap-1.5">
                <span class="font-normal text-slate-700 dark:text-slate-300 shrink-0 text-left">Birth</span>
                <div class="text-right flex-1 min-w-0">
                    <div v-if="person.birth_date || person.birth_year" class="font-medium text-slate-900 dark:text-white">
                        {{ formatVal(person.birth_date) || person.birth_year }}
                    </div>
                    <div v-if="person.birth_place" class="text-[10px] text-slate-600 dark:text-slate-400 truncate" :title="person.birth_place">
                        {{ person.birth_place }}
                    </div>
                </div>
            </div>

            <!-- Marriage -->
            <div v-if="person.marriage_date || person.marriage_year || person.marriage_spouse_name || person.marriage_place" class="flex items-start justify-between gap-1.5">
                <span class="font-normal text-slate-700 dark:text-slate-300 shrink-0 text-left">Marriage</span>
                <div class="text-right flex-1 min-w-0">
                    <div v-if="person.marriage_date || person.marriage_year" class="font-medium text-slate-900 dark:text-white">
                        {{ formatVal(person.marriage_date) || person.marriage_year }}
                    </div>
                    <div v-if="person.marriage_spouse_name" class="text-[10.5px] font-semibold text-slate-800 dark:text-slate-200 truncate" :title="person.marriage_spouse_name">
                        {{ person.marriage_spouse_name }}
                    </div>
                    <div v-if="person.marriage_place" class="text-[10px] text-slate-600 dark:text-slate-400 truncate" :title="person.marriage_place">
                        {{ person.marriage_place }}
                    </div>
                </div>
            </div>

            <!-- Death -->
            <div v-if="person.death_date || person.death_year || person.death_place || person.death_note" class="flex items-start justify-between gap-1.5">
                <span class="font-normal text-slate-700 dark:text-slate-300 shrink-0 text-left">Death</span>
                <div class="text-right flex-1 min-w-0">
                    <div v-if="person.death_date || person.death_year" class="font-medium text-slate-900 dark:text-white">
                        {{ formatVal(person.death_date) || person.death_year }}
                    </div>
                    <div v-if="person.death_note" class="text-[10px] italic text-slate-500 dark:text-slate-400">
                        {{ person.death_note }}
                    </div>
                    <div v-if="person.death_place" class="text-[10px] text-slate-600 dark:text-slate-400 truncate" :title="person.death_place">
                        {{ person.death_place }}
                    </div>
                </div>
            </div>

            <!-- Burial -->
            <div v-if="person.burial_date || person.burial_place" class="flex items-start justify-between gap-1.5">
                <span class="font-normal text-slate-700 dark:text-slate-300 shrink-0 text-left">Burial</span>
                <div class="text-right flex-1 min-w-0">
                    <div v-if="person.burial_date" class="font-medium text-slate-900 dark:text-white">
                        {{ formatVal(person.burial_date) }}
                    </div>
                    <div v-if="person.burial_place" class="text-[10px] text-slate-600 dark:text-slate-400 truncate" :title="person.burial_place">
                        {{ person.burial_place }}
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
    letter-spacing: 0.12em;
}

.heritage-banner {
    background-color: #1e293b;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.25);
}
</style>
