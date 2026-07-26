<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import {
    ZoomIn, ZoomOut, Maximize2, User, RefreshCcw, Layers
} from '@lucide/vue';
import GedcomAncestorNode from './GedcomAncestorNode.vue';
import GedcomDescendantNode from './GedcomDescendantNode.vue';

const props = defineProps<{
    rootPersonId: string | null;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();

const loading = ref(false);
const treeData = ref<any>(null);
const zoomLevel = ref(1);
const ancestorLevels = ref(2);
const descendantLevels = ref(2);

const fetchTreeData = async (id: string) => {
    loading.value = true;
    try {
        const res = await fetch(`/api/gedcom/tree/${id}?ancestors=${ancestorLevels.value}&descendants=${descendantLevels.value}`);
        if (res.ok) {
            treeData.value = await res.json();
        }
    } catch (e) {
        console.error('Failed to fetch tree data:', e);
    } finally {
        loading.value = false;
    }
};

watch(
    [() => props.rootPersonId, ancestorLevels, descendantLevels],
    ([newId]) => {
        if (newId) {
            fetchTreeData(newId);
        }
    },
    { immediate: true }
);

const ancestorBadgeLabel = computed(() => {
    const l = ancestorLevels.value;
    if (l === 0) return 'Ancestors Hidden';
    if (l === 1) return 'Ancestors (Parents)';
    if (l === 2) return 'Ancestors (Parents & Grandparents)';
    if (l === 3) return 'Ancestors (Parents, Grandparents & Gt-Grandparents)';
    return `Ancestors (${l} Generations)`;
});

const descendantBadgeLabel = computed(() => {
    const l = descendantLevels.value;
    if (l === 0) return 'Descendants Hidden';
    if (l === 1) return 'Descendants (Children)';
    if (l === 2) return 'Descendants (Children & Grandchildren)';
    if (l === 3) return 'Descendants (Children, Grandchildren & Gt-Grandchildren)';
    return `Descendants (${l} Generations)`;
});

const zoomIn = () => {
    zoomLevel.value = Math.min(1.8, zoomLevel.value + 0.15);
};

const zoomOut = () => {
    zoomLevel.value = Math.max(0.5, zoomLevel.value - 0.15);
};

const resetZoom = () => {
    zoomLevel.value = 1;
};
</script>

<template>
    <div class="relative bg-slate-900 rounded-3xl border border-slate-800 overflow-hidden shadow-2xl min-h-[680px] flex flex-col">
        <!-- Floating Tree Controls Bar -->
        <div class="absolute top-4 left-4 z-20 flex flex-wrap items-center gap-3 bg-slate-900/90 backdrop-blur-md p-2.5 rounded-2xl border border-slate-800 shadow-xl">
            <!-- Zoom controls -->
            <div class="flex items-center gap-1">
                <button
                    @click="zoomIn"
                    class="p-2 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-colors"
                    title="Zoom In"
                >
                    <ZoomIn class="w-4 h-4" />
                </button>
                <button
                    @click="zoomOut"
                    class="p-2 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-colors"
                    title="Zoom Out"
                >
                    <ZoomOut class="w-4 h-4" />
                </button>
                <button
                    @click="resetZoom"
                    class="p-2 rounded-xl text-slate-300 hover:bg-slate-800 hover:text-white transition-colors"
                    title="Reset Zoom"
                >
                    <Maximize2 class="w-4 h-4" />
                </button>
                <span class="text-xs font-semibold text-slate-400 px-1">
                    {{ Math.round(zoomLevel * 100) }}%
                </span>
            </div>

            <div class="h-4 w-px bg-slate-800"></div>

            <!-- Ancestor levels dropdown -->
            <div class="flex items-center gap-2">
                <label class="text-xs font-medium text-slate-400 flex items-center gap-1">
                    <Layers class="w-3.5 h-3.5 text-indigo-400" />
                    Ancestors:
                </label>
                <select
                    v-model.number="ancestorLevels"
                    class="bg-slate-800 border border-slate-700 text-white text-xs font-semibold rounded-xl px-2.5 py-1.5 focus:outline-hidden focus:border-indigo-500 transition-colors cursor-pointer"
                >
                    <option :value="0">0 (Hide)</option>
                    <option :value="1">1 (Parents)</option>
                    <option :value="2">2 (Grandparents)</option>
                    <option :value="3">3 (Gt-Grandparents)</option>
                    <option :value="4">4 Generations</option>
                    <option :value="5">5 Generations</option>
                </select>
            </div>

            <!-- Descendant levels dropdown -->
            <div class="flex items-center gap-2">
                <label class="text-xs font-medium text-slate-400 flex items-center gap-1">
                    <Layers class="w-3.5 h-3.5 text-emerald-400" />
                    Descendants:
                </label>
                <select
                    v-model.number="descendantLevels"
                    class="bg-slate-800 border border-slate-700 text-white text-xs font-semibold rounded-xl px-2.5 py-1.5 focus:outline-hidden focus:border-emerald-500 transition-colors cursor-pointer"
                >
                    <option :value="0">0 (Hide)</option>
                    <option :value="1">1 (Children)</option>
                    <option :value="2">2 (Grandchildren)</option>
                    <option :value="3">3 (Gt-Grandchildren)</option>
                    <option :value="4">4 Generations</option>
                    <option :value="5">5 Generations</option>
                </select>
            </div>
        </div>

        <!-- Tree Canvas -->
        <div class="flex-1 overflow-auto p-12 flex items-center justify-center cursor-grab active:cursor-grabbing">
            <div
                v-if="loading"
                class="flex flex-col items-center justify-center py-20 text-slate-400"
            >
                <div class="w-10 h-10 border-3 border-indigo-500 border-t-transparent rounded-full animate-spin"></div>
                <p class="mt-4 text-xs font-semibold text-slate-300">Rendering visual tree graph...</p>
            </div>

            <div
                v-else-if="treeData"
                class="transition-transform duration-200 ease-out origin-center flex flex-col items-center gap-12"
                :style="{ transform: `scale(${zoomLevel})` }"
            >
                <!-- Ancestors Section (Top) -->
                <div v-if="ancestorLevels > 0 && treeData.ancestors && treeData.ancestors.parents && treeData.ancestors.parents.length > 0" class="flex flex-col items-center gap-6">
                    <span class="text-[11px] font-bold uppercase tracking-widest text-indigo-400 bg-indigo-950/80 px-3 py-1 rounded-full border border-indigo-800/50">
                        {{ ancestorBadgeLabel }}
                    </span>

                    <div class="flex items-start gap-8">
                        <GedcomAncestorNode
                            v-for="parent in treeData.ancestors.parents"
                            :key="parent.id"
                            :person="parent"
                            :level="1"
                            @select-person="emit('select-person', $event)"
                            @change-root="emit('change-root', $event)"
                        />
                    </div>
                </div>

                <!-- Center Root Person Node -->
                <div v-if="treeData.ancestors || treeData.descendants" class="relative py-2">
                    <div class="absolute inset-0 bg-indigo-500/20 blur-xl rounded-full"></div>
                    <div
                        @click="emit('select-person', (treeData.ancestors || treeData.descendants).id)"
                        class="relative bg-gradient-to-r from-indigo-900 via-indigo-950 to-slate-900 border-2 border-indigo-400 rounded-3xl p-5 w-72 shadow-2xl cursor-pointer transition-all hover:scale-105 group"
                    >
                        <div class="flex items-center gap-4">
                            <img
                                v-if="(treeData.ancestors || treeData.descendants).primary_media"
                                :src="(treeData.ancestors || treeData.descendants).primary_media.url"
                                class="w-16 h-16 rounded-2xl object-cover shrink-0 border-2 border-indigo-400 shadow-md"
                            />
                            <div v-else class="w-16 h-16 rounded-2xl bg-slate-800 border border-slate-700 flex items-center justify-center text-indigo-400 shrink-0">
                                <User class="w-8 h-8" />
                            </div>

                            <div class="min-w-0 flex-1">
                                <span class="px-2 py-0.5 rounded-md bg-indigo-500/20 text-indigo-300 text-[10px] font-bold uppercase tracking-wider">
                                    Tree Focus
                                </span>
                                <h3 class="text-sm font-extrabold text-white truncate mt-1 group-hover:text-indigo-300">
                                    {{ (treeData.ancestors || treeData.descendants).name }}
                                </h3>
                                <p class="text-xs text-indigo-200 font-medium mt-0.5">
                                    {{ (treeData.ancestors || treeData.descendants).birth_year || '?' }} – {{ (treeData.ancestors || treeData.descendants).death_year || 'Present' }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Descendants Section (Bottom) -->
                <div v-if="descendantLevels > 0 && treeData.descendants && treeData.descendants.children && treeData.descendants.children.length > 0" class="flex flex-col items-center gap-6">
                    <span class="text-[11px] font-bold uppercase tracking-widest text-emerald-400 bg-emerald-950/80 px-3 py-1 rounded-full border border-emerald-800/50">
                        {{ descendantBadgeLabel }}
                    </span>

                    <div class="flex flex-wrap justify-center items-start gap-8">
                        <GedcomDescendantNode
                            v-for="child in treeData.descendants.children"
                            :key="child.id"
                            :person="child"
                            :level="1"
                            @select-person="emit('select-person', $event)"
                            @change-root="emit('change-root', $event)"
                        />
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
