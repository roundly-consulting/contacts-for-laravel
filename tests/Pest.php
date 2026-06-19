<?php

declare(strict_types=1);

use RoundlyConsulting\Contacts\Testing\ContactExpectations;
use RoundlyConsulting\Contacts\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

ContactExpectations::register();
