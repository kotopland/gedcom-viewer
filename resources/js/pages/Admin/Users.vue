<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Shield, ShieldCheck, ShieldAlert, CheckCircle, Trash2, UserCheck, UserX, UserPlus, UserMinus } from '@lucide/vue';
import type { User } from '@/types/auth';

type MiniIndividual = {
    id: string;
    name: string;
    birth_year: number | null;
};

defineOptions({
    layout: AppLayout,
});

const props = defineProps<{
    users: User[];
    individuals: MiniIndividual[];
}>();

const currentUser = usePage().props.auth.user as User;

const verifyUser = (userId: number) => {
    router.patch(`/admin/users/${userId}/verify`, {}, { preserveScroll: true });
};

const unverifyUser = (userId: number) => {
    router.patch(`/admin/users/${userId}/unverify`, {}, { preserveScroll: true });
};

const toggleSuperuser = (userId: number) => {
    router.patch(`/admin/users/${userId}/toggle-superuser`, {}, { preserveScroll: true });
};

const updateStartPerson = (userId: number, startPersonId: string | null) => {
    router.patch(`/admin/users/${userId}/start-person`, {
        start_person_id: startPersonId,
    }, { preserveScroll: true });
};

const deleteUser = (userId: number) => {
    if (confirm('Are you sure you want to delete this user?')) {
        router.delete(`/admin/users/${userId}`, { preserveScroll: true });
    }
};

const getIndividualName = (id: string | null) => {
    if (!id) return 'None (Unrestricted)';
    const cleanId = id.replace(/@/g, '');
    const found = props.individuals.find(i => i.id === cleanId || i.id === id);
    if (!found) return id;
    return `${found.name} (${found.id}${found.birth_year ? `, b. ${found.birth_year}` : ''})`;
};
</script>

<template>
    <Head title="User Management" />

    <div class="flex h-full flex-1 flex-col gap-4 p-4 md:p-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-foreground">User Management</h1>
                <p class="text-sm text-muted-foreground">Manage user accounts, verification statuses, superuser privileges, and assigned Start Person lineage constraints.</p>
            </div>
        </div>

        <div class="rounded-xl border border-sidebar-border/70 overflow-hidden bg-card">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/50 border-b border-sidebar-border/70 text-muted-foreground font-medium">
                        <tr>
                            <th class="py-3 px-4">User</th>
                            <th class="py-3 px-4">Role</th>
                            <th class="py-3 px-4">Status</th>
                            <th class="py-3 px-4">Assigned Start Person</th>
                            <th class="py-3 px-4">Joined</th>
                            <th class="py-3 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-sidebar-border/50">
                        <tr v-for="user in users" :key="user.id" class="hover:bg-muted/30 transition-colors">
                            <td class="py-3 px-4">
                                <div class="font-medium text-foreground">{{ user.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ user.email }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span v-if="user.is_superuser" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-500/10 text-purple-600 dark:text-purple-400">
                                    <ShieldCheck class="w-3.5 h-3.5" />
                                    Superuser
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-slate-500/10 text-slate-600 dark:text-slate-400">
                                    <Shield class="w-3.5 h-3.5" />
                                    Normal User
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span v-if="user.is_superuser || user.is_verified" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                                    <CheckCircle class="w-3.5 h-3.5" />
                                    Verified
                                </span>
                                <span v-else class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-600 dark:text-amber-400">
                                    <ShieldAlert class="w-3.5 h-3.5" />
                                    Pending Verification
                                </span>
                            </td>

                            <!-- Start Person Assignment Selection -->
                            <td class="py-3 px-4 min-w-[220px]">
                                <div v-if="user.is_superuser" class="text-xs text-muted-foreground italic">
                                    Full Tree Access (Superuser)
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <select
                                        :value="user.start_person_id || ''"
                                        class="h-8 w-full rounded-md border border-input bg-background px-2 py-1 text-xs shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        @change="e => updateStartPerson(user.id, (e.target as HTMLSelectElement).value || null)"
                                    >
                                        <option value="">None (Full Tree Access)</option>
                                        <option v-for="ind in individuals" :key="ind.id" :value="ind.id">
                                            {{ ind.name }} ({{ ind.id }}{{ ind.birth_year ? `, b. ${ind.birth_year}` : '' }})
                                        </option>
                                    </select>
                                </div>
                            </td>

                            <td class="py-3 px-4 text-xs text-muted-foreground">
                                {{ new Date(user.created_at).toLocaleDateString() }}
                            </td>

                            <td class="py-3 px-4 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <!-- Verify / Unverify Button -->
                                    <template v-if="!user.is_superuser">
                                        <Button
                                            v-if="!user.is_verified"
                                            size="sm"
                                            variant="outline"
                                            class="h-8 gap-1 border-emerald-500/30 text-emerald-600 hover:bg-emerald-500/10"
                                            @click="verifyUser(user.id)"
                                        >
                                            <UserCheck class="w-3.5 h-3.5" />
                                            Verify
                                        </Button>
                                        <Button
                                            v-else-if="user.id !== currentUser.id"
                                            size="sm"
                                            variant="outline"
                                            class="h-8 gap-1 border-amber-500/30 text-amber-600 hover:bg-amber-500/10"
                                            @click="unverifyUser(user.id)"
                                        >
                                            <UserX class="w-3.5 h-3.5" />
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
                                        {{ user.is_superuser ? 'Demote' : 'Make Superuser' }}
                                    </Button>

                                    <!-- Delete Button -->
                                    <Button
                                        v-if="user.id !== currentUser.id"
                                        size="icon"
                                        variant="ghost"
                                        class="h-8 w-8 text-destructive hover:bg-destructive/10"
                                        @click="deleteUser(user.id)"
                                    >
                                        <Trash2 class="w-4 h-4" />
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
