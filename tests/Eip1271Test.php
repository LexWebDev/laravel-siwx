<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use LexWebDev\Siwx\Contracts\ContractSignatureChecker;
use LexWebDev\Siwx\Contracts\NonceRepository;
use LexWebDev\Siwx\Exceptions\SiwxException;
use LexWebDev\Siwx\RpcContractSignatureChecker;
use LexWebDev\Siwx\SiwxParser;
use LexWebDev\Siwx\SiwxVerifier;
use LexWebDev\Siwx\Verifiers\Eip155Verifier;
use LexWebDev\Siwx\VerifierRegistry;

it('falls back to the contract checker when recovery does not match', function () {
    $checker = new class implements ContractSignatureChecker
    {
        public function isValid(string $address, string $hash, string $signature, string $chainId): bool
        {
            return true;
        }
    };

    $message = (new SiwxParser)->parse(EVM_MESSAGE);

    expect((new Eip155Verifier($checker))->verify($message, EVM_FOREIGN_SIG))->toBeTrue();
});

it('rejects when the contract checker declines', function () {
    $checker = new class implements ContractSignatureChecker
    {
        public function isValid(string $address, string $hash, string $signature, string $chainId): bool
        {
            return false;
        }
    };

    $message = (new SiwxParser)->parse(EVM_MESSAGE);

    expect((new Eip155Verifier($checker))->verify($message, EVM_FOREIGN_SIG))->toBeFalse();
});

it('does not call the checker when recovery already matches', function () {
    $calls = 0;
    $checker = new class($calls) implements ContractSignatureChecker
    {
        public function __construct(private int &$calls) {}

        public function isValid(string $address, string $hash, string $signature, string $chainId): bool
        {
            $this->calls++;

            return true;
        }
    };

    $message = (new SiwxParser)->parse(EVM_MESSAGE);

    expect((new Eip155Verifier($checker))->verify($message, EVM_SIG))->toBeTrue()
        ->and($calls)->toBe(0);
});

it('reads the magic value from an eth_call response', function () {
    Http::fake(['rpc.test/*' => Http::response(['result' => '0x1626ba7e' . str_repeat('0', 56)])]);

    $checker = new RpcContractSignatureChecker('https://rpc.test/v1');

    expect($checker->isValid(EVM_SIGNER, str_repeat('a', 64), EVM_SIG, 'eip155:1'))->toBeTrue();
});

it('treats any other magic value as invalid', function () {
    Http::fake(['rpc.test/*' => Http::response(['result' => '0x' . str_repeat('0', 64)])]);

    $checker = new RpcContractSignatureChecker('https://rpc.test/v1');

    expect($checker->isValid(EVM_SIGNER, str_repeat('a', 64), EVM_SIG, 'eip155:1'))->toBeFalse();
});

it('treats an rpc failure as invalid rather than throwing', function () {
    Http::fake(['rpc.test/*' => Http::response(status: 500)]);

    $checker = new RpcContractSignatureChecker('https://rpc.test/v1');

    expect($checker->isValid(EVM_SIGNER, str_repeat('a', 64), EVM_SIG, 'eip155:1'))->toBeFalse();
});

it('hands the chain of the message to the contract checker', function () {
    $chain = null;
    $checker = new class($chain) implements ContractSignatureChecker
    {
        public function __construct(private ?string &$chain) {}

        public function isValid(string $address, string $hash, string $signature, string $chainId): bool
        {
            $this->chain = $chainId;

            return true;
        }
    };

    $message = (new SiwxParser)->parse(str_replace('Chain ID: 1', 'Chain ID: 8453', EVM_MESSAGE));

    (new Eip155Verifier($checker))->verify($message, EVM_FOREIGN_SIG);

    expect($chain)->toBe('eip155:8453');
});

it('calls the rpc of the chain the message names', function () {
    Http::fake([
        'main.test/*' => Http::response(['result' => '0x' . str_repeat('0', 64)]),
        'base.test/*' => Http::response(['result' => '0x1626ba7e' . str_repeat('0', 56)]),
    ]);

    $checker = new RpcContractSignatureChecker(null, [
        'eip155:1' => 'https://main.test/v1',
        'eip155:8453' => 'https://base.test/v1',
    ]);

    expect($checker->isValid(EVM_SIGNER, str_repeat('a', 64), EVM_SIG, 'eip155:8453'))->toBeTrue()
        ->and($checker->isValid(EVM_SIGNER, str_repeat('a', 64), EVM_SIG, 'eip155:1'))->toBeFalse();
});

it('refuses a chain outside the configured map instead of falling back', function () {
    Http::fake();

    $checker = new RpcContractSignatureChecker('https://legacy.test/v1', ['eip155:1' => 'https://main.test/v1']);

    expect($checker->isValid(EVM_SIGNER, str_repeat('a', 64), EVM_SIG, 'eip155:137'))->toBeFalse();
    Http::assertNothingSent();
});

it('sends every chain to the single rpc when no map is configured', function () {
    Http::fake(['legacy.test/*' => Http::response(['result' => '0x1626ba7e' . str_repeat('0', 56)])]);

    $checker = new RpcContractSignatureChecker('https://legacy.test/v1');

    expect($checker->isValid(EVM_SIGNER, str_repeat('a', 64), EVM_SIG, 'eip155:137'))->toBeTrue();
});

it('reads the chain map from the config', function () {
    Http::fake(['base.test/*' => Http::response(['result' => '0x1626ba7e' . str_repeat('0', 56)])]);
    $this->travelTo('2026-07-30T12:40:00Z');
    config()->set('siwx.domains', ['dapp.expert']);
    config()->set('siwx.eip1271', ['enabled' => true, 'rpc_url' => null, 'rpc_urls' => ['eip155:8453' => 'https://base.test/v1']]);
    Cache::put('siwx:nonce:8Kc2fQ1pXm9Zr4Lt', true, 600);

    $message = str_replace('Chain ID: 1', 'Chain ID: 8453', EVM_MESSAGE);

    expect(app(SiwxVerifier::class)->verify($message, EVM_FOREIGN_SIG)->chainId)->toBe('eip155:8453');
});

it('spends no rpc call on a nonce that was never issued', function () {
    $calls = 0;
    $checker = new class($calls) implements ContractSignatureChecker
    {
        public function __construct(private int &$calls) {}

        public function isValid(string $address, string $hash, string $signature, string $chainId): bool
        {
            $this->calls++;

            return true;
        }
    };

    $this->travelTo('2026-07-30T12:40:00Z');

    $verifier = new SiwxVerifier(
        new SiwxParser,
        new VerifierRegistry([new Eip155Verifier($checker)], ['eip155']),
        app(NonceRepository::class),
        ['dapp.expert'],
        300,
    );

    $code = null;

    try {
        $verifier->verify(EVM_MESSAGE, EVM_FOREIGN_SIG);
    } catch (SiwxException $exception) {
        $code = $exception->code();
    }

    expect($code)->toBe('siwx_invalid_nonce')
        ->and($calls)->toBe(0);
});
