<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('arbac_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->string('permission');
            $table->string('action'); // 'granted', 'denied'
            $table->string('method'); // 'rbac', 'abac', 'rule_name'
            $table->json('context')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();
            
            $table->index(['tenant_id', 'user_id']);
            $table->index(['permission', 'action']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('arbac_audit_logs');
    }
};
