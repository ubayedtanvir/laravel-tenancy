<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->string('slug')->unique();
            $blueprint->string('name')->nullable();
            $blueprint->timestamps();
        });
    }
};
