<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class UpdatePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (!Hash::check($this->current_password, $this->user()->password)) {
                    $validator->errors()->add('current_password', 'The current password is incorrect.');
                }
            },
        ];
    }
}
