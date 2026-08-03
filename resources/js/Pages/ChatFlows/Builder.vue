<script setup>
import { computed, ref } from "vue";
import { Head, useForm } from "@inertiajs/vue3";
import { useFlowCanvas } from "../../Composables/useFlowCanvas";
import FlowNodeCard from "../../Components/ChatFlows/FlowNodeCard.vue";
import FlowNodeInspector from "../../Components/ChatFlows/FlowNodeInspector.vue";
import Button from "../../Components/UI/Button.vue";
import {
    ArrowLeft,
    Check,
    CircleCheck,
    Info,
    ListChecks,
    MessagesSquare,
    Minus,
    Play,
    Plus,
    RefreshCw,
    Save,
    Settings,
    Sparkles,
    Users,
    Workflow,
} from "lucide-vue-next";
import Toggle from "../../Components/UI/Toggle.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import Badge from "../../Components/UI/Badge.vue";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    flow: { type: Object, required: true },
    node_types: { type: Array, default: () => [] },
    queues: { type: Array, default: () => [] },
    objectives: { type: Array, default: () => [] },
    agents: { type: Array, default: () => [] },
    cards: { type: Array, default: () => [] },
});

const { can } = usePermissions();

const canvas = ref(null);
const showSettings = ref(false);

const flowCanvas = useFlowCanvas(props.flow.nodes, props.flow.edges);

const {
    nodes,
    edges,
    selectedNodeId,
    selectedNode,
    connecting,
    pan,
    zoom,
    pointer,
    nodeById,
    outputHandles,
    inputAnchor,
    outputAnchor,
    edgePath,
    startNodeDrag,
    startPan,
    onPointerMove,
    endInteraction,
    startConnection,
    completeConnection,
    cancelConnection,
    removeEdge,
    addNode,
    removeNode,
    zoomBy,
    onWheel,
    resetView,
} = flowCanvas;

const nodeIcons = {
    start: Play,
    message: MessagesSquare,
    menu: ListChecks,
    question: Info,
    condition: Workflow,
    ai: Sparkles,
    queue: Users,
    close: CircleCheck,
    end: Check,
};

const form = useForm({
    name: props.flow.name,
    description: props.flow.description ?? "",
    is_active: props.flow.is_active,
    nodes: props.flow.nodes,
    edges: props.flow.edges,
    triggers: props.flow.triggers ?? [],
});

const availableTypes = computed(() =>
    props.node_types.filter((type) => type.value !== "start"),
);

const drawnEdges = computed(() =>
    edges.value
        .map((edge) => {
            const source = nodeById(edge.source);
            const target = nodeById(edge.target);

            if (!source || !target) {
                return null;
            }

            const from = outputAnchor(source, edge.sourceHandle ?? "default");
            const to = inputAnchor(target);

            return {
                ...edge,
                path: edgePath(from, to),
                midX: (from.x + to.x) / 2,
                midY: (from.y + to.y) / 2,
            };
        })
        .filter(Boolean),
);

const pendingEdge = computed(() => {
    if (!connecting.value) {
        return null;
    }

    const source = nodeById(connecting.value.source);

    if (!source) {
        return null;
    }

    return edgePath(outputAnchor(source, connecting.value.handle), {
        x: pointer.x,
        y: pointer.y,
    });
});

function updateSelectedData(data) {
    const node = nodeById(selectedNodeId.value);

    if (node) {
        node.data = data;
    }
}

function save() {
    form.nodes = nodes.value;
    form.edges = edges.value;
    form.put(`/fluxos/${props.flow.id}`, {
        preserveScroll: true,
        preserveState: true,
    });
}

function onCanvasMouseDown(event) {
    if (connecting.value) {
        cancelConnection();

        return;
    }

    selectedNodeId.value = null;
    startPan(event, canvas.value);
}
</script>

