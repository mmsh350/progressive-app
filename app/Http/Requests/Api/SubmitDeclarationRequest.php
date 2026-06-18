<?php

namespace App\Http\Requests\Api;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SubmitDeclarationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|digits:11',
            'email' => 'nullable|email|max:255',
            'gender' => 'required|string|in:male,female,other',
            'age_group' => 'required|string|in:18-25,26-35,36-50,50+',
            'state_id' => 'required|integer|exists:states,id',
            'lga_id' => 'required|integer|exists:lgas,id',
            'ward_id' => 'nullable|integer|exists:wards,id',
            'polling_unit' => 'nullable|string|max:255',
            'voted_2023' => 'required|boolean',
            'vote_2027' => 'required|boolean',
            'occupation_id' => 'required|integer|exists:occupations,id',

            // Wish validation rules
            'wish_category_id' => 'required|integer|exists:wish_categories,id',
            'wish_title' => 'required|string|max:255',
            'wish_description' => 'required|string',

            // Verification image
            'pvc_selfie' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
            'agreement' => 'required|accepted',
        ];
    }
}
