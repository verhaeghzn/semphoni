<?php

use App\Models\Client;
use App\Models\ClientFile;
use App\Models\StorageConfiguration;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\post;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
});

it('allows client to upload file to default SFS storage', function () {
    $client = Client::factory()->create([
        'api_key' => 'test-client-api-key',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('test-file.pdf', 100);

    $response = post(route('client.files.store', absolute: false), [
        'file' => $file,
        'filename' => 'custom-filename.pdf',
    ], [
        'X-Client-Key' => 'test-client-api-key',
    ]);

    $response->assertSuccessful();
    $response->assertJsonStructure([
        'id',
        'client_id',
        'original_filename',
        'storage_type',
        'mime',
        'bytes',
        'sha256',
        'uploaded_at',
        'status',
    ]);

    expect($response->json('storage_type'))->toBe('sfs');
    expect($response->json('status'))->toBe('stored');

    $clientFile = ClientFile::query()->find($response->json('id'));
    expect($clientFile)->not->toBeNull();
    expect($clientFile->storage_type)->toBe('sfs');
    expect($clientFile->storage_configuration_id)->toBeNull();
    expect($clientFile->original_filename)->toBe('custom-filename.pdf');

    Storage::disk('local')->assertExists($clientFile->storage_path);
});

it('queues file upload when storage configuration is provided', function () {
    $user = User::factory()->create();
    $client = Client::factory()->create([
        'api_key' => 'test-client-api-key',
        'is_active' => true,
    ]);

    $storageConfig = StorageConfiguration::factory()->create([
        'user_id' => $user->id,
        'type' => 's3',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('test-file.pdf', 100);

    $response = post(route('client.files.store', absolute: false), [
        'file' => $file,
        'storage_configuration_id' => $storageConfig->id,
    ], [
        'X-Client-Key' => 'test-client-api-key',
    ]);

    $response->assertSuccessful();
    expect($response->json('storage_type'))->toBe('s3');
    expect($response->json('status'))->toBe('queued');

    $clientFile = ClientFile::query()->find($response->json('id'));
    expect($clientFile)->not->toBeNull();
    expect($clientFile->storage_configuration_id)->toBe($storageConfig->id);

    Queue::assertPushed(\App\Jobs\UploadFileToStorageJob::class);
});

it('rejects file upload with invalid API key', function () {
    Client::factory()->create([
        'api_key' => 'test-client-api-key',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('test-file.pdf', 100);

    $response = post(route('client.files.store', absolute: false), [
        'file' => $file,
    ], [
        'X-Client-Key' => 'invalid-key',
    ]);

    $response->assertForbidden();
});

it('rejects file upload when client is inactive', function () {
    $client = Client::factory()->create([
        'api_key' => 'test-client-api-key',
        'is_active' => false,
    ]);

    $file = UploadedFile::fake()->create('test-file.pdf', 100);

    $response = post(route('client.files.store', absolute: false), [
        'file' => $file,
    ], [
        'X-Client-Key' => 'test-client-api-key',
    ]);

    $response->assertForbidden();
});

it('validates file is required', function () {
    $client = Client::factory()->create([
        'api_key' => 'test-client-api-key',
        'is_active' => true,
    ]);

    $response = $this->post(route('client.files.store', absolute: false), [], [
        'X-Client-Key' => 'test-client-api-key',
        'Accept' => 'application/json',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['file']);
});

it('rejects file upload with invalid storage configuration', function () {
    $client = Client::factory()->create([
        'api_key' => 'test-client-api-key',
        'is_active' => true,
    ]);

    $file = UploadedFile::fake()->create('test-file.pdf', 100);

    $response = $this->post(route('client.files.store', absolute: false), [
        'file' => $file,
        'storage_configuration_id' => 99999,
    ], [
        'X-Client-Key' => 'test-client-api-key',
        'Accept' => 'application/json',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['storage_configuration_id']);
});
