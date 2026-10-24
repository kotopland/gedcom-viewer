<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import {
    CheckCircle,
    Shield,
    ShieldAlert,
    ShieldCheck,
    Trash2,
    UserCheck,
    UserX,
} from '@lucide/vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { User } from '@/types/auth';

type MiniIndividual = {
    id: string;
    name: string;
    birth_year: number | null;
};

defineOptions({
    layout: AppLayout,
});

defineProps<{
    users: User[];
    individuals: MiniIndividual[];
}>();

const currentUser = usePage().props.auth.user as User;

const verifyUser = (userId: number) => {
    router.patch(`/admin/users/${userId}/verify`, {}, { preserveScroll: true });
};

const unverifyUser = (userId: number) => {
    router.patch(
        `/admin/users/${userId}/unverify`,
        {},
        { preserveScroll: true },
    );
};

const toggleSuperuser = (userId: number) => {
    router.patch(
        `/admin/users/${userId}/toggle-superuser`,
        {},
        { preserveScroll: true },
    );
};

const updateStartPerson = (userId: number, startPersonId: string | null) => {
    router.patch(
        `/admin/users/${userId}/start-person`,
        {
            start_person_id: startPersonId,
        },
        { preserveScroll: true },
    );
};

const deleteUser = (userId: number) => {
    if (confirm('Are you sure you want to delete this user?')) {
        router.delete(`/admin/users/${userId}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="User Management" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">
                    User Management
                </h1>
                <p class="text-sm text-muted-foreground">
                    Manage user accounts, verification statuses, superuser
                    privileges, and assigned Start Person lineage constraints.
                </p>
            </div>
        </div>

        <div
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card"
        >
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead
                        class="border-b border-sidebar-border/70 bg-muted/50 font-medium text-muted-foreground"
                    >
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Role</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Assigned Start Person</th>
                            <th class="px-4 py-3">Joined</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/50">
                        <tr
                            v-for="user in users"
                            :key="user.id"
                            class="transition-colors hover:bg-muted/30"
                        >
                            <td class="px-4 py-3">
                                <div class="font-medium text-foreground">
                                    {{ user.name }}
                                </div>
                                <div class="text-xs text-muted-foreground">
                                    {{ user.email }}
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="user.is_superuser"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-purple-500/10 px-2.5 py-0.5 text-xs font-medium text-purple-600 dark:text-purple-400"
                                >
                                    <ShieldCheck class="h-3.5 w-3.5" />
                                    Superuser
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full bg-slate-500/10 px-2.5 py-0.5 text-xs font-medium text-slate-600 dark:text-slate-400"
                                >
                                    <Shield class="h-3.5 w-3.5" />
                                    Normal User
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="user.is_superuser || user.is_verified"
                                    class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400"
                                >
                                    <CheckCircle class="h-3.5 w-3.5" />
                                    Verified
                                </span>
                                <span
                                    v-else
                                    class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/10 px-2.5 py-0.5 text-xs font-medium text-amber-600 dark:text-amber-400"
                                >
                                    <ShieldAlert class="h-3.5 w-3.5" />
                                    Pending Verification
                                </span>
                            </td>

                            <!-- Start Person Assignment Selection -->
                            <td class="min-w-[220px] px-4 py-3">
                                <div
                                    v-if="user.is_superuser"
                                    class="text-xs text-muted-foreground italic"
                                >
                                    Full Tree Access (Superuser)
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <select
                                        :value="user.start_person_id || ''"
                                        class="h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-xs shadow-sm transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
                                        @change="
                                            (e) =>
                                                updateStartPerson(
                                                    user.id,
                                                    (
                                                        e.target as HTMLSelectElement
                                                    ).value || null,
                                                )
                                        "
                                    >
                                        <option value="">
                                            None (Full Tree Access)
                                        </option>
                                        <option
                                            v-for="ind in individuals"
                                            :key="ind.id"
                                            :value="ind.id"
                                        >
                                            {{ ind.name }} ({{ ind.id
                                            }}{{
                                                ind.birth_year
                                                    ? `, b. ${ind.birth_year}`
                                                    : ''
                                            }})
                                        </option>
                                    </select>
                                </div>
                            </td>

                            <td class="px-4 py-3 text-xs text-muted-foreground">
                                {{
                                    new Date(
                                        user.created_at,
                                    ).toLocaleDateString()
                                }}
                            </td>

                            <td class="px-4 py-3 text-right">
                                <div
                                    class="flex items-center justify-end gap-2"
                                >
                                    <!-- Verify / Unverify Button -->
                                    <template v-if="!user.is_superuser">
                                        <Button
                                            v-if="!user.is_verified"
                                            size="sm"
                                            variant="outline"
                                            class="h-8 gap-1 border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/10"
                                            @click="verifyUser(user.id)"
                                        >
                                            <UserCheck class="h-3.5 w-3.5" />
                                            Verify
                                        </Button>
                                        <Button
                                            v-else-if="
                                                user.id !== currentUser.id
                                            "
                                            size="sm"
                                            variant="outline"
                                            class="h-8 gap-1 border-amber-500/30 text-amber-600 hover:bg-amber-500/10"
                                            @click="unverifyUser(user.id)"
                                        >
                                            <UserX class="h-3.5 w-3.5" />
                                            Unverify
                                        </Button>
                                    </template>

                                    <!-- Toggle Superuser Button -->
                                    <Button
                                        v-if="user.id !== currentUser.id"
                                        size="sm"
                                        variant="ghost"
                                        class="h-8 text-xs text-muted-foreground hover:text-foreground"
                                        @click="toggleSuperuser(user.id)"
                                    >
                                        {{
                                            user.is_superuser
                                                ? 'Demote'
                                                : 'Make Superuser'
                                        }}
                                    </Button>

                                    <!-- Delete Button -->
                                    <Button
                                        v-if="user.id !== currentUser.id"
                                        size="icon"
                                        variant="ghost"
                                        class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                        @click="deleteUser(user.id)"
                                    >
                                        <Trash2 class="h-4 w-4" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>
