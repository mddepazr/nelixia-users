<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directory_audit_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('directory_user_id')->index();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('actor_email')->nullable();
            $table->string('action', 16)->index();
            $table->string('subject_name');
            $table->string('subject_email');
            $table->string('subject_company');
            $table->string('subject_department');
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directory_audit_entries');
    }
};
