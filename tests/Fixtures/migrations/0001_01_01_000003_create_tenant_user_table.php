<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_user', function (Blueprint $blueprint): void {
            $blueprint->foreignId('tenant_id')
                ->constrained('tenants')->cascadeOnDelete();
            $blueprint->foreignId('user_id')
                ->constrained('users')->cascadeOnDelete();

            $blueprint->primary(['tenant_id', 'user_id']);
        });
    }
};
