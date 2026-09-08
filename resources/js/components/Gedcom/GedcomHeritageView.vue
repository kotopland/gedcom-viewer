<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    ZoomIn, ZoomOut, Maximize2, RefreshCcw, SlidersHorizontal,
    Expand, Shrink, ChevronRight, ChevronLeft, Search, Plus, Minus,
    ArrowUp, ArrowDown, Sparkles, Users, Download
} from '@lucide/vue';
import GedcomHeritageNode from './GedcomHeritageNode.vue';
import GedcomHeritageAncestorNode from './GedcomHeritageAncestorNode.vue';
import GedcomHeritageDescendantNode from './GedcomHeritageDescendantNode.vue';

const props = defineProps<{
    rootPersonId: string | null;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();

const page = usePage();
const isVerified = computed(() => !!(page.props.auth?.user?.is_verified || page.props.auth?.user?.is_superuser));
const exportPdfLoading = ref(false);

const loading = ref(false);
const treeData = ref<any>(null);
const focusId = ref<string | null>(props.rootPersonId);
const ancestorLevels = ref(
    typeof window !== 'undefined' && sessionStorage.getItem('gedcom_heritage_ancestor_levels')
        ? parseInt(sessionStorage.getItem('gedcom_heritage_ancestor_levels')!, 10) || 3
        : 3
);
const descendantLevels = ref(
    typeof window !== 'undefined' && sessionStorage.getItem('gedcom_heritage_descendant_levels')
        ? parseInt(sessionStorage.getItem('gedcom_heritage_descendant_levels')!, 10) || 3
        : 3
);
const showSiblings = ref(
    typeof window !== 'undefined' && sessionStorage.getItem('gedcom_heritage_show_siblings') !== null
        ? sessionStorage.getItem('gedcom_heritage_show_siblings') === 'true'
        : false
);

watch(ancestorLevels, (val) => {
    if (typeof window !== 'undefined') sessionStorage.setItem('gedcom_heritage_ancestor_levels', String(val));
});
watch(descendantLevels, (val) => {
    if (typeof window !== 'undefined') sessionStorage.setItem('gedcom_heritage_descendant_levels', String(val));
});
watch(showSiblings, (val) => {
    if (typeof window !== 'undefined') sessionStorage.setItem('gedcom_heritage_show_siblings', String(val));
});

// Zoom and Pan
const zoomLevel = ref(0.85);
const panX = ref(0);
const panY = ref(0);
const isFullscreen = ref(false);
const isControlsCollapsed = ref(true);
const isMobile = ref(false);

const canvasContainerRef = ref<HTMLElement | null>(null);
const treeContentRef = ref<HTMLElement | null>(null);

// Search input
const searchInput = ref('');
const isSearching = ref(false);
const searchResults = ref<any[]>([]);

// Drag and Touch interactions
const isDragging = ref(false);
const isPointerDown = ref(false);
let startX = 0;
let startY = 0;
let startPanX = 0;
let startPanY = 0;
let dragMoved = false;

// Pinch zoom
let initialPinchDistance: number | null = null;
let initialPinchZoom = 1;
let initialPinchFocalX = 0;
let initialPinchFocalY = 0;
let initialPinchPanX = 0;
let initialPinchPanY = 0;

const checkMobile = () => {
    isMobile.value = window.innerWidth < 640;
};

const centerTree = () => {
    if (canvasContainerRef.value && treeContentRef.value) {
        const containerW = canvasContainerRef.value.clientWidth;
        const containerH = canvasContainerRef.value.clientHeight;
        const contentW = treeContentRef.value.scrollWidth || 1200;
        const contentH = treeContentRef.value.scrollHeight || 1000;

        const scaledW = contentW * zoomLevel.value;
        const scaledH = contentH * zoomLevel.value;

        panX.value = Math.max(20, Math.round((containerW - scaledW) / 2));
        panY.value = Math.max(30, Math.round((containerH - scaledH) / 4));
    }
};

const onPointerDown = (e: PointerEvent) => {
    if (e.button !== 0 && e.pointerType === 'mouse') return;
    const target = e.target as HTMLElement;
    if (target.closest('button, select, input, a, [data-clickable]')) return;

    isPointerDown.value = true;
    dragMoved = false;
    startX = e.clientX;
    startY = e.clientY;
    startPanX = panX.value;
    startPanY = panY.value;
};

const onPointerMove = (e: PointerEvent) => {
    if (!isPointerDown.value) return;
    if (initialPinchDistance !== null) return;

    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    if (!dragMoved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
        dragMoved = true;
        isDragging.value = true;
        try {
            (e.currentTarget as HTMLElement)?.setPointerCapture?.(e.pointerId);
        } catch (_) {}
    }

    if (dragMoved) {
        panX.value = startPanX + dx;
        panY.value = startPanY + dy;
    }
};

const onPointerUp = (e?: PointerEvent) => {
    if (e && e.currentTarget) {
        try {
            (e.currentTarget as HTMLElement)?.releasePointerCapture?.(e.pointerId);
        } catch (_) {}
    }
    isPointerDown.value = false;
    setTimeout(() => {
        isDragging.value = false;
        dragMoved = false;
    }, 50);
};

const onTouchStart = (e: TouchEvent) => {
    if (e.touches.length === 1) {
        const target = e.target as HTMLElement;
        if (target.closest('button, select, input, a, [data-clickable]')) return;
        const t = e.touches[0];
        isPointerDown.value = true;
        dragMoved = false;
        startX = t.clientX;
        startY = t.clientY;
        startPanX = panX.value;
        startPanY = panY.value;
    } else if (e.touches.length === 2 && canvasContainerRef.value) {
        const rect = canvasContainerRef.value.getBoundingClientRect();
        const t1 = e.touches[0];
        const t2 = e.touches[1];

        const focalX = (t1.clientX + t2.clientX) / 2 - rect.left;
        const focalY = (t1.clientY + t2.clientY) / 2 - rect.top;

        initialPinchDistance = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
        initialPinchZoom = zoomLevel.value;
        initialPinchFocalX = focalX;
        initialPinchFocalY = focalY;
        initialPinchPanX = panX.value;
        initialPinchPanY = panY.value;
    }
};

const onTouchMove = (e: TouchEvent) => {
    if (e.touches.length === 1 && isPointerDown.value && initialPinchDistance === null) {
        const t = e.touches[0];
        const dx = t.clientX - startX;
        const dy = t.clientY - startY;

        if (!dragMoved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
            dragMoved = true;
            isDragging.value = true;
        }

        if (dragMoved) {
            e.preventDefault();
            panX.value = startPanX + dx;
            panY.value = startPanY + dy;
        }
    } else if (e.touches.length === 2 && initialPinchDistance && canvasContainerRef.value) {
        e.preventDefault();
        const rect = canvasContainerRef.value.getBoundingClientRect();
        const t1 = e.touches[0];
        const t2 = e.touches[1];

        const currentDistance = Math.hypot(t1.clientX - t2.clientX, t1.clientY - t2.clientY);
        const currentFocalX = (t1.clientX + t2.clientX) / 2 - rect.left;
        const currentFocalY = (t1.clientY + t2.clientY) / 2 - rect.top;

        const scaleRatio = currentDistance / initialPinchDistance;
        const newZoom = Math.min(2.5, Math.max(0.2, initialPinchZoom * scaleRatio));

        const contentX = (initialPinchFocalX - initialPinchPanX) / initialPinchZoom;
        const contentY = (initialPinchFocalY - initialPinchPanY) / initialPinchZoom;

        panX.value = Math.round(currentFocalX - contentX * newZoom);
        panY.value = Math.round(currentFocalY - contentY * newZoom);
        zoomLevel.value = Math.round(newZoom * 100) / 100;
    }
};

const onTouchEnd = (e: TouchEvent) => {
    if (e.touches.length === 0) {
        isPointerDown.value = false;
        initialPinchDistance = null;
        setTimeout(() => {
            isDragging.value = false;
            dragMoved = false;
        }, 50);
    } else if (e.touches.length === 1) {
        initialPinchDistance = null;
        const t = e.touches[0];
        startX = t.clientX;
        startY = t.clientY;
        startPanX = panX.value;
        startPanY = panY.value;
    }
};

const onWheel = (e: WheelEvent) => {
    if (e.ctrlKey || e.metaKey) {
        e.preventDefault();
        if (!canvasContainerRef.value) return;

        const rect = canvasContainerRef.value.getBoundingClientRect();
        const cursorX = e.clientX - rect.left;
        const cursorY = e.clientY - rect.top;

        const zoomFactor = e.deltaY < 0 ? 1.15 : 0.85;
        const newZoom = Math.min(2.5, Math.max(0.2, Math.round(zoomLevel.value * zoomFactor * 100) / 100));

        const contentX = (cursorX - panX.value) / zoomLevel.value;
        const contentY = (cursorY - panY.value) / zoomLevel.value;

        panX.value = Math.round(cursorX - contentX * newZoom);
        panY.value = Math.round(cursorY - contentY * newZoom);
        zoomLevel.value = newZoom;
    }
};

const applyZoomFromCenter = (newZoom: number) => {
    if (!canvasContainerRef.value) return;
    const rect = canvasContainerRef.value.getBoundingClientRect();
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;

    const contentX = (centerX - panX.value) / zoomLevel.value;
    const contentY = (centerY - panY.value) / zoomLevel.value;

    panX.value = Math.round(centerX - contentX * newZoom);
    panY.value = Math.round(centerY - contentY * newZoom);
    zoomLevel.value = newZoom;
};

const zoomIn = () => {
    const newZoom = Math.min(2.5, Math.round((zoomLevel.value + 0.15) * 100) / 100);
    applyZoomFromCenter(newZoom);
};

const zoomOut = () => {
    const newZoom = Math.max(0.2, Math.round((zoomLevel.value - 0.15) * 100) / 100);
    applyZoomFromCenter(newZoom);
};

const resetZoom = () => {
    const maxGen = Math.max(ancestorLevels.value, descendantLevels.value);
    if (maxGen >= 4) {
        zoomLevel.value = 0.55;
    } else if (maxGen === 3) {
        zoomLevel.value = 0.70;
    } else {
        zoomLevel.value = isMobile.value ? 0.65 : 0.85;
    }
    nextTick(() => setTimeout(centerTree, 60));
};

const toggleFullscreen = () => {
    isFullscreen.value = !isFullscreen.value;
    if (isFullscreen.value) {
        document.body.style.overflow = 'hidden';
    } else {
        document.body.style.overflow = '';
    }
    setTimeout(centerTree, 100);
};

const handlePersonSelect = (id: string) => {
    if (dragMoved || isDragging.value) return;
    emit('select-person', id);
};

const handleChangeRoot = (id: string) => {
    if (dragMoved || isDragging.value) return;
    focusId.value = id;
    emit('change-root', id);
};

const fetchTreeData = async (id: string) => {
    if (!id) return;
    loading.value = true;
    try {
        const res = await fetch(`/api/gedcom/tree/${id}?ancestors=${ancestorLevels.value}&descendants=${descendantLevels.value}`);
        if (res.ok) {
            treeData.value = await res.json();
            nextTick(() => setTimeout(centerTree, 60));
        }
    } catch (e) {
        console.error('Failed to fetch heritage tree data:', e);
    } finally {
        loading.value = false;
    }
};

const handleSearch = async () => {
    if (!searchInput.value.trim()) {
        searchResults.value = [];
        return;
    }
    isSearching.value = true;
    try {
        const res = await fetch(`/api/gedcom/search?q=${encodeURIComponent(searchInput.value)}&limit=8`);
        if (res.ok) {
            const data = await res.json();
            searchResults.value = data.data || [];
        }
    } catch (e) {
        console.error('Search error:', e);
    } finally {
        isSearching.value = false;
    }
};

const selectSearchPerson = (person: any) => {
    handleChangeRoot(person.id);
    searchInput.value = '';
    searchResults.value = [];
};

// Primary focus person and primary spouse
const primary = computed(() => treeData.value?.primary);
const primarySpouse = computed(() => {
    const spouses = primary.value?.spouses || [];
    return spouses.length > 0 ? spouses[0] : null;
});

// Focus person's parents (Level 1 ancestors)
const focusParents = computed(() => {
    return treeData.value?.ancestors?.parents || [];
});

// Spouse's parents (Level 1 ancestors on spouse side)
const spouseParents = computed(() => {
    const sAncestors = primarySpouse.value?.ancestors;
    return sAncestors?.parents || [];
});

// Descendants (Children of focus couple)
const childrenList = computed(() => {
    return treeData.value?.descendants?.children || [];
});

// Combined sibling generation list: focused person + siblings
// Sorted chronologically by birth year (with focused person flagged with is_primary: true)
const siblingGeneration = computed(() => {
    if (!primary.value) return [];

    if (!showSiblings.value) {
        return [
            {
                ...primary.value,
                is_primary: true,
                children: childrenList.value,
                spouses: primarySpouse.value ? [primarySpouse.value] : []
            }
        ];
    }

    const sibs = (treeData.value?.siblings || []).map((s: any) => ({
        ...s,
        is_primary: false,
        spouses: [],
        children: []
    }));

    const primaryEntry = {
        ...primary.value,
        is_primary: true,
        children: childrenList.value,
        spouses: primarySpouse.value ? [primarySpouse.value] : []
    };

    const all = [...sibs, primaryEntry];

    // Sort chronologically by birth year
    all.sort((a, b) => {
        const yA = a.birth_year ?? 9999;
        const yB = b.birth_year ?? 9999;
        if (yA !== yB) return yA - yB;
        return (a.name || '').localeCompare(b.name || '');
    });

    return all;
});

watch(
    [() => props.rootPersonId, ancestorLevels, descendantLevels],
    ([newId]) => {
        if (newId) {
            focusId.value = newId;
            fetchTreeData(newId);
        }
    },
    { immediate: true }
);

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
});

