<?php

namespace Module\Message\Http\Request;

use Illuminate\Foundation\Http\FormRequest;

class ToggleSetting extends FormRequest
{
    public function rules(): array
    {
        return [
            'scenario' => 'required|integer',
            'enabled' => 'required|boolean'
        ];
    }
}