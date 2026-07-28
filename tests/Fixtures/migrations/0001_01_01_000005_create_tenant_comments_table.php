<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $blueprint): void {
            $blueprint->id();
            $blueprint->foreignId('tenant_id');
            $blueprint->foreignId('post_id');
            $blueprint->string('body')->nullable();
            $blueprint->timestamps();
        });
    }
};
