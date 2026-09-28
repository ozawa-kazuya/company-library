export function formatOverdueDuration(overdueMs) {
    const ms = Math.max(0, Number(overdueMs) || 0);

    if (ms <= 0) {
        return null;
    }

    const totalSeconds = Math.floor(ms / 1000);
    const totalMinutes = Math.floor(totalSeconds / 60);
    const totalHours = Math.floor(totalMinutes / 60);
    const days = Math.floor(totalHours / 24);

    if (days >= 1) {
        const hours = totalHours % 24;

        return hours > 0 ? `${days}日${hours}時間超過` : `${days}日超過`;
    }

    if (totalHours >= 1) {
        const minutes = totalMinutes % 60;

        return minutes > 0 ? `${totalHours}時間${minutes}分超過` : `${totalHours}時間超過`;
    }

    if (totalMinutes >= 1) {
        return `${totalMinutes}分超過`;
    }

    return `${Math.max(1, totalSeconds)}秒超過`;
}

export function getOverdueAmount(loan) {
    if (!loan?.due_date) {
        return null;
    }

    const today = new Date();
    const dueDate = new Date(loan.due_date);
    const overdueMs = today.getTime() - dueDate.getTime();

    if (overdueMs <= 0) {
        return null;
    }

    const text = formatOverdueDuration(overdueMs);

    return {
        text,
        days: Math.ceil(overdueMs / (1000 * 60 * 60 * 24)),
    };
}
