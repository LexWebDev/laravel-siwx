<?php

use Illuminate\Support\Facades\Http;
use LexWebDev\Siwx\Contracts\ContractSignatureChecker;
use LexWebDev\Siwx\RpcContractSignatureChecker;
use LexWebDev\Siwx\SiwxParser;
use LexWebDev\Siwx\Verifiers\Eip155Verifier;

it('falls back to the contract checker when recovery does not match', function () {
    $checker = new class implements ContractSignatureChecker {
        public function isValid(string $address, string $hash, string $signature): bool
        {
            return true;
        }
    };

    $message = (new SiwxParser)->parse(EVM_MESSAGE);

    expect((new Eip155Verifier($checker))->verify($message, EVM_FOREIGN_SIG))->toBeTrue();
});

it('rejects when the contract checker declines', function () {
    $checker = new class implements ContractSignatureChecker {
        public function isValid(string $address, string $hash, string $signature): bool
        {
            return false;
        }
    };

    $message = (new SiwxParser)->parse(EVM_MESSAGE);

    expect((new Eip155Verifier($checker))->verify($message, EVM_FOREIGN_SIG))->toBeFalse();
});

it('does not call the checker when recovery already matches', function () {
    $calls = 0;
    $checker = new class($calls) implements ContractSignatureChecker {
        public function __construct(private int &$calls) {}

        public function isValid(string $address, string $hash, string $signature): bool
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

    expect($checker->isValid(EVM_SIGNER, str_repeat('a', 64), EVM_SIG))->toBeTrue();
});

it('treats any other magic value as invalid', function () {
    Http::fake(['rpc.test/*' => Http::response(['result' => '0x' . str_repeat('0', 64)])]);

    $checker = new RpcContractSignatureChecker('https://rpc.test/v1');

    expect($checker->isValid(EVM_SIGNER, str_repeat('a', 64), EVM_SIG))->toBeFalse();
});

it('treats an rpc failure as invalid rather than throwing', function () {
    Http::fake(['rpc.test/*' => Http::response(status: 500)]);

    $checker = new RpcContractSignatureChecker('https://rpc.test/v1');

    expect($checker->isValid(EVM_SIGNER, str_repeat('a', 64), EVM_SIG))->toBeFalse();
});
