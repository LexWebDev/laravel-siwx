<?php

it('boots the package', function () {
    expect(config('siwx.nonce_ttl'))->toBe(600)
        ->and(config('siwx.namespaces'))->toBe(['eip155', 'solana']);
});

it('has the crypto extensions it needs', function () {
    expect(extension_loaded('sodium'))->toBeTrue()
        ->and(extension_loaded('gmp') || extension_loaded('bcmath'))->toBeTrue();
});
