<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreReceiptScanRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => '領収書の画像を選択してください。',
            'file.mimes' => 'JPEG、PNG、WebP形式の画像をアップロードしてください。',
            'file.max' => 'ファイルサイズは5MB以下にしてください。',
        ];
    }
}
