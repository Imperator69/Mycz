<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('properties')->nullOnDelete();
            $table->string('name');
            $table->string('type', 32);
            $table->string('status', 32)->default('vacant');
            $table->string('street')->nullable();
            $table->string('zip', 16)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('AT');
            $table->string('cadastral_community')->nullable();
            $table->string('land_register_number')->nullable();
            $table->string('plot_number')->nullable();
            $table->string('unit_number')->nullable();
            $table->decimal('area_m2', 10, 2)->nullable();
            $table->decimal('land_area_m2', 12, 2)->nullable();
            $table->string('ownership_share')->nullable();
            $table->unsignedSmallInteger('construction_year')->nullable();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 14, 2)->nullable();
            $table->decimal('purchase_costs', 14, 2)->nullable();
            $table->decimal('assessed_value', 14, 2)->nullable();
            $table->decimal('market_value', 14, 2)->nullable();
            $table->string('drive_folder_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->default('person');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('company')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('iban', 40)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('address')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('leases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_fixed_term')->default(false);
            $table->unsignedTinyInteger('notice_period_months')->nullable();
            $table->decimal('base_rent', 10, 2)->default(0);
            $table->decimal('operating_costs', 10, 2)->default(0);
            $table->decimal('heating_costs', 10, 2)->default(0);
            $table->decimal('other_costs', 10, 2)->default(0);
            $table->decimal('vat_rate', 5, 2)->default(10);
            $table->unsignedTinyInteger('due_day')->default(5);
            $table->date('charges_from');
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->string('deposit_type', 32)->nullable();
            $table->date('deposit_returned_at')->nullable();
            $table->string('index_type', 16)->default('none');
            $table->decimal('index_base_value', 10, 2)->nullable();
            $table->date('index_base_month')->nullable();
            $table->decimal('index_threshold_percent', 5, 2)->nullable();
            $table->date('index_last_adjusted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('rent_charges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lease_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->date('period');
            $table->date('due_date');
            $table->decimal('amount_due', 10, 2);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->string('status', 16)->default('open');
            $table->date('paid_at')->nullable();
            $table->unsignedTinyInteger('reminder_level')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique(['lease_id', 'period']);
            $table->index(['status', 'due_date']);
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 16);
            $table->string('group')->nullable();
            $table->boolean('is_allocatable')->default(false);
            $table->boolean('affects_result')->default(true);
            $table->string('slug')->nullable()->unique();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('documentable');
            $table->string('title');
            $table->string('doc_type', 32);
            $table->string('status', 16)->default('archived');
            $table->unsignedSmallInteger('year')->nullable();
            $table->date('document_date')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('sha256', 64)->nullable()->index();
            $table->string('drive_file_id')->nullable()->index();
            $table->string('drive_web_link')->nullable();
            $table->string('local_path')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('bank');
            $table->string('contract_number')->nullable();
            $table->decimal('principal', 14, 2);
            $table->decimal('interest_rate', 6, 3);
            $table->string('interest_type', 16)->default('fixed');
            $table->date('fixed_until')->nullable();
            $table->date('start_date');
            $table->unsignedSmallInteger('term_months');
            $table->decimal('monthly_payment', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('type', 16);
            $table->date('booked_at');
            $table->string('description');
            $table->string('counterparty')->nullable();
            $table->decimal('amount_net', 12, 2);
            $table->decimal('vat_rate', 5, 2)->default(0);
            $table->decimal('vat_amount', 12, 2)->default(0);
            $table->decimal('amount_gross', 12, 2);
            $table->boolean('is_allocatable')->default(false);
            $table->string('source', 16)->default('manual');
            $table->foreignId('rent_charge_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('statement_id')->nullable()->index();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['booked_at', 'type']);
        });

        Schema::create('statements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_file_id')->nullable()->unique();
            $table->string('source_file_name')->nullable();
            $table->string('issuer')->nullable();
            $table->string('statement_type')->nullable();
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->decimal('total_costs', 12, 2)->nullable();
            $table->decimal('reserve_contribution', 12, 2)->nullable();
            $table->decimal('reserve_balance', 12, 2)->nullable();
            $table->decimal('prepayments', 12, 2)->nullable();
            $table->decimal('balance', 12, 2)->nullable();
            $table->text('summary')->nullable();
            $table->json('warnings')->nullable();
            $table->json('raw')->nullable();
            $table->string('status', 16)->default('pending_review');
            $table->timestamp('booked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->decimal('amount', 12, 2);
            $table->string('suggested_category')->nullable();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_allocatable')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 16);
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'statement_lines', 'statements', 'transactions', 'loans', 'documents', 'categories', 'rent_charges', 'leases', 'tenants', 'properties', 'settings'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
