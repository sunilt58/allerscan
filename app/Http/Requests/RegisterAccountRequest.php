<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RegisterAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Shared by the website and the smartphone app API. The app also names the device its token belongs to.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'consent' => ['accepted'],
            'device_name' => [$this->is('api/*') ? 'required' : 'prohibited', 'string', 'max:100'],
        ];
    }
}
