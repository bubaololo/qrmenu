<?php

namespace App\Http\Requests\Push;

use Illuminate\Foundation\Http\FormRequest;

class StorePushSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'endpoint' => ['required', 'string', 'max:2048'],
            'key' => ['required', 'string', 'max:255'],
            'token' => ['required', 'string', 'max:255'],
            'encoding' => ['nullable', 'string', 'max:50'],
        ];
    }
}
