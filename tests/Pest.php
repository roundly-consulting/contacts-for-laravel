<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Testing\ContactExpectations;
use RoundlyConsulting\Contacts\Tests\Fixtures\SwappedContactTestCase;
use RoundlyConsulting\Contacts\Tests\TestCase;

// Explicit paths, not `->in(__DIR__)`: the ModelSwap directory below needs a different
// base case, and a blanket bind would claim it first. `Unit` carries ArchTest.php, which
// needs the app booted — `swappableModelsAreNotFinal` reads the `contacts.model` config
// default, and an arch file is not automatically test-cased.
uses(TestCase::class)->in('Feature', 'Unit');

// The model-swap proof needs `contacts.model` pointed at the host subclass BEFORE the
// providers boot, so it runs on its own base case in its own directory — Pest binds a
// test case per directory, not per file.
uses(SwappedContactTestCase::class)->in('ModelSwap');

ContactExpectations::register();
