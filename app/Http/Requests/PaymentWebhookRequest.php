<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PaymentWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'external_reference' => ['required', 'string'],
            'status' => ['required', 'string', 'in:success,failed'],
            'transaction_id' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric'],
            'reason' => ['nullable', 'string'],
        ];
    }
}
