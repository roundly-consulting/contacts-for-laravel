<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\PackageToolkit\Enums\KeyType;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('contacts.table');
        $table = is_string($table) && $table !== '' ? $table : 'contacts';

        $keyType = KeyType::fromConfig('contacts.key_type');

        Schema::create($table, function (Blueprint $table) use ($keyType): void {
            $table->id();
            $table->morphKey('owner', $keyType, nullable: true);
            $table->string('type')->default('custom')->index();
            $table->string('name');
            $table->string('value')->nullable();
            $table->string('label')->nullable();
            $table->string('category')->nullable()->index();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_token')->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id', 'type'], 'contacts_owner_type_idx');
            $table->index(['owner_type', 'owner_id', 'type', 'is_primary'], 'contacts_owner_type_primary_idx');
        });
    }
};
