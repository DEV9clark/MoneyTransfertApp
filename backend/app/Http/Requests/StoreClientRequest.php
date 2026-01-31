<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255|unique:clients',
            'phone' => 'required|string|max:20|unique:clients',
            'address' => 'nullable|string|max:255',
            'country_id' => 'required|exists:countries,id',
        ];
    }
}
