<script setup>
import { computed, ref, watch } from "vue";
import { useForm, useHttp } from "@inertiajs/vue3";
import { BookUser, Phone } from "lucide-vue-next";
import {
    countryFor,
    countryOptions,
    defaultCountry,
} from "../../Utils/countries";
import { formatPhone } from "../../Utils/format";
import Modal from "../UI/Modal.vue";
import Button from "../UI/Button.vue";
import FormField from "../UI/FormField.vue";
import PhoneInput from "../UI/PhoneInput.vue";
import TextInput from "../UI/TextInput.vue";
import SelectInput from "../UI/SelectInput.vue";
import SearchInput from "../UI/SearchInput.vue";
import Avatar from "../UI/Avatar.vue";

const props = defineProps({
    open: { type: Boolean, default: false },
    connections: { type: Array, default: () => [] },
});

const emit = defineEmits(["close"]);

const mode = ref("number");
const country = ref(defaultCountry.iso);
const search = ref("");
const contacts = ref([]);
const searching = ref(false);

const form = useForm({
    contact_id: null,
    phone: "",
    name: "",
    channel_connection_id: null,
});
const contactRequest = useHttp({});

const dial = computed(() => countryFor(country.value).dial);

const connectionOptions = computed(() =>
    props.connections.map((connection) => ({
        value: connection.id,
        label:
            connection.status === "connected"
                ? connection.name
                : `${connection.name} (${connection.status_label})`,
    })),
);

const manyConnections = computed(() => props.connections.length > 1);

function defaultConnection() {
    const connected = props.connections.find(
        (connection) => connection.status === "connected",
    );

    return connected?.id ?? props.connections[0]?.id ?? null;
}

let searchTimeout = null;

async function loadContacts() {
    searching.value = true;

    try {
        const payload = await contactRequest.get(
            `/atendimentos/contatos?busca=${encodeURIComponent(search.value)}`,
        );

        contacts.value = payload.data;
    } catch {
        contacts.value = [];
    } finally {
        searching.value = false;
    }
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(loadContacts, 300);
});

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        mode.value = "number";
        search.value = "";
        form.reset();
        form.clearErrors();
        form.channel_connection_id = defaultConnection();
        loadContacts();
    },
);

function submitNumber() {
    form.transform((data) => ({
        ...data,
        contact_id: null,
        phone: `${dial.value}${data.phone}`,
    })).post("/atendimentos", {
        preserveScroll: true,
        onSuccess: () => emit("close"),
    });
}

function startWith(contact) {
    form.transform((data) => ({
        ...data,
        contact_id: contact.id,
        phone: null,
    })).post("/atendimentos", {
        preserveScroll: true,
        onSuccess: () => emit("close"),
    });
}
</script>

<template>
    <Modal
        :open="open"
        title="Novo Atendimento"
        size="sm"
        @close="emit('close')"
    >
        <div class="space-y-4">
            <FormField
                v-if="manyConnections"
                label="Canal"
                :error="form.errors.channel_connection_id"
                hint="O número da conta que vai aparecer para o contato."
            >
                <SelectInput
                    v-model="form.channel_connection_id"
                    :options="connectionOptions"
                />
            </FormField>

            <div class="flex gap-1 rounded-control bg-surface-muted p-1">
                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-control px-3 py-1.5 text-xs font-medium transition"
                    :class="
                        mode === 'number'
                            ? 'bg-surface text-content'
                            : 'text-content-muted hover:text-content'
                    "
                    @click="mode = 'number'"
                >
                    <Phone :size="14" />
                    Número
                </button>

                <button
                    type="button"
                    class="flex flex-1 items-center justify-center gap-1.5 rounded-control px-3 py-1.5 text-xs font-medium transition"
                    :class="
                        mode === 'contacts'
                            ? 'bg-surface text-content'
                            : 'text-content-muted hover:text-content'
                    "
                    @click="mode = 'contacts'"
                >
                    <BookUser :size="14" />
                    Agenda
                </button>
            </div>

            <form
                v-if="mode === 'number'"
                class="space-y-3"
                @submit.prevent="submitNumber"
            >
                <div class="flex gap-2">
                    <div class="w-36 shrink-0">
                        <FormField label="País">
                            <SelectInput
                                v-model="country"
                                :options="countryOptions"
                            />
                        </FormField>
                    </div>

                    <div class="flex-1">
                        <FormField
                            label="Número"
                            :error="form.errors.phone"
                            required
                        >
                            <PhoneInput
                                v-model="form.phone"
                                placeholder="(11) 98888-7777"
                                :invalid="Boolean(form.errors.phone)"
                            />
                        </FormField>
                    </div>
                </div>

                <FormField
                    label="Nome"
                    :error="form.errors.name"
                    hint="Opcional, será usado até o contato se identificar."
                >
                    <TextInput v-model="form.name" placeholder="Fulano" />
                </FormField>

                <p class="text-xs text-content-subtle">
                    Enviaremos para +{{ dial }}{{ form.phone || "..." }}
                </p>

                <div class="flex justify-end gap-2 pt-1">
                    <Button
                        variant="secondary"
                        type="button"
                        @click="emit('close')"
                        >Cancelar</Button
                    >
                    <Button
                        type="submit"
                        :loading="form.processing"
                        :disabled="!form.phone"
                        >Iniciar</Button
                    >
                </div>
            </form>

            <div v-else class="space-y-3">
                <SearchInput
                    v-model="search"
                    placeholder="Nome, apelido ou telefone"
                />

                <p
                    v-if="searching"
                    class="py-4 text-center text-xs text-content-subtle"
                >
                    Buscando...
                </p>

                <p
                    v-else-if="!contacts.length"
                    class="py-4 text-center text-xs text-content-subtle"
                >
                    Nenhum contato com telefone encontrado.
                </p>

                <div
                    v-else
                    class="max-h-72 space-y-1 overflow-y-auto scrollbar-thin"
                >
                    <button
                        v-for="contact in contacts"
                        :key="contact.id"
                        type="button"
                        class="flex w-full items-center gap-2.5 rounded-control px-2 py-2 text-left transition hover:bg-surface-hover"
                        :disabled="form.processing"
                        @click="startWith(contact)"
                    >
                        <Avatar
                            :name="contact.name"
                            :src="contact.avatar_url"
                            :size="32"
                        />

                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm text-content">
                                {{ contact.name }}
                            </p>
                            <p class="truncate text-xs text-content-muted">
                                {{ formatPhone(contact.phone) }}
                            </p>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </Modal>
</template>
