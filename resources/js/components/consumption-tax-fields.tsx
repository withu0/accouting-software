import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

export interface TaxCategoryOption {
    value: string;
    label: string;
}

const PURCHASE_BASE_CATEGORIES = new Set(['taxable_purchase_10', 'taxable_purchase_8_reduced']);

interface ConsumptionTaxFieldsProps {
    category: string;
    hasQualifiedInvoice: boolean;
    categoryOptions: TaxCategoryOption[];
    onCategoryChange: (value: string) => void;
    onQualifiedInvoiceChange: (value: boolean) => void;
    categoryError?: string;
    qualifiedInvoiceError?: string;
    showQualifiedInvoice?: boolean;
    idPrefix?: string;
}

export default function ConsumptionTaxFields({
    category,
    hasQualifiedInvoice,
    categoryOptions,
    onCategoryChange,
    onQualifiedInvoiceChange,
    categoryError,
    qualifiedInvoiceError,
    showQualifiedInvoice = true,
    idPrefix = 'tax',
}: ConsumptionTaxFieldsProps) {
    const showInvoiceCheckbox = showQualifiedInvoice && PURCHASE_BASE_CATEGORIES.has(category);

    return (
        <div className="space-y-4">
            <div className="grid gap-2">
                <Label htmlFor={`${idPrefix}_category`}>税区分</Label>
                <Select value={category || undefined} onValueChange={onCategoryChange}>
                    <SelectTrigger id={`${idPrefix}_category`}>
                        <SelectValue placeholder="税区分を選択" />
                    </SelectTrigger>
                    <SelectContent>
                        {categoryOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={categoryError} />
            </div>

            {showInvoiceCheckbox && (
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={`${idPrefix}_qualified_invoice`}
                        checked={hasQualifiedInvoice}
                        onCheckedChange={(checked) => onQualifiedInvoiceChange(checked === true)}
                    />
                    <Label htmlFor={`${idPrefix}_qualified_invoice`} className="font-normal">
                        適格請求書あり
                    </Label>
                    <InputError message={qualifiedInvoiceError} />
                </div>
            )}
        </div>
    );
}

export function defaultCategoryForAccount(
    accountId: number | null | undefined,
    accounts: Array<{ id: number; default_consumption_tax_category?: string | null }>,
    fallback: string,
): string {
    if (!accountId) {
        return fallback;
    }

    const account = accounts.find((item) => item.id === accountId);

    return account?.default_consumption_tax_category ?? fallback;
}