// Helper to load external scripts dynamically on-demand
const loadExternalScript = (src: string, globalName: string): Promise<any> => {
    if ((window as any)[globalName]) {
        return Promise.resolve((window as any)[globalName]);
    }
    return new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${src}"]`);
        if (existing) {
            existing.addEventListener('load', () => resolve((window as any)[globalName]));
            existing.addEventListener('error', reject);
            return;
        }
        const script = document.createElement('script');
        script.src = src;
        script.async = true;
        script.onload = () => resolve((window as any)[globalName]);
        script.onerror = () => reject(new Error(`Failed to load ${src}`));
        document.head.appendChild(script);
    });
};

const exportToPdf = async () => {
    if (!isVerified.value || exportPdfLoading.value) return;
    if (!treeContentRef.value) {
        alert('Heritage tree is not available to export.');
        return;
    }

    exportPdfLoading.value = true;
    const el = treeContentRef.value;
    const originalTransform = el.style.transform;
    const originalTransition = el.style.transition;

    try {
        // Load html-to-image and jspdf dynamically from CDN
        await Promise.all([
            loadExternalScript('https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js', 'htmlToImage'),
            loadExternalScript('https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js', 'jspdf'),
        ]);

        const htmlToImage = (window as any).htmlToImage;
        const { jsPDF } = (window as any).jspdf;

        if (!htmlToImage || !jsPDF) {
            throw new Error('PDF export libraries could not be initialized.');
        }

        // Temporarily reset CSS transform to unscaled 1:1 state to capture full chart without clipping
        el.style.transition = 'none';
        el.style.transform = 'none';

        await nextTick();
        await new Promise((r) => setTimeout(r, 60));

        const isDark = document.documentElement.classList.contains('dark');
        const bgColor = isDark ? '#020617' : '#f8fafc';

        // Measure natural dimensions
        const width = Math.max(el.scrollWidth, el.offsetWidth) + 40;
        const height = Math.max(el.scrollHeight, el.offsetHeight) + 40;

        const dataUrl = await htmlToImage.toJpeg(el, {
            backgroundColor: bgColor,
            quality: 0.95,
            pixelRatio: 2,
            cacheBust: true,
        });

        // Restore view transform immediately after capture
        el.style.transform = originalTransform;
        el.style.transition = originalTransition;

        const orientation = width >= height ? 'landscape' : 'portrait';
        const pdf = new jsPDF({
            orientation,
            unit: 'pt',
            format: [width, height],
        });

        pdf.addImage(dataUrl, 'JPEG', 20, 20, width - 40, height - 40);

        const personName = primary.value?.name
            ? primary.value.name.replace(/[^a-zA-Z0-9_-]/g, '_')
            : 'heritage_chart';
        pdf.save(`${personName}_heritage_chart.pdf`);
    } catch (err: any) {
        console.error('PDF export failed:', err);
        alert('Failed to generate PDF: ' + (err?.message || 'Unknown error'));
        el.style.transform = originalTransform;
        el.style.transition = originalTransition;
    } finally {
        exportPdfLoading.value = false;
    }
};

