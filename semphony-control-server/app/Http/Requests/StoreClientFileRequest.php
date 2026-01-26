<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientFileRequest extends FormRequest
{
    public function clientModel(): Client
    {
        $client = $this->attributes->get('client');

        if (! $client instanceof Client) {
            abort(403);
        }

        return $client;
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $apiKey = $this->header('X-Client-Key');

        if (! is_string($apiKey) || $apiKey === '') {
            return false;
        }

        $client = Client::query()
            ->where('api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (! $client instanceof Client) {
            return false;
        }

        $this->attributes->set('client', $client);

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:104857600'], // 100MB max
            'filename' => ['nullable', 'string', 'max:255'],
            'storage_configuration_id' => ['nullable', 'integer', 'exists:storage_configurations,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'file is required',
            'file.file' => 'file must be a file upload',
            'file.max' => 'file is too large (max 100MB)',
            'filename.string' => 'filename must be a string',
            'filename.max' => 'filename is too long',
            'storage_configuration_id.integer' => 'storage_configuration_id must be an integer',
            'storage_configuration_id.exists' => 'storage_configuration_id does not exist',
        ];
    }
}
