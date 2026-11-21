export type InvoiceTaxRate = '10' | '8';

export interface InvoiceLineInput {
    quantity: number;
    unit_price: number;
    tax_rate: InvoiceTaxRate;
}

export interface InvoiceRateSummary {
    rate: InvoiceTaxRate;
    label: string;
    base: number;
    tax: number;
}

export interface InvoiceTaxSummary {
    rates: InvoiceRateSummary[];
    subtotal: number;
    tax_total: number;
    total: number;
}

export function taxRateLabel(rate: InvoiceTaxRate): string {
    return rate === '8' ? '8%（軽減）' : '10%';
}

export function lineAmount(quantity: number, unitPrice: number): number {
    const qty = Number.isFinite(quantity) ? Math.trunc(quantity) : 0;
    const price = Number.isFinite(unitPrice) ? Math.trunc(unitPrice) : 0;

    return qty * price;
}

export function calculateInvoiceTax(lines: InvoiceLineInput[]): InvoiceTaxSummary {
    const bases: Record<InvoiceTaxRate, number | undefined> = { '10': undefined, '8': undefined };

    for (const line of lines) {
        const rate: InvoiceTaxRate = line.tax_rate === '8' ? '8' : '10';
        const amount = lineAmount(line.quantity, line.unit_price);
        bases[rate] = (bases[rate] ?? 0) + amount;
    }

    const rates: InvoiceRateSummary[] = (['10', '8'] as const)
        .filter((rate) => bases[rate] !== undefined)
        .map((rate) => {
            const base = bases[rate] ?? 0;
            const percent = rate === '8' ? 8 : 10;

            return {
                rate,
                label: taxRateLabel(rate),
                base,
                tax: Math.floor((base * percent) / 100),
            };
        });

    const subtotal = rates.reduce((sum, rate) => sum + rate.base, 0);
    const taxTotal = rates.reduce((sum, rate) => sum + rate.tax, 0);

    return {
        rates,
        subtotal,
        tax_total: taxTotal,
        total: subtotal + taxTotal,
    };
}
