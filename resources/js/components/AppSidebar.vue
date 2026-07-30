<script setup lang="ts">
import { computed } from 'vue';
import { usePage, Link } from '@inertiajs/vue3';
import { BookOpen, FolderGit2, LayoutGrid, Users, FolderArchive } from '@lucide/vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import type { User } from '@/types/auth';

const page = usePage();
const currentUser = computed(() => page.props.auth?.user as User | undefined);

const mainNavItems = computed<NavItem[]>(() => {
    const items: NavItem[] = [];

    if (currentUser.value?.is_superuser) {
        items.push({
            title: 'Dashboard',
            href: dashboard(),
            icon: LayoutGrid,
        });
        items.push({
            title: 'User Management',
            href: '/admin/users',
            icon: Users,
        });
    }

    items.push({
        title: 'Family Tree Archive',
        href: '/gedcom',
        icon: FolderArchive,
    });

    return items;
});

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: FolderGit2,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="currentUser?.is_superuser ? dashboard() : '/gedcom'">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
