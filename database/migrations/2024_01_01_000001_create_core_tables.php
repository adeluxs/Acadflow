<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Universities
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('universities')) {
            Schema::create('universities', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('name');
                $table->string('short_name', 50);
                $table->string('code', 10)->unique();
                $table->string('email')->nullable();
                $table->string('phone', 20)->nullable();
                $table->text('address')->nullable();
                $table->string('logo')->nullable();
                $table->string('website')->nullable();
                $table->string('timezone')->default('Africa/Lagos');
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Users
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('university_id')
                    ->nullable()
                    ->constrained('universities')
                    ->nullOnDelete();

                $table->string('student_id')->nullable()->unique();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('password');
                $table->string('phone')->nullable();
                $table->string('avatar')->nullable();

                $table->enum('role', [
                    'super_admin',
                    'university_admin',
                    'department_admin',
                    'lecturer',
                    'student'
                ]);

                $table->boolean('is_active')->default(true);
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login_at')->nullable();

                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Faculties
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('faculties')) {
            Schema::create('faculties', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('university_id')
                    ->constrained('universities')
                    ->cascadeOnDelete();

                $table->string('name');
                $table->string('short_name');
                $table->string('code')->unique();

                $table->foreignId('dean_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Departments
        |--------------------------------------------------------------------------
        */
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();

                $table->foreignId('faculty_id')
                    ->constrained('faculties')
                    ->cascadeOnDelete();

                $table->string('name');
                $table->string('short_name');
                $table->string('code')->unique();

                $table->foreignId('head_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Add department_id to users
        |--------------------------------------------------------------------------
        */
        if (
            Schema::hasTable('users') &&
            Schema::hasTable('departments') &&
            !Schema::hasColumn('users', 'department_id')
        ) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('department_id')
                    ->nullable()
                    ->constrained('departments')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('users') &&
            Schema::hasColumn('users', 'department_id')
        ) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropColumn('department_id');
            });
        }

        Schema::dropIfExists('departments');
        Schema::dropIfExists('faculties');
        Schema::dropIfExists('users');
        Schema::dropIfExists('universities');
    }
};