<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CreateOrderRequest extends FormRequest
{

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string'],
            'items.*.source_api' => ['required', 'string', 'in:fakestore,dummyjson'],
            'items.*.name' => ['nullable', 'string'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'shipping' => ['required', 'array'],
            'shipping.courier' => ['required', 'string'],
            'shipping.service' => ['required', 'string'],
            'shipping.origin' => ['nullable', 'string'],
            'shipping.destination' => ['nullable', 'string'],
            'shipping.weight' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function getIdempotencyKey(): string
    {
        return (string) ($this->header('X-Idempotency-Key')
            ?: $this->input('idempotency_key')
            ?: ('ORD-IDEMP-'.uniqid('', true)));
    }
}
