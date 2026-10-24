<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    ZoomIn,
    ZoomOut,
    Maximize2,
    RefreshCcw,
    SlidersHorizontal,
    Expand,
    Shrink,
    ChevronRight,
    ChevronLeft,
    Search,
    Plus,
    Minus,
    ArrowUp,
    ArrowDown,
    Users,
    Download,
    Image,
    ImageOff,
    ChevronDown,
} from '@lucide/vue';
import {
    ref,
    computed,
    watch,
    onMounted,
    onUnmounted,
    nextTick,
    provide,
} from 'vue';
import GedcomHeritageAncestorNode from './GedcomHeritageAncestorNode.vue';
import GedcomHeritageDescendantNode from './GedcomHeritageDescendantNode.vue';
import GedcomHeritageNode from './GedcomHeritageNode.vue';

const props = defineProps<{
    rootPersonId: string | null;
}>();

const emit = defineEmits<{
    (e: 'select-person', id: string): void;
    (e: 'change-root', id: string): void;
}>();

const page = usePage();
const isVerified = computed(
    () =>
        !!(
            page.props.auth?.user?.is_verified ||
            page.props.auth?.user?.is_superuser
        ),
);
const exportPdfLoading = ref(false);

// Discrete PDF export and portrait disabling state
const showPdfExportMenu = ref(false);
const includePdfPortraits = ref(
    typeof window !== 'undefined' &&
        sessionStorage.getItem('gedcom_heritage_pdf_portraits') !== null
        ? sessionStorage.getItem('gedcom_heritage_pdf_portraits') === 'true'
        : true,
);
const excludedPortraitIds = ref<Set<string>>(new Set());
const isPdfExporting = ref(false);
const pdfMenuRef = ref<HTMLElement | null>(null);
const pdfCollapsedMenuRef = ref<HTMLElement | null>(null);

watch(includePdfPortraits, (val) => {
    if (typeof window !== 'undefined') {
        sessionStorage.setItem('gedcom_heritage_pdf_portraits', String(val));
    }
});

const clearAllExcluded = () => {
    excludedPortraitIds.value = new Set();
};

provide('heritagePortraitState', {
    includePdfPortraits,
    excludedPortraitIds,
    isPdfExporting,
    isExportingPdf: () => isPdfExporting.value,
    toggleExcludePortrait: (id: string) => {
        const next = new Set(excludedPortraitIds.value);

        if (next.has(id)) {
            next.delete(id);
        } else {
            next.add(id);
        }

        excludedPortraitIds.value = next;
    },
    isPortraitExcluded: (id: string) => {
        return excludedPortraitIds.value.has(id);
    },
    isPortraitHiddenForPerson: (id: string) => {
        if (isPdfExporting.value && !includePdfPortraits.value) {
            return true;
        }

        return excludedPortraitIds.value.has(id);
    },
    clearAllExcluded,
});

const loading = ref(false);
const treeData = ref<any>(null);
const focusId = ref<string | null>(props.rootPersonId);
const ancestorLevels = ref(
    typeof window !== 'undefined' &&
        sessionStorage.getItem('gedcom_heritage_ancestor_levels')
        ? parseInt(
              sessionStorage.getItem('gedcom_heritage_ancestor_levels')!,
              10,
          ) || 3
        : 3,
);
const descendantLevels = ref(
    typeof window !== 'undefined' &&
        sessionStorage.getItem('gedcom_heritage_descendant_levels')
        ? parseInt(
              sessionStorage.getItem('gedcom_heritage_descendant_levels')!,
              10,
          ) || 3
        : 3,
);
const showSiblings = ref(
    typeof window !== 'undefined' &&
        sessionStorage.getItem('gedcom_heritage_show_siblings') !== null
        ? sessionStorage.getItem('gedcom_heritage_show_siblings') === 'true'
        : false,
);

