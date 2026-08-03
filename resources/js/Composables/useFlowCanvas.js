import { computed, reactive, ref } from "vue";

export const NODE_WIDTH = 210;
export const NODE_HEADER = 34;
export const HANDLE_SPACING = 22;

export function useFlowCanvas(initialNodes, initialEdges) {
    const nodes = ref(
        initialNodes.map((node) => ({ ...node, data: { ...node.data } })),
    );
    const edges = ref([...initialEdges]);

    const selectedNodeId = ref(null);
    const connecting = ref(null);
    const pan = reactive({ x: 0, y: 0 });
    const zoom = ref(1);
    const pointer = reactive({ x: 0, y: 0 });

    let dragging = null;
    let panning = null;

    const selectedNode = computed(
        () =>
            nodes.value.find((node) => node.id === selectedNodeId.value) ??
            null,
    );

    function nodeById(id) {
        return nodes.value.find((node) => node.id === id);
    }

    function outputHandles(node) {
        if (node.type === "condition") {
            return [
                { id: "true", label: "Sim" },
                { id: "false", label: "Não" },
            ];
        }

        if (node.type === "menu") {
            return (node.data.options ?? []).map((option, index) => ({
                id: option.id ?? String(index),
                label: option.label || `Opção ${index + 1}`,
            }));
        }

        if (["ai", "queue", "end"].includes(node.type)) {
            return [];
        }

        return [{ id: "default", label: "Seguir" }];
    }

    function nodeHeight(node) {
        return (
            NODE_HEADER +
            26 +
            Math.max(1, outputHandles(node).length) * HANDLE_SPACING
        );
    }

    function inputAnchor(node) {
        return { x: node.position.x, y: node.position.y + NODE_HEADER / 2 + 6 };
    }

    function outputAnchor(node, handleId) {
        const handles = outputHandles(node);
        const index = Math.max(
            0,
            handles.findIndex((handle) => handle.id === handleId),
        );

        return {
            x: node.position.x + NODE_WIDTH,
            y: node.position.y + NODE_HEADER + 18 + index * HANDLE_SPACING,
        };
    }

    function edgePath(from, to) {
        const delta = Math.max(40, Math.abs(to.x - from.x) / 2);

        return `M ${from.x} ${from.y} C ${from.x + delta} ${from.y}, ${to.x - delta} ${to.y}, ${to.x} ${to.y}`;
    }

    function toCanvas(event, element) {
        const rect = element.getBoundingClientRect();

        return {
            x: (event.clientX - rect.left - pan.x) / zoom.value,
            y: (event.clientY - rect.top - pan.y) / zoom.value,
        };
    }

    function startNodeDrag(event, node, element) {
        const point = toCanvas(event, element);

        dragging = {
            id: node.id,
            offsetX: point.x - node.position.x,
            offsetY: point.y - node.position.y,
            element,
        };
        selectedNodeId.value = node.id;
    }

    function startPan(event, element) {
        panning = {
            x: event.clientX - pan.x,
            y: event.clientY - pan.y,
            element,
        };
    }

    function onPointerMove(event, element) {
        const point = toCanvas(event, element);
        pointer.x = point.x;
        pointer.y = point.y;

        if (dragging) {
            const node = nodeById(dragging.id);

            if (node) {
                node.position = {
                    x: Math.round(point.x - dragging.offsetX),
                    y: Math.round(point.y - dragging.offsetY),
                };
            }

            return;
        }

        if (panning) {
            pan.x = event.clientX - panning.x;
            pan.y = event.clientY - panning.y;
        }
    }

    function endInteraction() {
        dragging = null;
        panning = null;
    }

    function startConnection(nodeId, handleId) {
        connecting.value = { source: nodeId, handle: handleId };
    }

    function completeConnection(targetId) {
        if (!connecting.value || connecting.value.source === targetId) {
            connecting.value = null;

            return;
        }

        const { source, handle } = connecting.value;

        edges.value = [
            ...edges.value.filter(
                (edge) =>
                    !(edge.source === source && edge.sourceHandle === handle),
            ),
            {
                id: `${source}-${handle}-${targetId}`,
                source,
                target: targetId,
                sourceHandle: handle,
            },
        ];

        connecting.value = null;
    }

    function cancelConnection() {
        connecting.value = null;
    }

    function removeEdge(id) {
        edges.value = edges.value.filter((edge) => edge.id !== id);
    }

    function addNode(type, label) {
        const id = `${type}-${Date.now().toString(36)}`;

        nodes.value = [
            ...nodes.value,
            {
                id,
                type,
                position: {
                    x: Math.round(
                        120 - pan.x / zoom.value + nodes.value.length * 24,
                    ),
                    y: Math.round(
                        120 - pan.y / zoom.value + nodes.value.length * 18,
                    ),
                },
                data: defaultDataFor(type, label),
            },
        ];

        selectedNodeId.value = id;
    }

    function removeNode(id) {
        nodes.value = nodes.value.filter((node) => node.id !== id);
        edges.value = edges.value.filter(
            (edge) => edge.source !== id && edge.target !== id,
        );

        if (selectedNodeId.value === id) {
            selectedNodeId.value = null;
        }
    }

    function defaultDataFor(type, label) {
        return {
            label,
            ...(type === "message" || type === "end" ? { text: "" } : {}),
            ...(type === "question" ? { text: "", save_as: "resposta" } : {}),
            ...(type === "menu"
                ? { text: "", options: [{ id: "op1", label: "Opção 1" }] }
                : {}),
            ...(type === "condition"
                ? { field: "mensagem", operator: "equals", value: "" }
                : {}),
            ...(type === "ai" ? { ai_objective_id: null } : {}),
            ...(type === "queue" ? { service_queue_id: null } : {}),
        };
    }

    function clampZoom(value) {
        return Math.min(1.6, Math.max(0.4, Number(value.toFixed(2))));
    }

    function zoomBy(amount) {
        zoom.value = clampZoom(zoom.value + amount);
    }

    function zoomAt(event, element, factor) {
        const rect = element.getBoundingClientRect();
        const next = clampZoom(zoom.value * factor);

        if (next === zoom.value) {
            return;
        }

        const cursorX = event.clientX - rect.left;
        const cursorY = event.clientY - rect.top;

        pan.x = cursorX - ((cursorX - pan.x) / zoom.value) * next;
        pan.y = cursorY - ((cursorY - pan.y) / zoom.value) * next;
        zoom.value = next;
    }

    function onWheel(event, element) {
        if (event.ctrlKey || event.metaKey) {
            zoomAt(event, element, Math.exp(-event.deltaY * 0.002));

            return;
        }

        if (event.shiftKey) {
            pan.x -= event.deltaY || event.deltaX;

            return;
        }

        pan.x -= event.deltaX;
        pan.y -= event.deltaY;
    }

    function resetView() {
        pan.x = 0;
        pan.y = 0;
        zoom.value = 1;
    }

    return {
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
        nodeHeight,
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
    };
}
