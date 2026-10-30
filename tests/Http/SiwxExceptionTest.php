<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use LexWebDev\Siwx\Exceptions\SiwxException;

it('answers an unhandled failure with 401 and the machine code', function () {
    config()->set('app.debug', false);
    Route::post('/login', fn () => throw new SiwxException('siwx_invalid_signature'));

    $this->postJson('/login')
        ->assertUnauthorized()
        ->assertExactJson(['message' => 'siwx_invalid_signature']);
});

it('does not report a failed login to the logs', function () {
    Log::spy();

    report(new SiwxException('siwx_invalid_signature'));

    Log::shouldNotHaveReceived('error');
});

it('still lets the application render the failure itself', function () {
    app(Illuminate\Contracts\Debug\ExceptionHandler::class)->renderable(
        fn (SiwxException $exception) => response()->json(['code' => $exception->code()], 422),
    );
    Route::post('/login', fn () => throw new SiwxException('siwx_invalid_nonce'));

    $this->postJson('/login')
        ->assertStatus(422)
        ->assertExactJson(['code' => 'siwx_invalid_nonce']);
});

it('keeps the machine code available to callers', function () {
    expect((new SiwxException('siwx_invalid_domain'))->code())->toBe('siwx_invalid_domain');
});
