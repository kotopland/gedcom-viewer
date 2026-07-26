<script setup lang="ts">
import { User, RefreshCcw } from '@lucide/vue';

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
        <!-- Render parents recursively above this person -->
        <div v-if="person.parents && person.parents.length > 0" class="flex items-start gap-4 sm:gap-6">
            <GedcomAncestorNode
                v-for="parent in person.parents"
                :key="parent.id"
                :person="parent"
                :level="level + 1"
                @select-person="emit('select-person', $event)"
                @change-root="emit('change-root', $event)"
            />
        </div>

        <!-- Person Card -->
        <div
            @click="emit('select-person', person.id)"
            :class="[
                'relative border rounded-xl shadow-md cursor-pointer transition-all hover:scale-105 group',
                level === 1
                    ? 'bg-gradient-to-b from-slate-800 to-slate-850 hover:from-slate-750 hover:to-slate-800 border-2 border-indigo-500/40 p-3.5 w-52'
                    : level === 2
                    ? 'bg-slate-800/90 hover:bg-slate-800 border-slate-700/80 p-2.5 w-44'
                    : 'bg-slate-850/90 hover:bg-slate-800 border-slate-700/60 p-2 w-36 text-xs'
            ]"
        >
            <button
                @click.stop="emit('change-root', person.id)"
                class="absolute -top-2 -right-2 p-1 bg-indigo-600 hover:bg-indigo-500 text-white rounded-full shadow-xs opacity-0 group-hover:opacity-100 transition-opacity"
                title="Center tree on this person"
            >
                <RefreshCcw class="w-3 h-3" />
            </button>

            <div class="flex items-center gap-2.5">
                <img
                    v-if="person.primary_media"
                    :src="person.primary_media.url"
                    :class="[
                        'rounded-lg object-cover shrink-0',
                        level === 1 ? 'w-10 h-10 border border-slate-700' : 'w-8 h-8'
                    ]"
                />
                <div
                    v-else
                    :class="[
                        'rounded-lg bg-slate-700 flex items-center justify-center text-slate-400 shrink-0',
                        level === 1 ? 'w-10 h-10' : 'w-8 h-8'
                    ]"
                >
                    <User :class="level === 1 ? 'w-5 h-5' : 'w-4 h-4'" />
                </div>
                <div class="min-w-0 flex-1">
                    <div
                        :class="[
                            'font-bold truncate group-hover:text-indigo-400',
                            level === 1 ? 'text-xs text-white' : 'text-xs text-slate-200'
                        ]"
                    >
                        {{ person.name }}
                    </div>
                    <div
                        :class="[
                            'font-medium',
                            level === 1 ? 'text-[11px] text-indigo-300' : 'text-[10px] text-slate-400'
                        ]"
                    >
                        {{ person.birth_year || '?' }} – {{ person.death_year || '?' }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