<template>
    <Head :title="flow.name" />

    <div class="flex h-full min-h-0">
        <aside
            class="flex w-56 shrink-0 flex-col border-r border-border bg-surface"
        >
            <div class="border-b border-border px-3 py-3">
                <p class="truncate text-sm font-semibold text-content">
                    {{ flow.name }}
                </p>
                <p class="text-xs text-content-muted">
                    {{ nodes.length }} Blocos · {{ edges.length }} Conexões
                </p>
            </div>

            <div class="flex-1 space-y-1 overflow-y-auto scrollbar-thin p-2">
                <p
                    class="px-1 pb-1 text-[10px] font-semibold uppercase tracking-wider text-content-subtle"
                >
                    Blocos
                </p>

                <button
                    v-for="type in availableTypes"
                    :key="type.value"
                    type="button"
                    class="flex w-full items-center gap-2 rounded-control px-2 py-2 text-left text-sm text-content-muted transition hover:bg-surface-hover hover:text-content"
                    @click="addNode(type.value, type.label)"
                >
                    <component
                        :is="nodeIcons[type.value] ?? Workflow"
                        :size="16"
                    />
                    {{ type.label }}
                </button>
            </div>

            <div class="space-y-2 border-t border-border p-2">
                <Button
                    variant="secondary"
                    :icon="Settings"
                    class="w-full justify-center"
                    @click="showSettings = !showSettings"
                >
                    Configurações
                </Button>
                <Button
                    variant="secondary"
                    :icon="ArrowLeft"
                    href="/fluxos"
                    class="w-full justify-center"
                    >Voltar</Button
                >
            </div>
        </aside>

        <section class="relative min-w-0 flex-1 overflow-hidden bg-canvas">
            <div
                ref="canvas"
                class="absolute inset-0 cursor-grab active:cursor-grabbing"
                style="
                    background-image: radial-gradient(
                        circle,
                        var(--color-border) 1px,
                        transparent 1px
                    );
                    background-size: 22px 22px;
                "
                @mousedown="onCanvasMouseDown"
                @wheel.prevent="onWheel($event, canvas)"
                @mousemove="onPointerMove($event, canvas)"
                @mouseup="endInteraction"
                @mouseleave="endInteraction"
            >
                <div
                    class="absolute left-0 top-0 origin-top-left"
                    :style="{
                        transform: `translate(${pan.x}px, ${pan.y}px) scale(${zoom})`,
                    }"
                >
                    <svg
                        class="pointer-events-none absolute left-0 top-0 overflow-visible"
                        width="1"
                        height="1"
                    >
                        <g v-for="edge in drawnEdges" :key="edge.id">
                            <path
                                :d="edge.path"
                                fill="none"
                                stroke="var(--color-border-strong)"
                                stroke-width="2"
                            />
                            <circle
                                class="pointer-events-auto cursor-pointer"
                                :cx="edge.midX"
                                :cy="edge.midY"
                                r="7"
                                fill="var(--color-surface)"
                                stroke="var(--color-border-strong)"
                                stroke-width="1.5"
                                @click="removeEdge(edge.id)"
                            />
                            <text
                                :x="edge.midX"
                                :y="edge.midY + 3"
                                text-anchor="middle"
                                font-size="9"
                                fill="var(--color-content-muted)"
                                class="pointer-events-none select-none"
                            >
                                ×
                            </text>
                        </g>

                        <path
                            v-if="pendingEdge"
                            :d="pendingEdge"
                            fill="none"
                            stroke="var(--color-primary)"
                            stroke-width="2"
                            stroke-dasharray="5 4"
                        />
                    </svg>

                    <FlowNodeCard
                        v-for="node in nodes"
                        :key="node.id"
                        :node="node"
                        :handles="outputHandles(node)"
                        :selected="selectedNodeId === node.id"
                        :connecting="
                            Boolean(connecting) && connecting.source !== node.id
                        "
                        :active-handle="
                            connecting?.source === node.id
                                ? connecting.handle
                                : null
                        "
                        @drag-start="startNodeDrag($event, node, canvas)"
                        @select="selectedNodeId = node.id"
                        @connect-from="startConnection(node.id, $event)"
                        @connect-to="completeConnection(node.id)"
                        @remove="removeNode(node.id)"
                    />
                </div>
            </div>

            <div
                class="absolute left-3 top-3 flex items-center gap-1 rounded-control border border-border bg-surface p-1"
            >
                <button
                    type="button"
                    class="rounded p-1.5 text-content-muted transition hover:bg-surface-hover"
                    title="Diminuir Zoom"
                    @click="zoomBy(-0.1)"
                >
                    <Minus :size="14" />
                </button>
                <span class="w-10 text-center text-xs text-content-muted"
                    >{{ Math.round(zoom * 100) }}%</span
                >
                <button
                    type="button"
                    class="rounded p-1.5 text-content-muted transition hover:bg-surface-hover"
                    title="Aumentar Zoom"
                    @click="zoomBy(0.1)"
                >
                    <Plus :size="14" />
                </button>
                <button
                    type="button"
                    class="rounded p-1.5 text-content-muted transition hover:bg-surface-hover"
                    title="Reposicionar a Visão"
                    @click="resetView"
                >
                    <RefreshCw :size="14" />
                </button>
                <span
                    class="hidden border-l border-border pl-2 text-[10px] text-content-subtle sm:block"
                >
                    Ctrl + Scroll
                </span>
            </div>

            <div class="absolute right-3 top-3 flex items-center gap-2">
                <Badge :color="flow.is_active ? 'success' : 'muted'">{{
                    flow.is_active ? "Ativo" : "Inativo"
                }}</Badge>
                <Button
                    v-if="can('chat-flows.update')"
                    :icon="Save"
                    :loading="form.processing"
                    @click="save"
                    >Salvar Fluxo</Button
                >
            </div>

            <p
                v-if="connecting"
                class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-1.5 text-xs text-primary-content"
            >
                Clique na entrada do bloco de destino para conectar.
            </p>
        </section>

        <aside
            class="flex w-72 shrink-0 flex-col overflow-y-auto scrollbar-thin border-l border-border bg-surface"
        >
            <div
                v-if="showSettings"
                class="space-y-4 border-b border-border p-4"
            >
                <p
                    class="text-xs font-semibold uppercase tracking-wider text-content-subtle"
                >
                    Fluxo
                </p>

                <FormField label="Nome" :error="form.errors.name" required>
                    <TextInput v-model="form.name" />
                </FormField>

                <FormField label="Descrição" :error="form.errors.description">
                    <TextArea v-model="form.description" rows="2" />
                </FormField>

                <Toggle
                    v-model="form.is_active"
                    label="Fluxo ativo"
                    description="Fluxos inativos não atendem."
                />
            </div>

            <div v-if="selectedNode" class="space-y-4 p-4">
                <div class="flex items-center gap-2">
                    <span
                        class="rounded-control bg-primary-soft p-1.5 text-primary"
                    >
                        <component
                            :is="nodeIcons[selectedNode.type] ?? Workflow"
                            :size="15"
                        />
                    </span>
                    <p class="text-sm font-semibold text-content">
                        {{ selectedNode.data?.label ?? selectedNode.type }}
                    </p>
                </div>

                <FlowNodeInspector
                    :node="selectedNode"
                    :queues="queues"
                    :objectives="objectives"
                    :agents="agents"
                    :cards="cards"
                    @update="updateSelectedData"
                />
            </div>

            <div
                v-else
                class="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center"
            >
                <span
                    class="rounded-full bg-surface-muted p-3 text-content-subtle"
                >
                    <Workflow :size="20" />
                </span>
                <p class="text-sm font-medium text-content">
                    Selecione um Bloco
                </p>
                <p class="text-xs text-content-muted">
                    Clique em um bloco para editar o conteúdo ou arraste a
                    bolinha da saída até a entrada de outro bloco.
                </p>
            </div>
        </aside>
    </div>
</template>
