<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_posts', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('account_id');
            $blueprint->string('title')->nullable();
            $blueprint->timestamps();
        });
    }
};
