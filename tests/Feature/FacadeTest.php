<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Facades\Contacts;

it('pins the facade to the manager, its fake and every action', function (): void {
    expect(Contacts::class)
        ->toDocumentItsRoot()
        ->toBeFakeable()
        ->toReachEveryAction(__DIR__.'/../../src/Actions')
        // confirmVerification($token): the facade frame must not hold the verification token
        ->toRedactSensitiveArguments(methods: 1);
});
