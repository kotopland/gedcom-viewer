<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { RefreshCw, FolderArchive, Users, ShieldCheck, CheckCircle2, AlertCircle, UploadCloud, FileText, Sparkles, Download, Terminal, ChevronDown, ChevronUp, Copy, Check } from '@lucide/vue';

defineOptions({
    layout: AppLayout,
});

const isReimporting = ref(false);
const isUploading = ref(false);
const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const isUploadingFaces = ref(false);
const selectedFacesFile = ref<File | null>(null);
const facesFileInputRef = ref<HTMLInputElement | null>(null);
const showFacesInstructions = ref(false);
const copySuccess = ref(false);

const statusMessage = ref<string | null>(null);
const errorMessage = ref<string | null>(null);

const onFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        selectedFile.value = target.files[0];
    } else {
        selectedFile.value = null;
    }
};

const onFacesFileChange = (e: Event) => {
    const target = e.target as HTMLInputElement;
    if (target.files && target.files.length > 0) {
        selectedFacesFile.value = target.files[0];
    } else {
        selectedFacesFile.value = null;
    }
};

const uploadGedcomFile = async () => {
    if (!selectedFile.value) {
        errorMessage.value = 'Please select a .ged or .gedcom file to upload.';
        return;
    }

    isUploading.value = true;
    statusMessage.value = null;
    errorMessage.value = null;

    try {
        const formData = new FormData();
        formData.append('file', selectedFile.value);

        const res = await fetch('/api/gedcom/upload', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });

        if (res.status === 413) {
            errorMessage.value = 'The uploaded file exceeds the server max upload limit (413 Content Too Large). Please increase upload_max_filesize / post_max_size in php.ini and client_max_body_size in Nginx.';
            return;
        }

        const contentType = res.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            errorMessage.value = `Server error (${res.status} ${res.statusText}). The file may be exceeding server upload limits.`;
            return;
        }

        const json = await res.json();
        if (res.ok) {
            statusMessage.value = json.message || 'GEDCOM file uploaded and parsed successfully.';
            selectedFile.value = null;
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
        } else {
            errorMessage.value = json.message || json.error || 'Failed to upload GEDCOM file.';
        }
    } catch (e: any) {
        console.error('GEDCOM upload failed:', e);
        errorMessage.value = e.message || 'An unexpected error occurred during file upload.';
    } finally {
        isUploading.value = false;
    }
};

const uploadFacesFile = async () => {
    if (!selectedFacesFile.value) {
        errorMessage.value = 'Please select a faces.json file to upload.';
        return;
    }

    isUploadingFaces.value = true;
    statusMessage.value = null;
    errorMessage.value = null;

    try {
        const formData = new FormData();
        formData.append('file', selectedFacesFile.value);

        const res = await fetch('/api/gedcom/upload-faces', {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        });

        const json = await res.json();
        if (res.ok) {
            statusMessage.value = json.message || 'Face tags uploaded and applied successfully!';
            selectedFacesFile.value = null;
            if (facesFileInputRef.value) {
                facesFileInputRef.value.value = '';
            }
        } else {
            errorMessage.value = json.message || json.error || 'Failed to upload faces.json file.';
        }
    } catch (e: any) {
        console.error('Faces upload failed:', e);
        errorMessage.value = e.message || 'An unexpected error occurred during faces upload.';
    } finally {
        isUploadingFaces.value = false;
    }
};

const copyCommand = (cmd: string) => {
    navigator.clipboard.writeText(cmd);
    copySuccess.value = true;
    setTimeout(() => {
        copySuccess.value = false;
    }, 2000);
};

const reimportArchive = async () => {
    if (!confirm('Re-importing will wipe the current family tree data and re-import the active ZIP archive (including media files, gedcom.ged, and faces.json). Continue?')) {
        return;
    }
    isReimporting.value = true;
    statusMessage.value = null;
    errorMessage.value = null;

    try {
        const res = await fetch('/api/gedcom/reimport', { method: 'POST' });
        const json = await res.json();
        if (res.ok) {
            statusMessage.value = json.message || 'GEDCOM archive re-imported successfully!';
        } else {
            errorMessage.value = json.error || 'Failed to re-import GEDCOM archive.';
        }
    } catch (e: any) {
        console.error('Reimport failed:', e);
        errorMessage.value = e.message || 'An unexpected error occurred during re-import.';
    } finally {
        isReimporting.value = false;
    }
};
</script>

