<script setup lang="ts">
import { Moon, Sun } from '@lucide/vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { resolvedAppearance, updateAppearance } = useAppearance();

const toggleTheme = () => {
    updateAppearance(resolvedAppearance.value === 'dark' ? 'light' : 'dark');
};
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <button
            @click="toggleTheme"
            class="cursor-pointer rounded-xl border border-sidebar-border/60 bg-sidebar-accent/60 p-2 text-sidebar-foreground transition-colors hover:bg-sidebar-accent"
            :title="
                resolvedAppearance === 'dark'
                    ? 'Switch to Light Mode'
                    : 'Switch to Dark Mode'
            "
        >
            <Sun
                v-if="resolvedAppearance === 'dark'"
                class="h-4 w-4 text-amber-400"
            />
            <Moon v-else class="h-4 w-4 text-indigo-600 dark:text-indigo-400" />
        </button>
    </header>
</template>
