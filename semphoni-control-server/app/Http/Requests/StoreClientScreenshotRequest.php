<?php

namespace App\Http\Requests;

use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

class StoreClientScreenshotRequest extends FormRequest
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
            'monitor_nr' => ['required', 'integer', 'min:1', 'max:10'],
            'image' => ['required', 'file', 'mimes:jpg,jpeg', 'max:25000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'monitor_nr.required' => 'monitor_nr is required',
            'monitor_nr.integer' => 'monitor_nr must be an integer',
            'monitor_nr.min' => 'monitor_nr must be at least 1',
            'monitor_nr.max' => 'monitor_nr must be at most 10',
            'image.required' => 'image is required',
            'image.file' => 'image must be a file upload',
            'image.mimes' => 'image must be a JPEG file (jpg or jpeg)',
            'image.max' => 'image is too large',
        ];
    }
}
