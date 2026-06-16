# Mock Bank CSV Format

This document defines the canonical CSV format used until real bank samples are provided.

## Columns

| Column | Required | Description |
|--------|----------|-------------|
| 日付 | Yes | Transaction date (`YYYY-MM-DD`) |
| 摘要 | Yes | Transaction description |
| 入金額 | No | Deposit amount (yen, integer). Empty if withdrawal. |
| 出金額 | No | Withdrawal amount (yen, integer). Empty if deposit. |
| 残高 | No | Account balance after transaction |

## Rules

- Header row is required.
- Each data row must have exactly one of 入金額 or 出金額 greater than zero.
- Amounts are non-negative integers without currency symbols or commas.
- Encoding: UTF-8 (Shift_JIS support planned for Phase 7).

## Example

```csv
日付,摘要,入金額,出金額,残高
2025-04-01,振込 カ）ABC商事,100000,,1500000
2025-04-02,Amazon.co.jp,,5000,1495000
2025-04-03,JR東日本,,3200,1491800
```

## Posting behavior

| Row type | User action | Generated journal |
|----------|-------------|-------------------|
| 入金額 > 0 | Confirm | 預金 / 売上高 |
| 出金額 > 0 | Select 経費科目 | {経費} / 預金 |
