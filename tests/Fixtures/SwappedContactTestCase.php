<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Tests\Fixtures;

use RoundlyConsulting\Contacts\Tests\TestCase;

/**
 * The suite's base case with `contacts.model` already pointed at the host subclass
 * BEFORE the providers boot.
 *
 * Boot order is the whole point. The suite's existing swap coverage
 * (`tests/Unit/Support/ContactModelTest.php`) sets `contacts.model` inside the test
 * body and asserts a class-string off the resolver — which is exactly the shape the
 * reviews row found to be structurally incapable of catching the bug it was named for.
 * A real host sets this key in `config/contacts.php`, i.e. before boot, so anything the
 * provider hangs on the configured class at boot is decided long before a test body
 * runs.
 *
 * Note `array_merge(parent::configBeforeBoot(), …)`: dropping it would silently discard
 * whatever the base case wires — the same decapitation an un-parented `defineEnvironment()`
 * causes one level up. The parent is empty today; that is not a reason to omit it.
 */
abstract class SwappedContactTestCase extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    protected function configBeforeBoot(): array
    {
        return array_merge(parent::configBeforeBoot(), [
            'contacts.model' => CustomContactModel::class,
        ]);
    }
}
