<?php

declare(strict_types=1);

namespace RoundlyConsulting\Contacts\Tests\Models;

use RoundlyConsulting\Contacts\Models\Contact;

/**
 * A host-app style extension of the packaged model, as `contacts.model`
 * documents. Its existence also pins that Contact stays non-final.
 */
final class CustomContact extends Contact {}