watch(ancestorLevels, (val) => {
    if (typeof window !== 'undefined') {
        sessionStorage.setItem('gedcom_heritage_ancestor_levels', String(val));
    }
});
watch(descendantLevels, (val) => {
    if (typeof window !== 'undefined') {
        sessionStorage.setItem(
            'gedcom_heritage_descendant_levels',
            String(val),
        );
    }
});
watch(showSiblings, (val) => {
    if (typeof window !== 'undefined') {
        sessionStorage.setItem('gedcom_heritage_show_siblings', String(val));
    }
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
    if (e.button !== 0 && e.pointerType === 'mouse') {
        return;
    }

    const target = e.target as HTMLElement;

    if (target.closest('button, select, input, a, [data-clickable]')) {
        return;
    }

    isPointerDown.value = true;
    dragMoved = false;
    startX = e.clientX;
    startY = e.clientY;
    startPanX = panX.value;
    startPanY = panY.value;
};

const onPointerMove = (e: PointerEvent) => {
    if (!isPointerDown.value) {
        return;
    }

    if (initialPinchDistance !== null) {
        return;
    }

    const dx = e.clientX - startX;
    const dy = e.clientY - startY;

    if (!dragMoved && (Math.abs(dx) > 4 || Math.abs(dy) > 4)) {
        dragMoved = true;
        isDragging.value = true;

        try {
            (e.currentTarget as HTMLElement)?.setPointerCapture?.(e.pointerId);
        } catch {}
    }

    if (dragMoved) {
        panX.value = startPanX + dx;
        panY.value = startPanY + dy;
    }
};

const onPointerUp = (e?: PointerEvent) => {
    if (e && e.currentTarget) {
        try {
            (e.currentTarget as HTMLElement)?.releasePointerCapture?.(
                e.pointerId,
            );
        } catch {}
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

        if (target.closest('button, select, input, a, [data-clickable]')) {
            return;
        }

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

        initialPinchDistance = Math.hypot(
            t1.clientX - t2.clientX,
            t1.clientY - t2.clientY,
        );
        initialPinchZoom = zoomLevel.value;
        initialPinchFocalX = focalX;
        initialPinchFocalY = focalY;
        initialPinchPanX = panX.value;
        initialPinchPanY = panY.value;
    }
};

const onTouchMove = (e: TouchEvent) => {
    if (
        e.touches.length === 1 &&
        isPointerDown.value &&
        initialPinchDistance === null
    ) {
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
    } else if (
        e.touches.length === 2 &&
        initialPinchDistance &&
        canvasContainerRef.value
    ) {
        e.preventDefault();
        const rect = canvasContainerRef.value.getBoundingClientRect();
        const t1 = e.touches[0];
        const t2 = e.touches[1];

        const currentDistance = Math.hypot(
            t1.clientX - t2.clientX,
            t1.clientY - t2.clientY,
        );
        const currentFocalX = (t1.clientX + t2.clientX) / 2 - rect.left;
        const currentFocalY = (t1.clientY + t2.clientY) / 2 - rect.top;

        const scaleRatio = currentDistance / initialPinchDistance;
        const newZoom = Math.min(
            2.5,
            Math.max(0.2, initialPinchZoom * scaleRatio),
        );

        const contentX =
            (initialPinchFocalX - initialPinchPanX) / initialPinchZoom;
        const contentY =
            (initialPinchFocalY - initialPinchPanY) / initialPinchZoom;

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

        if (!canvasContainerRef.value) {
            return;
        }

        const rect = canvasContainerRef.value.getBoundingClientRect();
        const cursorX = e.clientX - rect.left;
        const cursorY = e.clientY - rect.top;

        const zoomFactor = e.deltaY < 0 ? 1.15 : 0.85;
        const newZoom = Math.min(
            2.5,
            Math.max(0.2, Math.round(zoomLevel.value * zoomFactor * 100) / 100),
        );

        const contentX = (cursorX - panX.value) / zoomLevel.value;
        const contentY = (cursorY - panY.value) / zoomLevel.value;

        panX.value = Math.round(cursorX - contentX * newZoom);
        panY.value = Math.round(cursorY - contentY * newZoom);
        zoomLevel.value = newZoom;
    }
};

const applyZoomFromCenter = (newZoom: number) => {
    if (!canvasContainerRef.value) {
        return;
    }

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
    const newZoom = Math.min(
        2.5,
        Math.round((zoomLevel.value + 0.15) * 100) / 100,
    );
    applyZoomFromCenter(newZoom);
};

const zoomOut = () => {
    const newZoom = Math.max(
        0.2,
        Math.round((zoomLevel.value - 0.15) * 100) / 100,
    );
    applyZoomFromCenter(newZoom);
};

const resetZoom = () => {
    const maxGen = Math.max(ancestorLevels.value, descendantLevels.value);

    if (maxGen >= 4) {
        zoomLevel.value = 0.55;
    } else if (maxGen === 3) {
        zoomLevel.value = 0.7;
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
    if (dragMoved || isDragging.value) {
        return;
    }

    emit('select-person', id);
};

const handleChangeRoot = (id: string) => {
    if (dragMoved || isDragging.value) {
        return;
    }

    focusId.value = id;
    emit('change-root', id);
};

const fetchTreeData = async (id: string) => {
    if (!id) {
        return;
    }

    loading.value = true;

    try {
        const res = await fetch(
            `/api/gedcom/tree/${id}?ancestors=${ancestorLevels.value}&descendants=${descendantLevels.value}`,
        );

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
        const res = await fetch(
            `/api/gedcom/search?q=${encodeURIComponent(searchInput.value)}&limit=8`,
        );

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
    if (!primary.value) {
        return [];
    }

    if (!showSiblings.value) {
        return [
            {
                ...primary.value,
                is_primary: true,
                children: childrenList.value,
                spouses: primarySpouse.value ? [primarySpouse.value] : [],
            },
        ];
    }

    const sibs = (treeData.value?.siblings || []).map((s: any) => ({
        ...s,
        is_primary: false,
        spouses: [],
        children: [],
    }));

    const primaryEntry = {
        ...primary.value,
        is_primary: true,
        children: childrenList.value,
        spouses: primarySpouse.value ? [primarySpouse.value] : [],
    };

    const all = [...sibs, primaryEntry];

    // Sort chronologically by birth year
    all.sort((a, b) => {
        const yA = a.birth_year ?? 9999;
        const yB = b.birth_year ?? 9999;

        if (yA !== yB) {
            return yA - yB;
        }

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
    { immediate: true },
);

const handleGlobalClick = (e: MouseEvent) => {
    if (!showPdfExportMenu.value) {
        return;
    }

    const target = e.target as Node;

    if (
        (pdfMenuRef.value && pdfMenuRef.value.contains(target)) ||
        (pdfCollapsedMenuRef.value &&
            pdfCollapsedMenuRef.value.contains(target))
    ) {
        return;
    }

    showPdfExportMenu.value = false;
};

onMounted(() => {
    checkMobile();
    window.addEventListener('resize', checkMobile);
    window.addEventListener('click', handleGlobalClick);
});

// Helper to load external scripts dynamically on-demand
const loadExternalScript = (src: string, globalName: string): Promise<any> => {
    if ((window as any)[globalName]) {
        return Promise.resolve((window as any)[globalName]);
    }

    return new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${src}"]`);

        if (existing) {
            existing.addEventListener('load', () =>
                resolve((window as any)[globalName]),
            );
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

const exportToPdf = async (withPortraits?: boolean) => {
    if (!isVerified.value || exportPdfLoading.value) {
        return;
    }

    if (!treeContentRef.value) {
        alert('Heritage tree is not available to export.');

        return;
    }

    showPdfExportMenu.value = false;
    const previousGlobalSetting = includePdfPortraits.value;

    if (typeof withPortraits === 'boolean') {
        includePdfPortraits.value = withPortraits;
    }

    exportPdfLoading.value = true;
    isPdfExporting.value = true;

    try {
        // Load html-to-image and jspdf dynamically from CDN
        await Promise.all([
            loadExternalScript(
                'https://cdnjs.cloudflare.com/ajax/libs/html-to-image/1.11.11/html-to-image.min.js',
                'htmlToImage',
            ),
            loadExternalScript(
                'https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js',
                'jspdf',
            ),
        ]);

        const htmlToImage = (window as any).htmlToImage;
        const { jsPDF } = (window as any).jspdf;

        if (!htmlToImage || !jsPDF) {
            throw new Error('PDF export libraries could not be initialized.');
        }

        // Wait for Vue to update DOM with isPdfExporting=true (transform: none, no hover buttons, portrait visibility updated)
        await nextTick();
        await new Promise((r) => setTimeout(r, 100));

        const el = treeContentRef.value;
        // Pure white background for crisp paper printing without any gray tint
        const bgColor = '#ffffff';

        // Measure natural full dimensions of the unscaled tree
        const contentWidth = Math.ceil(
            Math.max(el.scrollWidth, el.offsetWidth, el.clientWidth),
        );
        const contentHeight = Math.ceil(
            Math.max(el.scrollHeight, el.offsetHeight, el.clientHeight),
        );

        const dataUrl = await htmlToImage.toJpeg(el, {
            backgroundColor: bgColor,
            quality: 0.96,
            pixelRatio: 2,
            cacheBust: true,
            width: contentWidth,
            height: contentHeight,
            style: {
                transform: 'none',
                transformOrigin: '0 0',
                left: '0',
                top: '0',
                position: 'static',
                margin: '0',
                padding: '0',
                backgroundColor: '#ffffff',
            },
        });

        // Initialize standard ISO A3 PDF document (landscape or portrait based on chart aspect ratio)
        const orientation =
            contentWidth >= contentHeight ? 'landscape' : 'portrait';
        const pdf = new jsPDF({
            orientation,
            unit: 'mm',
            format: 'a3',
        });

        const pageWidth = pdf.internal.pageSize.getWidth();
        const pageHeight = pdf.internal.pageSize.getHeight();

        // 0% margin to stretch edge-to-edge on constraining dimension
        const marginPercent = 0;
        const maxContentWidth = pageWidth * (1 - 2 * marginPercent);
        const maxContentHeight = pageHeight * (1 - 2 * marginPercent);

        // Stretch chart to fill A3 page while preserving proportions
        const scale = Math.min(
            maxContentWidth / contentWidth,
            maxContentHeight / contentHeight,
        );

        const renderWidth = contentWidth * scale;
        const renderHeight = contentHeight * scale;

        // Center on the A3 page
        const posX = (pageWidth - renderWidth) / 2;
        const posY = (pageHeight - renderHeight) / 2;

        pdf.addImage(
            dataUrl,
            'JPEG',
            posX,
            posY,
            renderWidth,
            renderHeight,
            undefined,
            'FAST',
        );

        const personName = primary.value?.name
            ? primary.value.name.replace(/[^a-zA-Z0-9_-]/g, '_')
            : 'heritage_chart';
        const suffix = !includePdfPortraits.value ? '_no_portraits' : '';
        pdf.save(`${personName}_heritage_chart_A3${suffix}.pdf`);
    } catch (err: any) {
        console.error('PDF export failed:', err);
        alert('Failed to generate PDF: ' + (err?.message || 'Unknown error'));
    } finally {
        isPdfExporting.value = false;

        if (typeof withPortraits === 'boolean') {
            includePdfPortraits.value = previousGlobalSetting;
        }

        await nextTick();
        exportPdfLoading.value = false;
    }
};

onUnmounted(() => {
    window.removeEventListener('click', handleGlobalClick);
    window.removeEventListener('resize', checkMobile);
    document.body.style.overflow = '';
});
</script>

<template>
    <div
        :class="[
            'flex flex-col transition-all duration-300',
            isFullscreen
                ? 'fixed inset-0 z-50 h-screen min-h-screen w-screen rounded-none border-none bg-slate-50 p-0 shadow-none dark:bg-slate-950'
                : 'relative h-[calc(100dvh-5.5rem-env(safe-area-inset-top,0px))] min-h-[500px] flex-1 overflow-hidden rounded-3xl border border-slate-200/90 bg-white/95 shadow-2xl sm:h-[84vh] sm:min-h-[750px] dark:border-slate-800 dark:bg-slate-900',
        ]"
    >
        <!-- Floating Floating Controls Header -->
        <div
            :class="[
                'absolute z-20 max-w-[calc(100vw-2rem)] transition-all duration-300',
                isFullscreen
                    ? 'top-4 left-4 sm:top-6 sm:left-6'
                    : 'top-3 left-3 sm:top-4 sm:left-4',
            ]"
        >
            <!-- Collapsed Controls Pill -->
            <div
                v-if="isControlsCollapsed"
                class="flex items-center gap-1.5 rounded-2xl border border-slate-200 bg-white/95 p-1.5 shadow-xl backdrop-blur-md sm:gap-2 dark:border-slate-800 dark:bg-slate-900/95"
            >
                <button
                    @click="isControlsCollapsed = false"
                    class="dark:hover:bg-slate-750 group inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-slate-100 px-2.5 py-1.5 text-xs font-bold text-slate-800 shadow-xs transition-all hover:bg-slate-200 sm:gap-2 sm:px-3 dark:bg-slate-800 dark:text-slate-200"
                    title="Open Heritage Chart Settings"
                >
                    <SlidersHorizontal
                        class="h-3.5 w-3.5 text-indigo-600 transition-transform group-hover:scale-110 dark:text-indigo-400"
                    />
                    <span>Heritage Chart</span>
                    <span
                        class="rounded-md bg-slate-200/80 px-1 text-[10px] font-medium text-slate-600 dark:bg-slate-700/60 dark:text-slate-400"
                    >
                        {{ Math.round(zoomLevel * 100) }}% ({{
                            ancestorLevels
                        }}A / {{ descendantLevels }}D{{
                            showSiblings && treeData?.siblings?.length
                                ? ' + Siblings'
                                : ''
                        }})
                    </span>
                    <ChevronRight
                        class="h-3.5 w-3.5 text-slate-400 transition-transform group-hover:translate-x-0.5"
                    />
                </button>

                <button
                    @click="zoomIn"
                    class="dark:hover:bg-slate-750 cursor-pointer rounded-xl bg-slate-100 p-1.5 text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                    title="Zoom In"
                >
                    <ZoomIn class="h-3.5 w-3.5" />
                </button>

                <button
                    @click="zoomOut"
                    class="dark:hover:bg-slate-750 cursor-pointer rounded-xl bg-slate-100 p-1.5 text-slate-700 transition-colors hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300"
                    title="Zoom Out"
                >
                    <ZoomOut class="h-3.5 w-3.5" />
                </button>

                <button
                    @click="toggleFullscreen"
                    class="inline-flex cursor-pointer items-center gap-1 rounded-xl bg-slate-900 px-2.5 py-1.5 text-xs font-bold text-white shadow-xs transition-all hover:bg-indigo-600 active:scale-95 dark:bg-slate-800"
                    :title="isFullscreen ? 'Exit Full Screen' : 'Full Screen'"
                >
                    <Shrink v-if="isFullscreen" class="h-3.5 w-3.5" />
                    <Expand v-else class="h-3.5 w-3.5" />
                </button>

                <!-- PDF Export Split Button (Collapsed) -->
                <div
                    ref="pdfCollapsedMenuRef"
                    class="relative inline-flex items-center"
                >
                    <button
                        v-if="isVerified"
                        @click="exportToPdf()"
                        :disabled="exportPdfLoading"
                        class="cursor-pointer rounded-l-xl bg-red-600/90 p-1.5 text-white transition-colors hover:bg-red-600 disabled:opacity-50"
                        :title="
                            includePdfPortraits
                                ? 'Export High-Resolution A3 PDF Chart'
                                : 'Export A3 PDF Chart (without portraits)'
                        "
                    >
                        <Download
                            v-if="!exportPdfLoading"
                            class="h-3.5 w-3.5"
                        />
                        <RefreshCcw v-else class="h-3.5 w-3.5 animate-spin" />
                    </button>
                    <button
                        v-if="isVerified"
                        @click.stop="showPdfExportMenu = !showPdfExportMenu"
                        :disabled="exportPdfLoading"
                        class="cursor-pointer rounded-r-xl border-l border-red-500/50 bg-red-700 px-1 py-1.5 text-white transition-colors hover:bg-red-800"
                        title="A3 PDF Export Options (Portraits on/off)"
                    >
                        <ChevronDown
                            class="h-3 w-3 transition-transform"
                            :class="showPdfExportMenu ? 'rotate-180' : ''"
                        />
                    </button>
                </div>
            </div>

            <!-- Expanded Controls Bar -->
            <div
                v-else
                class="flex max-w-[calc(100vw-2.5rem)] animate-in flex-col items-stretch gap-2.5 overflow-x-auto rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-2xl backdrop-blur-md duration-150 zoom-in-95 fade-in sm:flex-row sm:flex-wrap sm:items-center sm:gap-3 sm:p-2.5 dark:border-slate-800 dark:bg-slate-900/95"
            >
                <div class="flex items-center justify-between gap-2">
                    <button
                        @click="toggleFullscreen"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white shadow-md transition-all hover:bg-indigo-600 active:scale-95 dark:bg-indigo-600"
                    >
                        <Shrink v-if="isFullscreen" class="h-3.5 w-3.5" />
                        <Expand v-else class="h-3.5 w-3.5" />
                        <span>{{ isFullscreen ? 'Exit' : 'Full Screen' }}</span>
                    </button>

                    <!-- PDF Export Split Button (Expanded) -->
                    <div
                        ref="pdfMenuRef"
                        class="relative inline-flex items-center"
                    >
                        <button
                            v-if="isVerified"
                            @click="exportToPdf()"
                            :disabled="exportPdfLoading"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-l-xl bg-red-600 px-3 py-1.5 text-xs font-bold text-white shadow-md transition-all hover:bg-red-700 active:scale-95 disabled:opacity-50"
                            :title="
                                includePdfPortraits
                                    ? 'Export High-Resolution A3 PDF Chart'
                                    : 'Export A3 PDF Chart (without portraits)'
                            "
                        >
                            <Download
                                v-if="!exportPdfLoading"
                                class="h-3.5 w-3.5"
                            />
                            <RefreshCcw
                                v-else
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <span>{{
                                exportPdfLoading ? 'Exporting...' : 'PDF (A3)'
                            }}</span>
                            <span
                                v-if="!includePdfPortraits"
                                class="py-0.2 rounded border border-amber-400/40 bg-black/30 px-1 text-[9px] font-bold text-amber-200"
                                title="Portraits disabled for PDF"
                            >
                                No Photos
                            </span>
                        </button>
                        <button
                            v-if="isVerified"
                            @click.stop="showPdfExportMenu = !showPdfExportMenu"
                            :disabled="exportPdfLoading"
                            class="cursor-pointer rounded-r-xl border-l border-red-500/50 bg-red-700 px-1.5 py-1.5 text-xs text-white transition-colors hover:bg-red-800"
                            :class="showPdfExportMenu ? 'bg-red-800' : ''"
                            title="A3 PDF Export Options (Portraits on/off)"
                        >
                            <ChevronDown
                                class="h-3.5 w-3.5 transition-transform"
                                :class="showPdfExportMenu ? 'rotate-180' : ''"
                            />
                        </button>

                        <!-- Discrete PDF Options Popover Menu -->
                        <div
                            v-if="showPdfExportMenu"
                            @click.stop
                            class="absolute top-full left-0 z-50 mt-2 w-64 animate-in rounded-2xl border border-slate-200 bg-white/95 p-2 text-xs text-slate-800 shadow-2xl backdrop-blur-md duration-100 zoom-in-95 fade-in dark:border-slate-800 dark:bg-slate-900/95 dark:text-slate-200"
                        >
                            <div
                                class="flex items-center justify-between px-2 py-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase dark:text-slate-500"
                            >
                                <span>A3 PDF Export Options</span>
                                <span
                                    v-if="!includePdfPortraits"
                                    class="font-semibold text-amber-500 normal-case dark:text-amber-400"
                                    >Portraits Off</span
                                >
                            </div>

                            <!-- Toggle: Include portrait pictures in PDF export -->
                            <label
                                class="flex cursor-pointer items-start gap-2.5 rounded-xl p-2 transition-colors select-none hover:bg-slate-100 dark:hover:bg-slate-800/70"
                            >
                                <input
                                    type="checkbox"
                                    v-model="includePdfPortraits"
                                    class="mt-0.5 cursor-pointer rounded border-slate-300 text-red-600 focus:ring-red-500 dark:border-slate-700"
                                />
                                <div class="flex-1">
                                    <div
                                        class="flex items-center gap-1.5 font-semibold text-slate-900 dark:text-slate-100"
                                    >
                                        <Image
                                            v-if="includePdfPortraits"
                                            class="h-3.5 w-3.5 text-emerald-500"
                                        />
                                        <ImageOff
                                            v-else
                                            class="h-3.5 w-3.5 text-amber-500"
                                        />
                                        <span>Include portraits</span>
                                    </div>
                                    <p
                                        class="mt-0.5 text-[11px] leading-snug text-slate-500 dark:text-slate-400"
                                    >
                                        {{
                                            includePdfPortraits
                                                ? 'Export with portrait photos included'
                                                : 'Disable portrait photos in PDF export (clean silhouette)'
                                        }}
                                    </p>
                                </div>
                            </label>

                            <!-- Individual exclusions summary if any -->
                            <div
                                v-if="excludedPortraitIds.size > 0"
                                class="mt-1 flex items-center justify-between rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-[11px] text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-300"
                            >
                                <span
                                    >{{ excludedPortraitIds.size }} individual
                                    photo(s) disabled</span
                                >
                                <button
                                    @click="clearAllExcluded"
                                    class="cursor-pointer text-[10px] font-bold underline hover:text-amber-950 dark:hover:text-amber-100"
                                >
                                    Reset
                                </button>
                            </div>

                            <div
                                class="my-1.5 border-t border-slate-200 dark:border-slate-800"
                            ></div>

                            <!-- Direct Actions -->
                            <div class="space-y-1">
                                <button
                                    @click="exportToPdf(true)"
                                    class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2.5 py-1.5 text-left font-medium transition-colors hover:bg-slate-100 dark:hover:bg-slate-800"
                                >
                                    <Image
                                        class="h-3.5 w-3.5 shrink-0 text-emerald-500"
                                    />
                                    <span class="flex-1 truncate"
                                        >Export A3 with portraits</span
                                    >
                                </button>
                                <button
                                    @click="exportToPdf(false)"
                                    class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2.5 py-1.5 text-left font-medium text-amber-700 transition-colors hover:bg-amber-50 dark:text-amber-300 dark:hover:bg-amber-950/40"
                                >
                                    <ImageOff class="h-3.5 w-3.5 shrink-0" />
                                    <span class="flex-1 truncate"
                                        >Export A3 without portraits</span
                                    >
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Zoom Controls -->
                    <div
                        class="flex items-center gap-1 rounded-xl bg-slate-100 p-1 dark:bg-slate-800/80"
                    >
                        <button
                            @click="zoomIn"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-700 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"
                            title="Zoom In"
                        >
                            <ZoomIn class="h-3.5 w-3.5" />
                        </button>
                        <button
                            @click="zoomOut"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-700 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"
                            title="Zoom Out"
                        >
                            <ZoomOut class="h-3.5 w-3.5" />
                        </button>
                        <button
                            @click="resetZoom"
                            class="cursor-pointer rounded-lg p-1.5 text-slate-700 hover:bg-slate-200 dark:text-slate-300 dark:hover:bg-slate-700"
                            title="Fit Screen"
                        >
                            <Maximize2 class="h-3.5 w-3.5" />
                        </button>
                        <span
                            class="px-1 text-[11px] font-bold text-slate-700 dark:text-slate-300"
                        >
                            {{ Math.round(zoomLevel * 100) }}%
                        </span>
                    </div>

                    <button
                        @click="isControlsCollapsed = true"
                        class="cursor-pointer rounded-xl p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-white"
                        title="Collapse"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                </div>

                <!-- Generation Depth Selectors (Up to 10 Generations) -->
                <div
                    class="flex items-center gap-2.5 border-t border-slate-200 pt-2 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-3 dark:border-slate-800"
                >
                    <!-- Ancestor Depth -->
                    <div
                        class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        <ArrowUp class="h-3.5 w-3.5 shrink-0 text-indigo-500" />
                        <span class="hidden sm:inline">Ancestors:</span>
                        <div
                            class="flex items-center gap-0.5 rounded-lg border border-slate-300 bg-slate-100 p-0.5 dark:border-slate-700 dark:bg-slate-800"
                        >
                            <button
                                @click="
                                    ancestorLevels = Math.max(
                                        0,
                                        ancestorLevels - 1,
                                    )
                                "
                                :disabled="ancestorLevels <= 0"
                                class="cursor-pointer rounded-md p-1 hover:bg-slate-200 disabled:opacity-30 dark:hover:bg-slate-700"
                                title="Decrease Ancestor Generations"
                            >
                                <Minus class="h-3 w-3" />
                            </button>
                            <select
                                v-model.number="ancestorLevels"
                                class="cursor-pointer border-none bg-transparent px-1.5 py-0.5 text-xs font-bold text-slate-800 focus:outline-hidden dark:text-slate-200"
                            >
                                <option :value="0">0 (Hidden)</option>
                                <option :value="1">1 Gen (Parents)</option>
                                <option :value="2">
                                    2 Gens (Grandparents)
                                </option>
                                <option :value="3">3 Gens (Great-GP)</option>
                                <option :value="4">4 Gens (2nd Gt-GP)</option>
                                <option :value="5">5 Gens (3rd Gt-GP)</option>
                                <option :value="6">6 Gens (4th Gt-GP)</option>
                                <option :value="7">7 Gens (5th Gt-GP)</option>
                                <option :value="8">8 Gens (6th Gt-GP)</option>
                                <option :value="10">10 Generations</option>
                            </select>
                            <button
                                @click="
                                    ancestorLevels = Math.min(
                                        10,
                                        ancestorLevels + 1,
                                    )
                                "
                                :disabled="ancestorLevels >= 10"
                                class="cursor-pointer rounded-md p-1 hover:bg-slate-200 disabled:opacity-30 dark:hover:bg-slate-700"
                                title="Increase Ancestor Generations"
                            >
                                <Plus class="h-3 w-3" />
                            </button>
                        </div>
                    </div>

                    <!-- Descendant Depth -->
                    <div
                        class="flex items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-300"
                    >
                        <ArrowDown
                            class="h-3.5 w-3.5 shrink-0 text-emerald-500"
                        />
                        <span class="hidden sm:inline">Descendants:</span>
                        <div
                            class="flex items-center gap-0.5 rounded-lg border border-slate-300 bg-slate-100 p-0.5 dark:border-slate-700 dark:bg-slate-800"
                        >
                            <button
                                @click="
                                    descendantLevels = Math.max(
                                        0,
                                        descendantLevels - 1,
                                    )
                                "
                                :disabled="descendantLevels <= 0"
                                class="cursor-pointer rounded-md p-1 hover:bg-slate-200 disabled:opacity-30 dark:hover:bg-slate-700"
                                title="Decrease Descendant Generations"
                            >
                                <Minus class="h-3 w-3" />
                            </button>
                            <select
                                v-model.number="descendantLevels"
                                class="cursor-pointer border-none bg-transparent px-1.5 py-0.5 text-xs font-bold text-slate-800 focus:outline-hidden dark:text-slate-200"
                            >
                                <option :value="0">0 (Hidden)</option>
                                <option :value="1">1 Gen (Children)</option>
                                <option :value="2">
                                    2 Gens (Grandchildren)
                                </option>
                                <option :value="3">3 Gens (Great-GC)</option>
                                <option :value="4">4 Gens (2nd Gt-GC)</option>
                                <option :value="5">5 Gens (3rd Gt-GC)</option>
                                <option :value="6">6 Gens (4th Gt-GC)</option>
                                <option :value="7">7 Gens (5th Gt-GC)</option>
                                <option :value="8">8 Gens (6th Gt-GC)</option>
                                <option :value="10">10 Generations</option>
                            </select>
                            <button
                                @click="
                                    descendantLevels = Math.min(
                                        10,
                                        descendantLevels + 1,
                                    )
                                "
                                :disabled="descendantLevels >= 10"
                                class="cursor-pointer rounded-md p-1 hover:bg-slate-200 disabled:opacity-30 dark:hover:bg-slate-700"
                                title="Increase Descendant Generations"
                            >
                                <Plus class="h-3 w-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Siblings Toggle Button -->
                <div
                    class="flex items-center border-t border-slate-200 pt-2 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-3 dark:border-slate-800"
                >
                    <button
                        @click="showSiblings = !showSiblings"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl px-2.5 py-1.5 text-xs font-bold shadow-xs transition-all"
                        :class="[
                            showSiblings
                                ? 'border border-purple-300 bg-purple-100 text-purple-700 shadow-sm dark:border-purple-800/50 dark:bg-purple-950/80 dark:text-purple-300'
                                : 'border border-slate-200 bg-slate-100 text-slate-500 hover:bg-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400 dark:hover:bg-slate-700',
                        ]"
                        title="Toggle siblings and their descendants"
                    >
                        <Users class="h-3.5 w-3.5" />
                        <span>Siblings</span>
                        <span
                            v-if="
                                treeData?.siblings &&
                                treeData.siblings.length > 0
                            "
                            class="py-0.2 rounded-full px-1.5 text-[10px] font-extrabold"
                            :class="
                                showSiblings
                                    ? 'bg-purple-200 text-purple-800 dark:bg-purple-900 dark:text-purple-200'
                                    : 'bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-400'
                            "
                        >
                            {{ treeData.siblings.length }}
                        </span>
                    </button>
                </div>

                <!-- Search Input -->
                <div
                    class="relative min-w-[180px] border-t border-slate-200 pt-2 sm:border-t-0 sm:border-l sm:pt-0 sm:pl-3 dark:border-slate-800"
                >
                    <div class="relative">
                        <Search
                            class="absolute top-2.5 left-2.5 h-3.5 w-3.5 text-slate-400"
                        />
                        <input
                            v-model="searchInput"
                            @input="handleSearch"
                            type="text"
                            placeholder="Change focus person..."
                            class="w-full rounded-xl border border-slate-300 bg-slate-100 py-1.5 pr-3 pl-8 text-xs font-medium text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-indigo-500 focus:outline-hidden dark:border-slate-700 dark:bg-slate-800 dark:text-white"
                        />
                    </div>

                    <!-- Search dropdown -->
                    <div
                        v-if="searchResults.length > 0"
                        class="absolute top-10 right-0 left-0 z-30 max-h-48 space-y-1 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                    >
                        <button
                            v-for="p in searchResults"
                            :key="'sr-' + p.id"
                            @click="selectSearchPerson(p)"
                            class="group flex w-full cursor-pointer items-center justify-between rounded-lg p-2 text-left text-xs transition-colors hover:bg-indigo-50 dark:hover:bg-slate-800"
                        >
                            <span
                                class="truncate font-bold text-slate-900 group-hover:text-indigo-600 dark:text-white"
                            >
                                {{ p.name }}
                            </span>
                            <span
                                class="ml-2 shrink-0 text-[10px] text-slate-400"
                            >
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
                'relative h-full w-full flex-1 touch-none overflow-hidden bg-slate-50/50 select-none dark:bg-slate-950/70',
                isDragging ? 'cursor-grabbing' : 'cursor-grab',
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
                class="absolute inset-0 z-30 flex items-center justify-center bg-white/60 backdrop-blur-xs dark:bg-slate-950/60"
            >
                <div
                    class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-5 py-3 shadow-2xl dark:border-slate-800 dark:bg-slate-900"
                >
                    <RefreshCcw
                        class="h-5 w-5 animate-spin text-indigo-600 dark:text-indigo-400"
                    />
                    <span
                        class="text-xs font-bold text-slate-800 dark:text-slate-200"
                    >
                        Loading Generations...
                    </span>
                </div>
            </div>

            <!-- Scaled and Panned Canvas Content -->
            <div
                ref="treeContentRef"
                class="absolute top-0 left-0 flex min-w-max shrink-0 flex-col items-center gap-0 transition-transform duration-75 ease-out"
                :class="isPdfExporting ? 'p-0' : 'p-4 sm:p-8'"
                :style="{
                    transform: isPdfExporting
                        ? 'none'
                        : `translate3d(${panX}px, ${panY}px, 0px) scale(${zoomLevel})`,
                    transformOrigin: '0 0',
                    willChange: isPdfExporting ? 'auto' : 'transform',
                    transition: isPdfExporting ? 'none' : undefined,
                }"
            >
                <!-- ================= ANCESTORS SECTION (RECURSIVE MULTI-GENERATION) ================= -->
                <div
                    v-if="
                        ancestorLevels >= 1 &&
                        (focusParents.length > 0 || spouseParents.length > 0)
                    "
                    class="mb-1.5 flex items-start justify-center gap-1 sm:mb-2 sm:gap-1.5"
                >
                    <!-- Focus Person Ancestor Tree (Recursive Parents/Grandparents/Great-GP) -->
                    <div
                        v-if="focusParents.length > 0"
                        class="flex flex-col items-center"
                    >
                        <div class="relative flex items-start justify-center">
                            <!-- Father Side Ancestors Column -->
                            <div
                                class="relative flex flex-col items-center px-0.5"
                            >
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
                                v-if="
                                    focusParents.length > 1 &&
                                    (!focusParents[0].parents ||
                                        focusParents[0].parents.length === 0) &&
                                    (!focusParents[1].parents ||
                                        focusParents[1].parents.length === 0)
                                "
                                class="pointer-events-none absolute top-[250px] left-1/2 z-0 flex h-[3px] w-10 -translate-x-1/2 items-center justify-between bg-slate-900 dark:bg-slate-300"
                            >
                                <span
                                    class="-ml-1 h-2.5 w-2.5 rounded-sm bg-slate-900 dark:bg-slate-300"
                                ></span>
                                <span
                                    class="-mr-1 h-2.5 w-2.5 rounded-sm bg-slate-900 dark:bg-slate-300"
                                ></span>
                            </div>

                            <!-- Mother Side Ancestors Column -->
                            <div
                                v-if="focusParents.length > 1"
                                class="relative flex flex-col items-center px-0.5"
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
                    <div
                        v-if="spouseParents.length > 0"
                        class="flex flex-col items-center"
                    >
                        <div class="relative flex items-start justify-center">
                            <!-- Spouse Father Side Ancestors Column -->
                            <div
                                class="relative flex flex-col items-center px-0.5"
                            >
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
                                v-if="
                                    spouseParents.length > 1 &&
                                    (!spouseParents[0].parents ||
                                        spouseParents[0].parents.length ===
                                            0) &&
                                    (!spouseParents[1].parents ||
                                        spouseParents[1].parents.length === 0)
                                "
                                class="pointer-events-none absolute top-[250px] left-1/2 z-0 flex h-[3px] w-10 -translate-x-1/2 items-center justify-between bg-slate-900 dark:bg-slate-300"
                            >
                                <span
                                    class="-ml-1 h-2.5 w-2.5 rounded-sm bg-slate-900 dark:bg-slate-300"
                                ></span>
                                <span
                                    class="-mr-1 h-2.5 w-2.5 rounded-sm bg-slate-900 dark:bg-slate-300"
                                ></span>
                            </div>

                            <!-- Spouse Mother Side Ancestors Column -->
                            <div
                                v-if="spouseParents.length > 1"
                                class="relative flex flex-col items-center px-0.5"
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
                <div
                    v-if="siblingGeneration.length > 0"
                    class="relative flex flex-col items-center"
                >
                    <!-- Row of Siblings & Primary Couple Branches -->
                    <div class="flex items-start justify-center">
                        <div
                            v-for="(member, sIdx) in siblingGeneration"
                            :key="member.id"
                            class="relative flex flex-col items-center px-1.5 sm:px-2.5"
                        >
                            <!-- Horizontal Distribution Bracket across Siblings (when parents exist & multiple siblings) -->
                            <div
                                v-if="
                                    focusParents.length > 0 &&
                                    siblingGeneration.length > 1
                                "
                                class="pointer-events-none absolute top-0 h-[3px] bg-slate-900 dark:bg-slate-300"
                                :class="[
                                    sIdx === 0
                                        ? member.is_primary &&
                                          member.spouses &&
                                          member.spouses.length > 0
                                            ? 'right-0 left-[calc(50%-134px)] sm:left-[calc(50%-135px)]'
                                            : 'right-0 left-1/2'
                                        : '',
                                    sIdx === siblingGeneration.length - 1
                                        ? member.is_primary &&
                                          member.spouses &&
                                          member.spouses.length > 0
                                            ? 'right-[calc(50%-134px)] left-0 sm:right-[calc(50%-135px)]'
                                            : 'right-1/2 left-0'
                                        : '',
                                    sIdx > 0 &&
                                    sIdx < siblingGeneration.length - 1
                                        ? 'right-0 left-0'
                                        : '',
                                ]"
                            ></div>

                            <!-- Horizontal Bar above Focused Person with Spouse -->
                            <div
                                v-if="
                                    focusParents.length > 0 &&
                                    member.is_primary &&
                                    member.spouses &&
                                    member.spouses.length > 0
                                "
                                class="pointer-events-none absolute top-0 right-[calc(50%-134px)] left-[calc(50%-134px)] h-[3px] bg-slate-900 sm:right-[calc(50%-135px)] sm:left-[calc(50%-135px)] dark:bg-slate-300"
                            ></div>

                            <!-- Vertical Drop Line from Parents' Bracket into this Sibling/Primary -->
                            <div
                                v-if="focusParents.length > 0"
                                class="z-0 mb-0.5 h-4 w-[3px] bg-slate-900 sm:h-5 dark:bg-slate-300"
                            ></div>

                            <!-- Couple Row: Member + Partner (if any) -->
                            <div
                                class="relative flex items-start gap-2 sm:gap-2.5"
                            >
                                <!-- Person Node (Primary or Sibling) -->
                                <div
                                    class="relative flex flex-col items-center"
                                >
                                    <GedcomHeritageNode
                                        :person="member"
                                        :is-primary="member.is_primary"
                                        :spouse="member.spouses?.[0]"
                                        @select-person="handlePersonSelect"
                                        @change-root="handleChangeRoot"
                                    />
                                </div>

                                <!-- Marriage Horizontal Bar with Anchor Pins (only for primary couple) -->
                                <div
                                    v-if="
                                        member.is_primary &&
                                        member.spouses &&
                                        member.spouses.length > 0
                                    "
                                    class="pointer-events-none absolute top-[250px] left-1/2 z-0 flex h-[3px] w-10 -translate-x-1/2 items-center justify-between bg-slate-900 dark:bg-slate-300"
                                >
                                    <span
                                        class="-ml-1 h-2.5 w-2.5 rounded-sm bg-slate-900 dark:bg-slate-300"
                                    ></span>
                                    <span
                                        class="-mr-1 h-2.5 w-2.5 rounded-sm bg-slate-900 dark:bg-slate-300"
                                    ></span>
                                </div>

                                <!-- Partner / Spouse Node (only for primary person) -->
                                <GedcomHeritageNode
                                    v-if="
                                        member.is_primary &&
                                        member.spouses &&
                                        member.spouses.length > 0
                                    "
                                    :person="member.spouses[0]"
                                    :spouse="member"
                                    @select-person="handlePersonSelect"
                                    @change-root="handleChangeRoot"
                                />
                            </div>

                            <!-- Vertical Drop Line from Couple down to Children Bracket (only for primary couple) -->
                            <div
                                v-if="
                                    member.is_primary &&
                                    descendantLevels >= 1 &&
                                    member.children &&
                                    member.children.length > 0
                                "
                                class="z-0 h-4 w-[3px] bg-slate-900 sm:h-5 dark:bg-slate-300"
                            ></div>

                            <!-- Descendants of this Member & Spouse (only for primary couple) -->
                            <div
                                v-if="
                                    member.is_primary &&
                                    descendantLevels >= 1 &&
                                    member.children &&
                                    member.children.length > 0
                                "
                                class="relative flex flex-col items-center"
                            >
                                <!-- Row of Children -->
                                <div class="flex items-start justify-center">
                                    <div
                                        v-for="(child, cIdx) in member.children"
                                        :key="child.id"
                                        class="relative flex flex-col items-center px-1 pt-4 sm:px-1.5 sm:pt-5"
                                    >
                                        <!-- Horizontal Distribution Bracket across Children -->
                                        <div
                                            v-if="member.children.length > 1"
                                            class="pointer-events-none absolute top-0 h-[3px] bg-slate-900 dark:bg-slate-300"
                                            :class="[
                                                cIdx === 0
                                                    ? child.spouses &&
                                                      child.spouses.length > 0
                                                        ? 'right-0 left-[calc(50%-134px)] sm:left-[calc(50%-135px)]'
                                                        : 'right-0 left-1/2'
                                                    : '',
                                                cIdx ===
                                                member.children.length - 1
                                                    ? child.spouses &&
                                                      child.spouses.length > 0
                                                        ? 'right-[calc(50%-134px)] left-0 sm:right-[calc(50%-135px)]'
                                                        : 'right-1/2 left-0'
                                                    : '',
                                                cIdx > 0 &&
                                                cIdx <
                                                    member.children.length - 1
                                                    ? 'right-0 left-0'
                                                    : '',
                                            ]"
                                        ></div>

                                        <!-- Horizontal Bar above Single Child with Spouse/Partner -->
                                        <div
                                            v-if="
                                                member.children.length === 1 &&
                                                child.spouses &&
                                                child.spouses.length > 0
                                            "
                                            class="pointer-events-none absolute top-0 right-[calc(50%-134px)] left-[calc(50%-134px)] h-[3px] bg-slate-900 sm:right-[calc(50%-135px)] sm:left-[calc(50%-135px)] dark:bg-slate-300"
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
    .btn,
    button,
    nav,
    header,
    aside {
        display: none !important;
    }
    * {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
}
</style>
