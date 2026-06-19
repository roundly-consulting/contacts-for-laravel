<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = config('contacts.table');
        $table = is_string($table) && $table !== '' ? $table : 'contacts';

        Schema::create($table, function (Blueprint $table): void {
            $table->id();
            $table->nullableMorphs('owner');
            $table->string('type')->default('custom')->index();
            $table->string('name');
            $table->string('value')->nullable();
            $table->string('label')->nullable();
            $table->string('category')->nullable()->index();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('verified_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_type', 'owner_id', 'type'], 'contacts_owner_type_idx');
            $table->index(['owner_type', 'owner_id', 'type', 'is_primary'], 'contacts_owner_type_primary_idx');
        });
    }
};
