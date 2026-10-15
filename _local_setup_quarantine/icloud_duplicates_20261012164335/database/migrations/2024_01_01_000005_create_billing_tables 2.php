<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Subscriptions
        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('university_id')->constrained('universities')->cascadeOnDelete();
                $table->string('plan_name', 100);
                $table->enum('billing_model', ['institution', 'student', 'hybrid']);
                $table->decimal('price_per_student', 10, 2);
                $table->integer('grace_days')->default(7);
                $table->date('start_date');
                $table->date('end_date');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Invoices
        if (!Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
                $table->decimal('amount', 10, 2);
                $table->enum('status', ['pending', 'paid', 'overdue', 'waived'])->default('pending');
                $table->date('due_date');
                $table->timestamp('paid_at')->nullable();
                $table->string('payment_method', 50)->nullable();
                $table->string('transaction_ref', 100)->nullable();
                $table->timestamps();
            });
        }

        // Payments
        if (!Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('amount', 10, 2);
                $table->enum('payment_method', ['bank_transfer', 'card', 'wallet']);
                $table->string('transaction_ref', 100)->unique();
                $table->string('reference', 100)->nullable();
                $table->enum('status', ['pending', 'verified', 'failed'])->default('pending');
                $table->timestamp('verified_at')->nullable();
                $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        // Document Templates
        if (!Schema::hasTable('document_templates')) {
            Schema::create('document_templates', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->enum('type', ['project', 'siwes', 'group', 'seminar']);
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('template_path');
                $table->boolean('is_default')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Generated Documents
        if (!Schema::hasTable('generated_documents')) {
            Schema::create('generated_documents', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('submission_id')->nullable()->constrained('submissions')->nullOnDelete();
                $table->foreignId('template_id')->constrained('document_templates')->cascadeOnDelete();
                $table->string('title');
                $table->string('file_path');
                $table->bigInteger('file_size');
                $table->enum('status', ['processing', 'ready', 'failed'])->default('processing');
                $table->timestamps();
            });
        }

        // Notifications
        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique()->index();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 100);
                $table->string('title');
                $table->text('message');
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }

        // Notification Settings
        if (!Schema::hasTable('notification_settings')) {
            Schema::create('notification_settings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('email_enabled')->default(true);
                $table->boolean('push_enabled')->default(true);
                $table->boolean('submission_notifications')->default(true);
                $table->boolean('grade_notifications')->default(true);
                $table->boolean('attendance_notifications')->default(true);
                $table->boolean('billing_notifications')->default(true);
                $table->timestamps();
            });
        }

        // Audit Logs
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 100);
                $table->string('entity_type');
                $table->bigInteger('entity_id');
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->index(['entity_type', 'entity_id']);
                $table->index('user_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('notification_settings');
        Schema::dropIfExists('notifications');
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('document_templates');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
    }
};
