<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesCompany;
use App\Http\Requests\StoreReceiptScanRequest;
use App\Services\ReceiptOcrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ReceiptScanController extends Controller
{
    use ResolvesCompany;

    public function __construct(
        private readonly ReceiptOcrService $receiptOcrService,
    ) {}

    public function store(StoreReceiptScanRequest $request): RedirectResponse
    {
        $company = $this->resolveCompany($request);

        if ($company->activeFiscalYear() === null) {
            return redirect()
                ->route('advance-expenses')
                ->withErrors(['receipt' => '会計期間が設定されていません。']);
        }

        try {
            $result = $this->receiptOcrService->extract($request->file('file'));
        } catch (InvalidArgumentException $exception) {
            return redirect()
                ->route('advance-expenses')
                ->withErrors(['receipt' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('advance-expenses')
                ->withErrors(['receipt' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            Log::error('Receipt scan failed', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            $message = config('app.debug')
                ? $exception->getMessage()
                : '領収書の読み取り中にエラーが発生しました。';

            return redirect()
                ->route('advance-expenses')
                ->withErrors(['receipt' => $message]);
        }

        if ($result['entry_date'] === null && $result['amount'] === null) {
            return redirect()
                ->route('advance-expenses')
                ->withErrors(['receipt' => '日付と金額を読み取れませんでした。手入力してください。']);
        }

        return redirect()
            ->route('advance-expenses')
            ->with('receiptScan', $result);
    }
}
