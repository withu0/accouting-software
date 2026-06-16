/**
 * Format a date string as YYYY/MM/DD in the browser's local timezone.
 */
export function formatDate(date: string): string {
    const parsed = new Date(date);

    if (Number.isNaN(parsed.getTime())) {
        return date.replace(/-/g, '/').slice(0, 10);
    }

    return parsed.toLocaleDateString(undefined, {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    });
}

/**
 * Convert a date string to YYYY-MM-DD for <input type="date">.
 */
export function toDateInputValue(date: string): string {
    const parsed = new Date(date);

    if (Number.isNaN(parsed.getTime())) {
        return date.slice(0, 10);
    }

    const year = parsed.getFullYear();
    const month = String(parsed.getMonth() + 1).padStart(2, '0');
    const day = String(parsed.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}
