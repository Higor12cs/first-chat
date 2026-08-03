<script setup>
import { watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import Modal from "../UI/Modal.vue";
import Button from "../UI/Button.vue";
import FormField from "../UI/FormField.vue";
import TextInput from "../UI/TextInput.vue";
import PhoneInput from "../UI/PhoneInput.vue";
import TextArea from "../UI/TextArea.vue";
import Toggle from "../UI/Toggle.vue";

const props = defineProps({
    open: { type: Boolean, default: false },
    contact: { type: Object, default: null },
    tags: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "saved"]);

const form = useForm({
    name: "",
    nickname: "",
    phone: "",
    email: "",
    document: "",
    notes: "",
    is_blocked: false,
    tags: [],
});

watch(
    () => [props.open, props.contact?.id],
    () => {
        if (!props.open || !props.contact) {
            return;
        }

        form.defaults({
            name: props.contact.legal_name ?? props.contact.name ?? "",
            nickname: props.contact.nickname ?? "",
            phone: props.contact.phone ?? "",
            email: props.contact.email ?? "",
            document: props.contact.document ?? "",
            notes: props.contact.notes ?? "",
            is_blocked: props.contact.is_blocked ?? false,
            tags: props.contact.tags?.map((tag) => tag.id) ?? [],
        });

        form.reset();
        form.clearErrors();
    },
    { immediate: true },
);

function toggleTag(id) {
    form.tags = form.tags.includes(id)
        ? form.tags.filter((item) => item !== id)
        : [...form.tags, id];
}

function submit() {
    form.put(`/contatos/${props.contact.id}`, {
        preserveScroll: true,
        preserveState: true,
        only: ["selected", "sections", "flash"],
        onSuccess: () => {
            emit("saved");
            emit("close");
        },
    });
}
</script>

<template>
    <Modal
        :open="open"
        title="Editar Contato"
        description="O apelido substitui o nome que o WhatsApp informa."
        @close="emit('close')"
    >
        <form
            id="contact-inline-form"
            class="space-y-4"
            @submit.prevent="submit"
        >
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Nome" :error="form.errors.name" required>
                    <TextInput
                        v-model="form.name"
                        :invalid="Boolean(form.errors.name)"
                    />
                </FormField>

                <FormField
                    label="Apelido"
                    :error="form.errors.nickname"
                    hint="Como o time chama este contato."
                >
                    <TextInput v-model="form.nickname" />
                </FormField>

                <FormField label="Telefone" :error="form.errors.phone">
                    <PhoneInput
                        v-model="form.phone"
                        with-country
                        placeholder="+55 (11) 98888-7777"
                        :invalid="Boolean(form.errors.phone)"
                    />
                </FormField>

                <FormField label="Email" :error="form.errors.email">
                    <TextInput v-model="form.email" type="email" />
                </FormField>
            </div>

            <FormField label="Documento" :error="form.errors.document">
                <TextInput v-model="form.document" />
            </FormField>

            <FormField label="Observações" :error="form.errors.notes">
                <TextArea v-model="form.notes" rows="3" />
            </FormField>

            <FormField v-if="tags.length" group label="Tags">
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="item in tags"
                        :key="item.id"
                        type="button"
                        class="rounded-full border px-2 py-0.5 text-xs transition"
                        :class="
                            form.tags.includes(item.id)
                                ? 'border-primary bg-primary-soft text-primary'
                                : 'border-border text-content-muted hover:bg-surface-hover'
                        "
                        @click="toggleTag(item.id)"
                    >
                        {{ item.name }}
                    </button>
                </div>
            </FormField>

            <Toggle
                v-model="form.is_blocked"
                label="Bloquear contato"
                description="Mensagens recebidas deste contato não abrem novos atendimentos."
            />
        </form>

        <template #footer>
            <Button variant="secondary" @click="emit('close')">Cancelar</Button>
            <Button
                type="submit"
                form="contact-inline-form"
                :loading="form.processing"
                >Salvar</Button
            >
        </template>
    </Modal>
</template>
