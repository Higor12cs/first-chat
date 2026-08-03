const entities = { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" };

const rules = [
    [/```([\s\S]+?)```/g, "<code>$1</code>"],
    [/(^|\W)\*(?!\s)([^*\n]+?)(?<!\s)\*(?=\W|$)/g, "$1<strong>$2</strong>"],
    [/(^|\W)_(?!\s)([^_\n]+?)(?<!\s)_(?=\W|$)/g, "$1<em>$2</em>"],
    [/(^|\W)~(?!\s)([^~\n]+?)(?<!\s)~(?=\W|$)/g, "$1<s>$2</s>"],
];

function escape(value) {
    return String(value).replace(/[&<>"']/g, (character) => entities[character]);
}

export function formatWhatsApp(value) {
    if (!value) {
        return "";
    }

    return rules.reduce((text, [pattern, replacement]) => text.replace(pattern, replacement), escape(value));
}
