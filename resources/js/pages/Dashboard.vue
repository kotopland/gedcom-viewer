<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { RefreshCw, FolderArchive, Users, ShieldCheck, CheckCircle2, AlertCircle, UploadCloud, FileText } from '@lucide/vue';

defineOptions({
    layout: AppLayout,
});

const isReimporting = ref(false);
const isUploading = ref(false);
const selectedFile = ref<File | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);
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

const reimportArchive = async () => {
    if (!confirm('Re-importing will wipe current extracted media and re-parse the active ZIP archive in storage/app/private. Continue?')) {
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
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
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

            <!-- GEDCOM ZIP Re-import Admin Card -->
            <div class="p-6 rounded-2xl bg-card border border-sidebar-border/70 shadow-sm space-y-4 flex flex-col justify-between">
                <div class="space-y-2">
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-500">
                        <FolderArchive class="w-5 h-5" />
                    </div>
                    <h2 class="text-base font-bold text-foreground">ZIP Archive Re-import</h2>
                    <p class="text-xs text-muted-foreground leading-relaxed">
                        Re-extract active ZIP archives in <code class="px-1.5 py-0.5 rounded bg-muted font-mono text-[11px]">storage/app/private</code>, <strong>clear old media cache</strong>, and re-parse genealogical records.
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
