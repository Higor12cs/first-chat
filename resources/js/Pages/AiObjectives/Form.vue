<script setup>
import { ArrowLeft, Save } from "lucide-vue-next";
import { computed } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import SelectInput from "../../Components/UI/SelectInput.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import Badge from "../../Components/UI/Badge.vue";

const props = defineProps({
    objective: { type: Object, default: null },
    providers: { type: Array, default: () => [] },
    tools: { type: Array, default: () => [] },
    queues: { type: Array, default: () => [] },
});

const form = useForm({
    name: props.objective?.name ?? "",
    description: props.objective?.description ?? "",
    provider: props.objective?.provider ?? props.providers[0]?.value ?? "",
    model: props.objective?.model ?? props.providers[0]?.models?.[0] ?? "",
    temperature: props.objective?.temperature ?? 0.7,
    max_tokens: props.objective?.max_tokens ?? 1024,
    system_prompt: props.objective?.system_prompt ?? "",
    tools: props.objective?.tools ?? [],
    cost_limit_cents: props.objective?.cost_limit_cents ?? null,
    max_turns: props.objective?.max_turns ?? 20,
    handoff_service_queue_id: props.objective?.handoff_service_queue_id ?? null,
    closing_condition: props.objective?.closing_condition ?? "",
    is_active: props.objective?.is_active ?? true,
});

const providerOptions = computed(() =>
    props.providers.map((provider) => ({
        value: provider.value,
        label: provider.configured ? provider.label : `${provider.label} (sem chave)`,
    })),
);

const selectedProvider = computed(() => props.providers.find((provider) => provider.value === form.provider));

const temperatureIsRisky = computed(() => form.temperature > 1.2);

const modelOptions = computed(() => (selectedProvider.value?.models ?? []).map((model) => ({ value: model, label: model })));

const queueOptions = computed(() => props.queues.map((queue) => ({ value: queue.id, label: queue.name })));

const costLimitDollars = computed({
    get: () => (form.cost_limit_cents === null ? "" : form.cost_limit_cents / 100),
    set: (value) => {
        form.cost_limit_cents = value === "" || value === null ? null : Math.round(Number(value) * 100);
    },
});

function toggleTool(name) {
    form.tools = form.tools.includes(name) ? form.tools.filter((item) => item !== name) : [...form.tools, name];
}

function changeProvider(value) {
    form.provider = value;
    form.model = props.providers.find((provider) => provider.value === value)?.models?.[0] ?? "";
}

function submit() {
    if (props.objective) {
        form.put(`/objetivos-de-ia/${props.objective.id}`);
    } else {
        form.post("/objetivos-de-ia");
    }
}
</script>

<template>
    <Head :title="objective ? 'Editar Objetivo de IA' : 'Novo Objetivo de IA'" />

    <form class="space-y-5 p-4 lg:p-6" @submit.prevent="submit">
        <PageHeader
            :title="objective ? 'Editar Objetivo de IA' : 'Novo Objetivo de IA'"
            subtitle="A IA não tem comportamento fixo: tudo abaixo define como ela atende."
        >
            <template #actions>
                <Button variant="secondary" :icon="ArrowLeft" href="/objetivos-de-ia">Voltar</Button>
                <Button type="submit" :icon="Save" :loading="form.processing">Salvar</Button>
            </template>
        </PageHeader>

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <Card title="Identificação" description="Como este objetivo aparece para a equipe.">
                    <div class="space-y-4">
                        <FormField label="Nome" :error="form.errors.name" required>
                            <TextInput v-model="form.name" :invalid="Boolean(form.errors.name)" />
                        </FormField>

                        <FormField label="Descrição" :error="form.errors.description">
                            <TextArea v-model="form.description" rows="2" />
                        </FormField>
                    </div>
                </Card>

                <Card title="Instruções" description="O prompt que define o papel e os limites da IA.">
                    <FormField :error="form.errors.system_prompt" required>
                        <TextArea
                            v-model="form.system_prompt"
                            rows="12"
                            placeholder="Você é o atendente virtual da empresa. Seu objetivo é..."
                            :invalid="Boolean(form.errors.system_prompt)"
                        />
                    </FormField>
                </Card>

                <Card title="Ferramentas" description="Ações que a IA pode executar durante o atendimento.">
                    <div class="grid gap-2 sm:grid-cols-2">
                        <button
                            v-for="tool in tools"
                            :key="tool.value"
                            type="button"
                            class="space-y-1 rounded-control border px-3 py-2 text-left transition"
                            :class="
                                form.tools.includes(tool.value)
                                    ? 'border-primary bg-primary-soft'
                                    : 'border-border hover:bg-surface-hover'
                            "
                            @click="toggleTool(tool.value)"
                        >
                            <span class="block text-sm font-medium text-content">{{ tool.label }}</span>
                            <span class="block text-xs text-content-muted">{{ tool.description }}</span>
                        </button>
                    </div>
                </Card>
            </div>

            <div class="space-y-5">
                <Card title="Modelo" description="Provedor e parâmetros de geração.">
                    <div class="space-y-4">
                        <FormField label="Provedor" :error="form.errors.provider" required>
                            <SelectInput
                                :model-value="form.provider"
                                :options="providerOptions"
                                @update:model-value="changeProvider"
                            />
                        </FormField>

                        <FormField label="Modelo" :error="form.errors.model" required>
                            <SelectInput v-model="form.model" :options="modelOptions" />
                        </FormField>

                        <FormField label="Temperatura" :error="form.errors.temperature" hint="0 responde sempre igual, 1 varia o jeito de dizer.">
                            <input
                                v-model.number="form.temperature"
                                type="range"
                                min="0"
                                max="2"
                                step="0.1"
                                class="w-full accent-primary"
                            />
                            <span class="text-xs text-content-muted">{{ form.temperature }}</span>
                            <p v-if="temperatureIsRisky" class="mt-1 text-xs text-warning">
                                Acima de 1.2 a resposta começa coerente e degenera no meio,
                                misturando idiomas e caracteres soltos.
                            </p>
                        </FormField>

                        <FormField label="Máximo de tokens" :error="form.errors.max_tokens">
                            <TextInput v-model="form.max_tokens" type="number" />
                        </FormField>
                    </div>
                </Card>

                <Card title="Limites" description="Barreiras de custo e de duração do atendimento.">
                    <div class="space-y-4">
                        <FormField label="Limite de custo (US$)" :error="form.errors.cost_limit_cents" hint="Ao atingir, a IA para de responder.">
                            <TextInput v-model="costLimitDollars" type="number" placeholder="Sem limite" />
                        </FormField>

                        <FormField label="Máximo de turnos" :error="form.errors.max_turns">
                            <TextInput v-model="form.max_turns" type="number" />
                        </FormField>

                        <FormField label="Setor de transbordo" :error="form.errors.handoff_service_queue_id" hint="Para onde a conversa vai quando a IA encerra.">
                            <SelectInput v-model="form.handoff_service_queue_id" :options="queueOptions" placeholder="Sem transbordo" />
                        </FormField>

                        <FormField label="Condição de encerramento" :error="form.errors.closing_condition">
                            <TextArea v-model="form.closing_condition" rows="3" placeholder="Quando o cliente informar nome, e-mail e interesse." />
                        </FormField>

                        <Toggle v-model="form.is_active" label="Objetivo ativo" />
                    </div>
                </Card>

                <Card v-if="objective" title="Consumo">
                    <Badge color="primary">{{ objective.spent_cents ?? 0 }} centavos gastos</Badge>
                </Card>
            </div>
        </div>
    </form>
</template>