<template>
    <Head title="Admin Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
        <!-- Header Title Banner -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 via-indigo-950 to-slate-900 p-6 rounded-2xl border border-indigo-500/20 shadow-xl text-white">
            <div class="space-y-1">
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30">
                    <ShieldCheck class="w-3.5 h-3.5" />
                    Superuser Control Panel
                </div>
                <h1 class="text-2xl font-bold tracking-tight">Superuser Dashboard</h1>
                <p class="text-xs text-slate-300">System administration, user verification, and GEDCOM data management.</p>
            </div>

            <div class="flex items-center gap-3">
                <Link
                    href="/admin/users"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-all shadow-md active:scale-95"
                >
                    <Users class="w-4 h-4" />
                    Manage Users
                </Link>
                <Link
                    href="/gedcom"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold transition-all shadow-md active:scale-95"
                >
                    <FolderArchive class="w-4 h-4" />
                    View Family Tree
                </Link>
            </div>
        </div>

        <!-- Status Alerts -->
        <div v-if="statusMessage" class="flex items-center gap-2 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-semibold">
            <CheckCircle2 class="w-4 h-4 shrink-0" />
            <span>{{ statusMessage }}</span>
        </div>

        <div v-if="errorMessage" class="flex items-center gap-2 p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-400 text-xs font-semibold">
            <AlertCircle class="w-4 h-4 shrink-0" />
            <span>{{ errorMessage }}</span>
        </div>

        <!-- Admin Actions Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-6">
            <!-- Upload Standalone .ged File (Preserve Media Cache) -->
            <div class="p-6 rounded-2xl bg-card border border-sidebar-border/70 shadow-sm space-y-4 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-500">
                        <UploadCloud class="w-5 h-5" />
                    </div>
                    <div class="space-y-1">
                        <h2 class="text-base font-bold text-foreground">Upload GEDCOM (.ged)</h2>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Upload a standalone <code class="px-1.5 py-0.5 rounded bg-muted font-mono text-[11px]">.ged</code> file to update tree records while <strong>preserving existing extracted media files</strong> in cache.
                        </p>
                    </div>

                    <div class="space-y-2 pt-2">
                        <label class="block text-xs font-medium text-muted-foreground">Select .ged / .gedcom File</label>
                        <input
                            ref="fileInputRef"
                            type="file"
                            accept=".ged,.gedcom,.txt"
                            @change="onFileChange"
                            class="block w-full text-xs text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-emerald-500/10 file:text-emerald-600 hover:file:bg-emerald-500/20 cursor-pointer border border-sidebar-border rounded-lg p-1 bg-background"
                        />
                    </div>
                </div>

                <div class="pt-3 border-t border-sidebar-border/50">
                    <Button
                        @click="uploadGedcomFile"
                        :disabled="isUploading || !selectedFile"
                        class="w-full h-10 gap-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition-all active:scale-98 disabled:opacity-50"
                    >
                        <RefreshCw v-if="isUploading" class="w-4 h-4 animate-spin" />
                        <FileText v-else class="w-4 h-4" />
                        {{ isUploading ? 'Uploading & Parsing .ged...' : 'Upload & Parse .ged' }}
                    </Button>
                </div>
            </div>

            <!-- MacFamilyTree 11 Face Tags & Crops Card -->
            <div class="p-6 rounded-2xl bg-card border border-sidebar-border/70 shadow-sm space-y-4 flex flex-col justify-between">
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500">
                            <Sparkles class="w-5 h-5" />
                        </div>
                        <a
                            href="/api/gedcom/download-face-script"
                            download="mft11_export_faces.py"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 text-xs font-semibold transition-colors"
                            title="Download Python export script"
                        >
                            <Download class="w-3.5 h-3.5" />
                            Download Script
                        </a>
                    </div>
                    <div class="space-y-1">
                        <h2 class="text-base font-bold text-foreground">MacFamilyTree Face Tags</h2>
                        <p class="text-xs text-muted-foreground leading-relaxed">
                            Export face rectangles from your MacFamilyTree 11 database package and upload <code class="px-1.5 py-0.5 rounded bg-muted font-mono text-[11px]">faces.json</code> to apply cropped portraits.
                        </p>
                    </div>

                    <button
                        type="button"
                        @click="showFacesInstructions = !showFacesInstructions"
                        class="inline-flex items-center gap-1 text-xs font-semibold text-amber-600 dark:text-amber-400 hover:underline cursor-pointer"
                    >
                        <Terminal class="w-3.5 h-3.5" />
                        <span>{{ showFacesInstructions ? 'Hide Instructions' : 'View Instructions & Guide' }}</span>
                        <ChevronUp v-if="showFacesInstructions" class="w-3.5 h-3.5" />
                        <ChevronDown v-else class="w-3.5 h-3.5" />
                    </button>

                    <div v-if="showFacesInstructions" class="p-3.5 rounded-xl bg-muted/70 border border-sidebar-border space-y-2 text-xs text-muted-foreground">
                        <div class="font-semibold text-foreground text-[11px]">
                            How to extract from MacFamilyTree 11:
                        </div>
                        <ol class="list-decimal list-inside space-y-1 text-[11px] leading-relaxed">
                            <li>Right-click your <strong class="text-foreground">.mftpkg</strong> file in Finder and choose <em class="text-foreground">"Show Package Contents"</em>.</li>
                            <li>Locate <code class="font-mono text-foreground bg-background px-1 py-0.5 rounded text-[10px]">Database.sqlite</code> inside.</li>
                            <li>Run the Python script in your Terminal:</li>
                        </ol>
                        <div class="relative bg-slate-950 text-slate-200 p-2.5 pr-8 rounded-lg font-mono text-[10px] overflow-x-auto">
                            <code>python3 scripts/mft11_export_faces.py ~/Documents/*.mftpkg/Database.sqlite faces.json</code>
                            <button
                                @click="copyCommand('python3 scripts/mft11_export_faces.py ~/Documents/*.mftpkg/Database.sqlite faces.json')"
                                class="absolute top-1.5 right-1.5 p-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors"
                                title="Copy command"
                            >
                                <Check v-if="copySuccess" class="w-3.5 h-3.5 text-emerald-400" />
                                <Copy v-else class="w-3.5 h-3.5" />
                            </button>
                        </div>
                        <p class="text-[10px] text-muted-foreground">
                            Upload the generated <code class="font-mono">faces.json</code> below.
                        </p>
                    </div>

                    <div class="space-y-2 pt-1">
                        <label class="block text-xs font-medium text-muted-foreground">Select faces.json File</label>
                        <input
                            ref="facesFileInputRef"
                            type="file"
                            accept=".json"
                            @change="onFacesFileChange"
                            class="block w-full text-xs text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-500/10 file:text-amber-600 hover:file:bg-amber-500/20 cursor-pointer border border-sidebar-border rounded-lg p-1 bg-background"
                        />
                    </div>
                </div>

                <div class="pt-3 border-t border-sidebar-border/50">
                    <Button
                        @click="uploadFacesFile"
                        :disabled="isUploadingFaces || !selectedFacesFile"
                        class="w-full h-10 gap-2 bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs shadow-md transition-all active:scale-98 disabled:opacity-50"
                    >
                        <RefreshCw v-if="isUploadingFaces" class="w-4 h-4 animate-spin" />
                        <Sparkles v-else class="w-4 h-4" />
                        {{ isUploadingFaces ? 'Uploading & Applying Faces...' : 'Upload & Apply Faces' }}
                    </Button>
                </div>
            </div>

            <!-- GEDCOM ZIP Re-import Admin Card -->
            <div class="p-6 rounded-2xl bg-card border border-sidebar-border/70 shadow-sm space-y-4 flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-500">
                        <FolderArchive class="w-5 h-5" />
                    </div>
                    <h2 class="text-base font-bold text-foreground">ZIP Archive Re-import</h2>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Wipe current family tree data and re-import active ZIP archive containing media files, <code class="px-1.5 py-0.5 rounded bg-muted font-mono text-[11px]">gedcom.ged</code>, and <code class="px-1.5 py-0.5 rounded bg-muted font-mono text-[11px]">faces.json</code>.
                    </p>
                </div>

                <div class="pt-2 border-t border-sidebar-border/50">
                    <Button
                        @click="reimportArchive"
                        :disabled="isReimporting"
                        class="w-full h-10 gap-2 bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md transition-all active:scale-98"
                    >
                        <RefreshCw class="w-4 h-4" :class="{ 'animate-spin': isReimporting }" />
                        {{ isReimporting ? 'Re-importing ZIP Archive...' : 'Re-import ZIP Archive' }}
                    </Button>
                </div>
            </div>

            <!-- User Verification Quick Card -->
            <div class="p-6 rounded-2xl bg-card border border-sidebar-border/70 shadow-sm space-y-4 flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="w-10 h-10 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-500">
                        <Users class="w-5 h-5" />
                    </div>
                    <h2 class="text-base font-bold text-foreground">User Management & Lineage</h2>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Review newly registered accounts, verify pending normal users, configure superuser privileges, and set Start Person lineage restrictions for verified users.
                    </p>
                </div>

                <div class="pt-2 border-t border-sidebar-border/50">
                    <Link
                        href="/admin/users"
                        class="inline-flex items-center justify-center w-full h-10 gap-2 rounded-md bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-md transition-all active:scale-98"
                    >
                        <Users class="w-4 h-4" />
                        Open User Management
                    </Link>
                </div>
            </div>
        </div>
    </div>
</template>
