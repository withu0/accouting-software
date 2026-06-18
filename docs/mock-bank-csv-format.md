# Mock Bank CSV Format

This document has moved to [bank-csv-formats.md](./bank-csv-formats.md).

The canonical mock/native format columns are:

| Column | Required | Description |
|--------|----------|-------------|
| 日付 | Yes | Transaction date (`YYYY-MM-DD`) |
| 摘要 | Yes | Transaction description |
| 入金額 | No | Deposit amount (yen, integer). Empty if withdrawal. |
| 出金額 | No | Withdrawal amount (yen, integer). Empty if deposit. |
| 残高 | No | Account balance after transaction |

See sample files `samples/bank-csv-samples/native-2025-04.csv` and `native-2025-05.csv`.
