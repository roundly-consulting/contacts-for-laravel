<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The host-owned fixture table contact owners live in. It is the suite's stand-in for
 * an application's `users` table — contacts, addresses and connections all point at it
 * polymorphically, so it carries no foreign key and nothing points back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
        });
    }
};
