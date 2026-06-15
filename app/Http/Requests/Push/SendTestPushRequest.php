<?php

namespace App\Http\Requests\Push;

use Illuminate\Foundation\Http\FormRequest;

class SendTestPushRequest extends FormRequest
{
    /**
     * Only admins may fire ad-hoc pushes (reuses User::isAdmin / ADMIN_EMAILS).
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'title' => ['nullable', 'string', 'max:100'],
            'body' => ['required', 'string', 'max:255'],
        ];
    }
}
