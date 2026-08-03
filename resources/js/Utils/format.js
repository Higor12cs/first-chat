const dateFormatter = new Intl.DateTimeFormat("pt-BR", { day: "2-digit", month: "2-digit", year: "numeric" });
const timeFormatter = new Intl.DateTimeFormat("pt-BR", { hour: "2-digit", minute: "2-digit" });
const currencyFormatter = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" });
const usdFormatter = new Intl.NumberFormat("pt-BR", { style: "currency", currency: "USD" });

export function formatDate(value) {
    return value ? dateFormatter.format(new Date(value)) : "";
}

export function formatTime(value) {
    return value ? timeFormatter.format(new Date(value)) : "";
}

export function formatDateTime(value) {
    return value ? `${formatDate(value)} ${formatTime(value)}` : "";
}

export function formatCents(cents) {
    return currencyFormatter.format((cents ?? 0) / 100);
}

/**
 * Os provedores de IA cobram em dólar, então todo custo de IA é armazenado e
 * exibido nessa moeda. O preço da assinatura continua em reais.
 */
export function formatUsdCents(cents) {
    return usdFormatter.format((cents ?? 0) / 100);
}

export function formatRelative(value, now = Date.now()) {
    if (!value) {
        return "";
    }

    const diff = now - new Date(value).getTime();
    const minutes = Math.round(diff / 60000);

    if (minutes < 1) return "agora";
    if (minutes < 60) return `${minutes} min`;

    const hours = Math.round(minutes / 60);
    if (hours < 24) return `${hours} h`;

    const days = Math.round(hours / 24);
    if (days < 7) return `${days} d`;

    return formatDate(value);
}

export function formatPhone(value) {
    if (!value) {
        return "";
    }

    const raw = String(value).split("@")[0];
    const digits = raw.replace(/\D/g, "");

    if (!digits.startsWith("55")) {
        return raw;
    }

    const national = digits.slice(2);
    const area = national.slice(0, 2);
    const subscriber = national.slice(2);

    if (subscriber.length === 9) {
        return `(${area})${subscriber.slice(0, 5)}-${subscriber.slice(5)}`;
    }

    if (subscriber.length === 8) {
        return `(${area})${subscriber.slice(0, 4)}-${subscriber.slice(4)}`;
    }

    return raw;
}

export function initials(name) {
    return (name ?? "")
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join("")
        .toUpperCase();
}
