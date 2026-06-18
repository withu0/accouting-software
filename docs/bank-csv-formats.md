# Bank CSV Formats

The bank import feature auto-detects CSV encoding (UTF-8 or Shift_JIS) and bank format from file content.

## Supported formats

| Format | Sample files | Headers / structure |
|--------|--------------|---------------------|
| 標準形式 (native) | `native-2025-04.csv`, `native-2025-05.csv` | `日付, 摘要, 入金額, 出金額, 残高` |
| GMOあおぞら 当社CSV | `gmo-native-2025-04.csv`, `gmo-native-2025-05.csv` | Same as native + optional `メモ` (Shift_JIS) |
| 楽天銀行 | `rakuten-2025-04.csv`, `rakuten-2025-05.csv` | `取引日, 入出金(円), 残高(円), 入出金先内容` — positive = deposit, negative = withdrawal |
| 住信SBIネット銀行 | `sbi-2025-04.csv`, `sbi-2025-05.csv` | `日付, 内容, 出金金額(円), 入金金額(円), 残高(円), メモ` |
| GMOあおぞら 全銀CSV | `gmo-zengin-csv-2025-04.csv`, `gmo-zengin-csv-2025-05.csv` | 全銀協 record format (header/data/trailer/end), comma-separated |
| GMOあおぞら 全銀固定長 | `gmo-zengin-fixed-2025-04.txt`, `gmo-zengin-fixed-2025-05.txt` | 200-character fixed-width 全銀協 records |

Sample files live in [`samples/bank-csv-samples/`](../samples/bank-csv-samples/). Regenerate with:

```bash
php scripts/generate-bank-csv-samples.php
```

## Normalized internal shape

All formats are converted to:

| Field | Type | Notes |
|-------|------|-------|
| transaction_date | date | `YYYY-MM-DD` |
| description | string | 摘要 / 内容 / 入出金先内容 |
| deposit_amount | integer | Yen, 0 if withdrawal |
| withdrawal_amount | integer | Yen, 0 if deposit |
| balance | integer or null | After-transaction balance when available |

## Rules

- Each row must have exactly one of deposit or withdrawal greater than zero.
- Amounts are non-negative integers without currency symbols (commas are stripped).
- Blank data rows are skipped.
- Duplicate detection uses a SHA-256 hash of date, description, amounts, and balance.

## Posting behavior

| Row type | User action | Generated journal |
|----------|-------------|-------------------|
| 入金 | Confirm | 預金 / 売上高 |
| 出金 | Select 経費科目 | {経費} / 預金 |