onUnmounted(() => {
    window.removeEventListener('resize', checkMobile);
    document.body.style.overflow = '';
});
</script>

<template>
    <div
        :class="[
            'transition-all duration-300 flex flex-col',
            isFullscreen
                ? 'fixed inset-0 z-50 rounded-none w-screen h-screen min-h-screen bg-slate-50 dark:bg-slate-950 border-none p-0 shadow-none'
                : 'relative bg-white/95 dark:bg-slate-900 rounded-3xl border border-slate-200/90 dark:border-slate-800 overflow-hidden shadow-2xl flex-1 min-h-[500px] sm:min-h-[750px] h-[calc(100dvh-5.5rem-env(safe-area-inset-top,0px))] sm:h-[84vh]'
        ]"
    >
        <!-- Floating Floating Controls Header -->
        <div
            :class="[
                'absolute z-20 transition-all duration-300 max-w-[calc(100vw-2rem)]',
                isFullscreen ? 'top-4 left-4 sm:top-6 sm:left-6' : 'top-3 left-3 sm:top-4 sm:left-4'
            ]"
        >
            <!-- Collapsed Controls Pill -->
            <div
                v-if="isControlsCollapsed"
                class="flex items-center gap-1.5 sm:gap-2 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-1.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl"
            >
                <button
                    @click="isControlsCollapsed = false"
                    class="inline-flex items-center gap-1.5 sm:gap-2 px-2.5 sm:px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-750 text-slate-800 dark:text-slate-200 text-xs font-bold transition-all shadow-xs cursor-pointer group"
                    title="Open Heritage Chart Settings"
                >
                    <SlidersHorizontal class="w-3.5 h-3.5 text-indigo-600 dark:text-indigo-400 group-hover:scale-110 transition-transform" />
                    <span>Heritage Chart</span>
                    <span class="text-[10px] text-slate-600 dark:text-slate-400 font-medium px-1 bg-slate-200/80 dark:bg-slate-700/60 rounded-md">
                        {{ Math.round(zoomLevel * 100) }}% ({{ ancestorLevels }}A / {{ descendantLevels }}D{{ showSiblings && treeData?.siblings?.length ? ' + Siblings' : '' }})
                    </span>
                    <ChevronRight class="w-3.5 h-3.5 text-slate-400 group-hover:translate-x-0.5 transition-transform" />
                </button>

                <button
                    @click="zoomIn"
                    class="p-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                    title="Zoom In"
                >
                    <ZoomIn class="w-3.5 h-3.5" />
                </button>

                <button
                    @click="zoomOut"
                    class="p-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 dark:hover:bg-slate-750 text-slate-700 dark:text-slate-300 transition-colors cursor-pointer"
                    title="Zoom Out"
                >
                    <ZoomOut class="w-3.5 h-3.5" />
                </button>

                <button
                    @click="toggleFullscreen"
                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-slate-900 dark:bg-slate-800 hover:bg-indigo-600 text-white text-xs font-bold transition-all shadow-xs active:scale-95 cursor-pointer"
                    :title="isFullscreen ? 'Exit Full Screen' : 'Full Screen'"
                >
                    <Shrink v-if="isFullscreen" class="w-3.5 h-3.5" />
                    <Expand v-else class="w-3.5 h-3.5" />
                </button>

                <button
                    v-if="isVerified"
                    @click="exportToPdf"
                    :disabled="exportPdfLoading"
                    class="p-1.5 rounded-xl bg-red-600/90 hover:bg-red-600 text-white transition-colors cursor-pointer disabled:opacity-50"
                    title="Export High-Resolution PDF Chart"
                >
                    <Download v-if="!exportPdfLoading" class="w-3.5 h-3.5" />
                    <RefreshCcw v-else class="w-3.5 h-3.5 animate-spin" />
                </button>
            </div>

            <!-- Expanded Controls Bar -->
            <div
                v-else
                class="flex flex-col sm:flex-row sm:flex-wrap items-stretch sm:items-center gap-2.5 sm:gap-3 bg-white/95 dark:bg-slate-900/95 backdrop-blur-md p-3 sm:p-2.5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-2xl animate-in fade-in zoom-in-95 duration-150 max-w-[calc(100vw-2.5rem)] overflow-x-auto"
            >
                <div class="flex items-center justify-between gap-2">
                    <button
                        @click="toggleFullscreen"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-900 dark:bg-indigo-600 hover:bg-indigo-600 text-white text-xs font-bold transition-all shadow-md active:scale-95 cursor-pointer"
                    >
                        <Shrink v-if="isFullscreen" class="w-3.5 h-3.5" />
                        <Expand v-else class="w-3.5 h-3.5" />
                        <span>{{ isFullscreen ? 'Exit' : 'Full Screen' }}</span>
                    </button>

                    <button
                        v-if="isVerified"
                        @click="exportToPdf"
                        :disabled="exportPdfLoading"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-red-600 hover:bg-red-700 disabled:opacity-50 text-white text-xs font-bold transition-all shadow-md active:scale-95 cursor-pointer"
                        title="Export High-Resolution PDF Chart"
                    >
                        <Download v-if="!exportPdfLoading" class="w-3.5 h-3.5" />
                        <RefreshCcw v-else class="w-3.5 h-3.5 animate-spin" />
                        <span>{{ exportPdfLoading ? 'Exporting...' : 'PDF' }}</span>
                    </button>

                    <!-- Zoom Controls -->
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-xl">
                        <button @click="zoomIn" class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer" title="Zoom In">
                            <ZoomIn class="w-3.5 h-3.5" />
                        </button>
                        <button @click="zoomOut" class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer" title="Zoom Out">
                            <ZoomOut class="w-3.5 h-3.5" />
                        </button>
                        <button @click="resetZoom" class="p-1.5 rounded-lg text-slate-700 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700 cursor-pointer" title="Fit Screen">
                            <Maximize2 class="w-3.5 h-3.5" />
                        </button>
                        <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 px-1">
                            {{ Math.round(zoomLevel * 100) }}%
                        </span>
                    </div>

                    <button
                        @click="isControlsCollapsed = true"
                        class="p-1.5 rounded-xl text-slate-500 hover:text-slate-800 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800 cursor-pointer"
                        title="Collapse"
                    >
                        <ChevronLeft class="w-4 h-4" />
                    </button>
                </div>

                <!-- Generation Depth Selectors (Up to 10 Generations) -->
                <div class="flex items-center gap-2.5 border-t sm:border-t-0 sm:border-l border-slate-200 dark:border-slate-800 pt-2 sm:pt-0 sm:pl-3">
                    <!-- Ancestor Depth -->
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                        <ArrowUp class="w-3.5 h-3.5 text-indigo-500 shrink-0" />
                        <span class="hidden sm:inline">Ancestors:</span>
                        <div class="flex items-center gap-0.5 bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg border border-slate-300 dark:border-slate-700">
                            <button
                                @click="ancestorLevels = Math.max(0, ancestorLevels - 1)"
                                :disabled="ancestorLevels <= 0"
                                class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 cursor-pointer"
                                title="Decrease Ancestor Generations"
                            >
                                <Minus class="w-3 h-3" />
                            </button>
                            <select
                                v-model.number="ancestorLevels"
                                class="bg-transparent text-xs font-bold text-slate-800 dark:text-slate-200 px-1.5 py-0.5 border-none focus:outline-hidden cursor-pointer"
                            >
                                <option :value="0">0 (Hidden)</option>
                                <option :value="1">1 Gen (Parents)</option>
                                <option :value="2">2 Gens (Grandparents)</option>
                                <option :value="3">3 Gens (Great-GP)</option>
                                <option :value="4">4 Gens (2nd Gt-GP)</option>
                                <option :value="5">5 Gens (3rd Gt-GP)</option>
                                <option :value="6">6 Gens (4th Gt-GP)</option>
                                <option :value="7">7 Gens (5th Gt-GP)</option>
                                <option :value="8">8 Gens (6th Gt-GP)</option>
                                <option :value="10">10 Generations</option>
                            </select>
                            <button
                                @click="ancestorLevels = Math.min(10, ancestorLevels + 1)"
                                :disabled="ancestorLevels >= 10"
                                class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 cursor-pointer"
                                title="Increase Ancestor Generations"
                            >
                                <Plus class="w-3 h-3" />
                            </button>
                        </div>
                    </div>

                    <!-- Descendant Depth -->
                    <div class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300">
                        <ArrowDown class="w-3.5 h-3.5 text-emerald-500 shrink-0" />
                        <span class="hidden sm:inline">Descendants:</span>
                        <div class="flex items-center gap-0.5 bg-slate-100 dark:bg-slate-800 p-0.5 rounded-lg border border-slate-300 dark:border-slate-700">
                            <button
                                @click="descendantLevels = Math.max(0, descendantLevels - 1)"
                                :disabled="descendantLevels <= 0"
                                class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 cursor-pointer"
                                title="Decrease Descendant Generations"
                            >
                                <Minus class="w-3 h-3" />
                            </button>
                            <select
                                v-model.number="descendantLevels"
                                class="bg-transparent text-xs font-bold text-slate-800 dark:text-slate-200 px-1.5 py-0.5 border-none focus:outline-hidden cursor-pointer"
                            >
                                <option :value="0">0 (Hidden)</option>
                                <option :value="1">1 Gen (Children)</option>
                                <option :value="2">2 Gens (Grandchildren)</option>
                                <option :value="3">3 Gens (Great-GC)</option>
                                <option :value="4">4 Gens (2nd Gt-GC)</option>
                                <option :value="5">5 Gens (3rd Gt-GC)</option>
                                <option :value="6">6 Gens (4th Gt-GC)</option>
                                <option :value="7">7 Gens (5th Gt-GC)</option>
                                <option :value="8">8 Gens (6th Gt-GC)</option>
                                <option :value="10">10 Generations</option>
                            </select>
                            <button
                                @click="descendantLevels = Math.min(10, descendantLevels + 1)"
                                :disabled="descendantLevels >= 10"
                                class="p-1 rounded-md hover:bg-slate-200 dark:hover:bg-slate-700 disabled:opacity-30 cursor-pointer"
                                title="Increase Descendant Generations"
                            >
                                <Plus class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Siblings Toggle Button -->
                <div class="flex items-center border-t sm:border-t-0 sm:border-l border-slate-200 dark:border-slate-800 pt-2 sm:pt-0 sm:pl-3">
                    <button
                        @click="showSiblings = !showSiblings"
                        class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer"
                        :class="[
                            showSiblings
                                ? 'bg-purple-100 dark:bg-purple-950/80 text-purple-700 dark:text-purple-300 border border-purple-300 dark:border-purple-800/50 shadow-sm'
                                : 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700'
                        ]"
                        title="Toggle siblings and their descendants"
                    >
                        <Users class="w-3.5 h-3.5" />
                        <span>Siblings</span>
                        <span
                            v-if="treeData?.siblings && treeData.siblings.length > 0"
                            class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold"
                            :class="showSiblings ? 'bg-purple-200 dark:bg-purple-900 text-purple-800 dark:text-purple-200' : 'bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400'"
                        >
                            {{ treeData.siblings.length }}
                        </span>
                    </button>
                </div>

                <!-- Search Input -->
                <div class="relative min-w-[180px] border-t sm:border-t-0 sm:border-l border-slate-200 dark:border-slate-800 pt-2 sm:pt-0 sm:pl-3">
                    <div class="relative">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" />
                        <input
                            v-model="searchInput"
                            @input="handleSearch"
                            type="text"
                            placeholder="Change focus person..."
                            class="w-full pl-8 pr-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-xs text-slate-900 dark:text-white placeholder-slate-400 focus:outline-hidden focus:ring-2 focus:ring-indigo-500 font-medium"
                        />
                    </div>

                    <!-- Search dropdown -->
                    <div
                        v-if="searchResults.length > 0"
                        class="absolute left-0 right-0 top-10 z-30 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl shadow-2xl p-1.5 space-y-1 max-h-48 overflow-y-auto"
                    >
                        <button
                            v-for="p in searchResults"
                            :key="'sr-' + p.id"
                            @click="selectSearchPerson(p)"
                            class="w-full p-2 rounded-lg text-left hover:bg-indigo-50 dark:hover:bg-slate-800 transition-colors flex items-center justify-between group cursor-pointer text-xs"
                        >
                            <span class="font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 truncate">
                                {{ p.name }}
                            </span>
                            <span class="text-[10px] text-slate-400 ml-2 shrink-0">
                                b.{{ p.birth_year || '?' }}
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Canvas Container Area -->
        <div
            ref="canvasContainerRef"
            :class="[
                'flex-1 w-full h-full relative overflow-hidden select-none touch-none bg-slate-50/50 dark:bg-slate-950/70',
                isDragging ? 'cursor-grabbing' : 'cursor-grab'
            ]"
            @pointerdown="onPointerDown"
            @pointermove="onPointerMove"
            @pointerup="onPointerUp"
            @pointercancel="onPointerUp"
            @touchstart="onTouchStart"
            @touchmove="onTouchMove"
            @touchend="onTouchEnd"
            @touchcancel="onTouchEnd"
            @wheel.passive="onWheel"
        >
            <!-- Loading Indicator -->
            <div
                v-if="loading"
                class="absolute inset-0 z-30 flex items-center justify-center bg-white/60 dark:bg-slate-950/60 backdrop-blur-xs"
            >
                <div class="flex items-center gap-3 px-5 py-3 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl">
                    <RefreshCcw class="w-5 h-5 text-indigo-600 dark:text-indigo-400 animate-spin" />
                    <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                        Loading Generations...
                    </span>
                </div>
            </div>

            <!-- Scaled and Panned Canvas Content -->
            <div
                ref="treeContentRef"
                class="absolute top-0 left-0 min-w-max p-4 sm:p-8 flex flex-col items-center gap-0 shrink-0 transition-transform duration-75 ease-out"
                :style="{
                    transform: `translate3d(${panX}px, ${panY}px, 0px) scale(${zoomLevel})`,
                    transformOrigin: '0 0',
                    willChange: 'transform'
                }"
            >
                <!-- ================= ANCESTORS SECTION (RECURSIVE MULTI-GENERATION) ================= -->
                <div
                    v-if="ancestorLevels >= 1 && (focusParents.length > 0 || spouseParents.length > 0)"
                    class="flex items-start justify-center gap-2 sm:gap-3 mb-3 sm:mb-4"
                >
                    <!-- Focus Person Ancestor Tree (Recursive Parents/Grandparents/Great-GP) -->
                    <div v-if="focusParents.length > 0" class="flex flex-col items-center">
                        <div class="flex items-start justify-center relative">
                            <!-- Father Side Ancestors Column -->
                            <div class="flex flex-col items-center relative px-1 sm:px-1.5">
                                <GedcomHeritageAncestorNode
                                    :person="focusParents[0]"
                                    :level="1"
                                    :parent-index="0"
                                    :spouse="focusParents[1]"
                                    @select-person="handlePersonSelect"
                                    @change-root="handleChangeRoot"
                                />
                            </div>

                            <!-- Marriage Bar between Focus Person's Parents (only when parents have no parents above them) -->
                            <div
                                v-if="focusParents.length > 1 && (!focusParents[0].parents || focusParents[0].parents.length === 0) && (!focusParents[1].parents || focusParents[1].parents.length === 0)"
                                class="absolute top-[250px] left-[254px] right-[254px] h-[3px] bg-slate-900 dark:bg-slate-300 z-0 flex items-center justify-between pointer-events-none"
                            >
                                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -ml-1"></span>
                                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -mr-1"></span>
                            </div>

                            <!-- Mother Side Ancestors Column -->
                            <div
                                v-if="focusParents.length > 1"
                                class="flex flex-col items-center relative px-1 sm:px-1.5"
                            >
                                <GedcomHeritageAncestorNode
                                    :person="focusParents[1]"
                                    :level="1"
                                    :parent-index="1"
                                    :spouse="focusParents[0]"
                                    @select-person="handlePersonSelect"
                                    @change-root="handleChangeRoot"
                                />
                            </div>
                        </div>
                    </div>

                    <!-- Spouse Side Ancestor Tree (Recursive Parents/Grandparents/Great-GP) -->
                    <div v-if="spouseParents.length > 0" class="flex flex-col items-center">
                        <div class="flex items-start justify-center relative">
                            <!-- Spouse Father Side Ancestors Column -->
                            <div class="flex flex-col items-center relative px-1 sm:px-1.5">
                                <GedcomHeritageAncestorNode
                                    :person="spouseParents[0]"
                                    :level="1"
                                    :parent-index="0"
                                    :spouse="spouseParents[1]"
                                    @select-person="handlePersonSelect"
                                    @change-root="handleChangeRoot"
                                />
                            </div>

                            <!-- Marriage Bar between Spouse's Parents (only when parents have no parents above them) -->
                            <div
                                v-if="spouseParents.length > 1 && (!spouseParents[0].parents || spouseParents[0].parents.length === 0) && (!spouseParents[1].parents || spouseParents[1].parents.length === 0)"
                                class="absolute top-[250px] left-[254px] right-[254px] h-[3px] bg-slate-900 dark:bg-slate-300 z-0 flex items-center justify-between pointer-events-none"
                            >
                                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -ml-1"></span>
                                <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -mr-1"></span>
                            </div>

                            <!-- Spouse Mother Side Ancestors Column -->
                            <div
                                v-if="spouseParents.length > 1"
                                class="flex flex-col items-center relative px-1 sm:px-1.5"
                            >
                                <GedcomHeritageAncestorNode
                                    :person="spouseParents[1]"
                                    :level="1"
                                    :parent-index="1"
                                    :spouse="spouseParents[0]"
                                    @select-person="handlePersonSelect"
                                    @change-root="handleChangeRoot"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ================= SIBLINGS & PRIMARY GENERATION SECTION ================= -->
                <div v-if="siblingGeneration.length > 0" class="flex flex-col items-center relative">
                    <!-- Row of Siblings & Primary Couple Branches -->
                    <div class="flex items-start justify-center">
                        <div
                            v-for="(member, sIdx) in siblingGeneration"
                            :key="member.id"
                            class="flex flex-col items-center relative px-1.5 sm:px-2.5"
                        >
                            <!-- Horizontal Distribution Bracket across Siblings (when parents exist & multiple siblings) -->
                            <div
                                v-if="focusParents.length > 0 && siblingGeneration.length > 1"
                                class="absolute top-0 h-[3px] bg-slate-900 dark:bg-slate-300 pointer-events-none"
                                :class="[
                                    sIdx === 0
                                        ? (member.is_primary && member.spouses && member.spouses.length > 0
                                            ? 'left-[calc(50%-136px)] sm:left-[calc(50%-137px)] right-0'
                                            : 'left-1/2 right-0')
                                        : '',
                                    sIdx === siblingGeneration.length - 1
                                        ? (member.is_primary && member.spouses && member.spouses.length > 0
                                            ? 'left-0 right-[calc(50%-136px)] sm:right-[calc(50%-137px)]'
                                            : 'left-0 right-1/2')
                                        : '',
                                    sIdx > 0 && sIdx < siblingGeneration.length - 1 ? 'left-0 right-0' : ''
                                ]"
                            ></div>

                            <!-- Horizontal Bar above Focused Person with Spouse -->
                            <div
                                v-if="focusParents.length > 0 && member.is_primary && member.spouses && member.spouses.length > 0"
                                class="absolute top-0 left-[calc(50%-136px)] right-[calc(50%-136px)] sm:left-[calc(50%-137px)] sm:right-[calc(50%-137px)] h-[3px] bg-slate-900 dark:bg-slate-300 pointer-events-none"
                            ></div>

                            <!-- Vertical Drop Line from Parents' Bracket into this Sibling/Primary -->
                            <div
                                v-if="focusParents.length > 0"
                                class="w-[3px] h-4 sm:h-5 bg-slate-900 dark:bg-slate-300 z-0 mb-0.5"
                            ></div>

                            <!-- Couple Row: Member + Partner (if any) -->
                            <div class="flex items-start gap-2 sm:gap-2.5 relative">
                                <!-- Person Node (Primary or Sibling) -->
                                <div class="relative flex flex-col items-center">
                                    <GedcomHeritageNode
                                        :person="member"
                                        :spouse="member.spouses?.[0]"
                                        :is-primary="member.is_primary"
                                        @select-person="handlePersonSelect"
                                        @change-root="handleChangeRoot"
                                    />
                                </div>

                                <!-- Marriage Horizontal Bar with Anchor Pins (only for primary couple) -->
                                <div
                                    v-if="member.is_primary && member.spouses && member.spouses.length > 0"
                                    class="absolute top-[250px] left-[254px] right-[254px] h-[3px] bg-slate-900 dark:bg-slate-300 z-0 flex items-center justify-between pointer-events-none"
                                >
                                    <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -ml-1"></span>
                                    <span class="w-2.5 h-2.5 bg-slate-900 dark:bg-slate-300 rounded-sm -mr-1"></span>
                                </div>

                                <!-- Partner / Spouse Node (only for primary person) -->
                                <GedcomHeritageNode
                                    v-if="member.is_primary && member.spouses && member.spouses.length > 0"
                                    :person="member.spouses[0]"
                                    :spouse="member"
                                    @select-person="handlePersonSelect"
                                    @change-root="handleChangeRoot"
                                />
                            </div>

                            <!-- Vertical Drop Line from Couple down to Children Bracket (only for primary couple) -->
                            <div
                                v-if="member.is_primary && descendantLevels >= 1 && member.children && member.children.length > 0"
                                class="w-[3px] h-4 sm:h-5 bg-slate-900 dark:bg-slate-300 z-0"
                            ></div>

                            <!-- Descendants of this Member & Spouse (only for primary couple) -->
                            <div
                                v-if="member.is_primary && descendantLevels >= 1 && member.children && member.children.length > 0"
                                class="flex flex-col items-center relative"
                            >
                                <!-- Row of Children -->
                                <div class="flex items-start justify-center">
                                    <div
                                        v-for="(child, cIdx) in member.children"
                                        :key="child.id"
                                        class="flex flex-col items-center relative px-1 sm:px-1.5 pt-4 sm:pt-5"
                                    >
                                        <!-- Horizontal Distribution Bracket across Children -->
                                        <div
                                            v-if="member.children.length > 1"
                                            class="absolute top-0 h-[3px] bg-slate-900 dark:bg-slate-300 pointer-events-none"
                                            :class="[
                                                cIdx === 0
                                                    ? (child.spouses && child.spouses.length > 0
                                                        ? 'left-[calc(50%-136px)] sm:left-[calc(50%-137px)] right-0'
                                                        : 'left-1/2 right-0')
                                                    : '',
                                                cIdx === member.children.length - 1
                                                    ? (child.spouses && child.spouses.length > 0
                                                        ? 'left-0 right-[calc(50%-136px)] sm:right-[calc(50%-137px)]'
                                                        : 'left-0 right-1/2')
                                                    : '',
                                                cIdx > 0 && cIdx < member.children.length - 1 ? 'left-0 right-0' : ''
                                            ]"
                                        ></div>

                                        <!-- Horizontal Bar above Single Child with Spouse/Partner -->
                                        <div
                                            v-if="member.children.length === 1 && child.spouses && child.spouses.length > 0"
                                            class="absolute top-0 left-[calc(50%-136px)] right-[calc(50%-136px)] sm:left-[calc(50%-137px)] sm:right-[calc(50%-137px)] h-[3px] bg-slate-900 dark:bg-slate-300 pointer-events-none"
                                        ></div>

                                        <!-- Recursive Descendant Node for Child, Spouses, Grandchildren, etc. -->
                                        <GedcomHeritageDescendantNode
                                            :person="child"
                                            :level="1"
                                            @select-person="handlePersonSelect"
                                            @change-root="handleChangeRoot"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Cinzel:wght@600;700;800;900&family=Marcellus&display=swap');

@media print {
    @page {
        size: landscape;
        margin: 0.5cm;
    }
    body {
        background: #ffffff !important;
    }
    .btn, button, nav, header, aside {
        display: none !important;
    }
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
