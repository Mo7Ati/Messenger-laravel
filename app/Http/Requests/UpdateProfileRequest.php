<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'max:255', 'unique:users,username,' . $this->user()->id],
            'bio' => ['nullable', 'string', 'max:1000'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,' . $this->user()->id],
            'avatar' => [
                'nullable',
                File::types(['jpeg', 'jpg', 'png', 'gif', 'webp'])->max(2 * 1024),
            ],
        ];
    }
}
