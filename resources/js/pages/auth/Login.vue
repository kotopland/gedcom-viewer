<script setup lang="ts">
import { ref } from 'vue';
import { Form, Head, useForm } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import PasskeyVerify from '@/components/PasskeyVerify.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';
import { Mail, Key, ShieldCheck, Sparkles } from '@lucide/vue';

defineOptions({
    layout: {
        title: 'Log in to your account',
        description: 'Magic Link login for family members & Password login for Superusers.',
    },
});

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const mode = ref<'magic' | 'password'>('magic');

const magicForm = useForm({
    email: '',
    remember: true,
});

const sendMagicLink = () => {
    magicForm.post('/login/magic-link', {
        onSuccess: () => {
            magicForm.reset('email');
        },
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="space-y-6">
        <!-- Status Flash Banner -->
        <div
            v-if="status"
            class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-xs font-semibold text-center animate-in fade-in"
        >
            {{ status }}
        </div>

        <!-- Mode Toggle Pill -->
        <div class="flex items-center p-1 bg-slate-100 dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 text-xs font-bold">
            <button
                type="button"
                @click="mode = 'magic'"
                class="flex-1 py-2 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer"
                :class="mode === 'magic' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
            >
                <Mail class="w-4 h-4" />
                <span>Magic Link Login</span>
            </button>
            <button
                type="button"
                @click="mode = 'password'"
                class="flex-1 py-2 rounded-xl transition-all flex items-center justify-center gap-2 cursor-pointer"
                :class="mode === 'password' ? 'bg-indigo-600 text-white shadow-md' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
            >
                <Key class="w-4 h-4" />
                <span>Superuser Password</span>
            </button>
        </div>

        <!-- Magic Link Form (Default for Normal Users) -->
        <form
            v-if="mode === 'magic'"
            @submit.prevent="sendMagicLink"
            class="space-y-4 animate-in fade-in duration-150"
        >
            <div class="space-y-1.5">
                <Label for="magic-email">Email address</Label>
                <Input
                    id="magic-email"
                    v-model="magicForm.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="email"
                    placeholder="email@example.com"
                />
                <InputError :message="magicForm.errors.email" />
            </div>

            <div class="flex items-center justify-between py-1">
                <Label for="magic-remember" class="flex items-center space-x-2.5 text-xs font-semibold cursor-pointer text-slate-700 dark:text-slate-300">
                    <Checkbox id="magic-remember" v-model:checked="magicForm.remember" />
                    <span>Remember me</span>
                </Label>
            </div>

            <p class="text-[11px] text-slate-500 dark:text-slate-400 leading-relaxed">
                Enter your email address and we'll send you a secure, passwordless magic login link to access the family tree archive.
            </p>

            <Button
                type="submit"
                class="w-full font-bold cursor-pointer"
                :disabled="magicForm.processing"
            >
                <Spinner v-if="magicForm.processing" />
                <Mail class="w-4 h-4 mr-2" />
                Send Magic Link
            </Button>
        </form>

        <!-- Superuser Password Form -->
        <div v-else class="space-y-6 animate-in fade-in duration-150">
            <PasskeyVerify />

            <Form
                v-bind="store.form()"
                :reset-on-success="['password']"
                v-slot="{ errors, processing }"
                class="flex flex-col gap-6"
            >
                <div class="grid gap-6">
                    <div class="grid gap-2">
                        <Label for="email">Superuser Email</Label>
                        <Input
                            id="email"
                            type="email"
                            name="email"
                            required
                            autofocus
                            :tabindex="1"
                            autocomplete="email"
                            placeholder="admin@example.com"
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-center justify-between">
                            <Label for="password">Password</Label>
                            <TextLink
                                v-if="canResetPassword"
                                :href="request()"
                                class="text-sm"
                                :tabindex="5"
                            >
                                Forgot password?
                            </TextLink>
                        </div>
                        <PasswordInput
                            id="password"
                            name="password"
                            required
                            :tabindex="2"
                            autocomplete="current-password"
                            placeholder="Password"
                        />
                        <InputError :message="errors.password" />
                    </div>

                    <div class="flex items-center justify-between">
                        <Label for="remember" class="flex items-center space-x-3">
                            <Checkbox id="remember" name="remember" :tabindex="3" />
                            <span>Remember me</span>
                        </Label>
                    </div>

                    <Button
                        type="submit"
                        class="mt-2 w-full"
                        :tabindex="4"
                        :disabled="processing"
                    >
                        <Spinner v-if="processing" />
                        <ShieldCheck class="w-4 h-4 mr-2" />
                        Log in as Superuser
                    </Button>
                </div>
            </Form>
        </div>

        <div class="text-center text-sm text-slate-500 dark:text-slate-400 border-t border-slate-200 dark:border-slate-800 pt-4">
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
        </div>
    </div>
</template>
