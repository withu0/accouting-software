export function formatYen(amount: number): string {
    const formatted = new Intl.NumberFormat('ja-JP').format(Math.abs(amount));

    return amount < 0 ? `△${formatted}` : formatted;
}

export function parseYenInput(value: string): number {
    const normalized = value.replaceAll(/[¥,\s]/g, '').replaceAll('△', '-').replaceAll('▲', '-');

    if (normalized === '' || normalized === '-') {
        return 0;
    }

    const parsed = Number.parseInt(normalized, 10);

    return Number.isNaN(parsed) ? 0 : parsed;
}
