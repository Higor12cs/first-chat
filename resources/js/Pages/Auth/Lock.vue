<script setup>
import { Head, useForm, usePage, router } from "@inertiajs/vue3";
import { computed } from "vue";
import Avatar from "../../Components/UI/Avatar.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import Button from "../../Components/UI/Button.vue";

const page = usePage();
const user = computed(() => page.props.auth?.user);

const form = useForm({ password: "" });

function submit() {
    form.put("/bloqueio", { onFinish: () => form.reset("password") });
}
</script>

<template>
    <Head title="Tela Bloqueada" />

    <form class="space-y-4" @submit.prevent="submit">
        <div class="flex flex-col items-center gap-2 text-center">
            <Avatar :name="user?.name" :src="user?.avatar_url" :size="52" />
            <div class="space-y-0.5">
                <p class="text-sm font-semibold text-content">
                    {{ user?.name }}
                </p>
                <p class="text-xs text-content-muted">
                    Sua sessão está bloqueada.
                </p>
            </div>
        </div>

        <FormField label="Senha" :error="form.errors.password" required>
            <TextInput
                v-model="form.password"
                type="password"
                placeholder="••••••••"
                :invalid="Boolean(form.errors.password)"
            />
        </FormField>

        <Button
            type="submit"
            class="w-full justify-center"
            :loading="form.processing"
            >Desbloquear</Button
        >

        <button
            type="button"
            class="w-full text-center text-xs text-content-subtle transition hover:text-content"
            @click="router.post('/sair')"
        >
            Sair da Conta
        </button>
    </form>
</template>
