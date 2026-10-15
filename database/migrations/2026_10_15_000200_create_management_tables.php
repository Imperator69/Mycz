<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('company')->nullable();
            $table->string('role')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_property', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->primary(['contact_id', 'property_id']);
        });

        Schema::create('insurance_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('insurer');
            $table->string('policy_number')->nullable();
            $table->string('line', 32);
            $table->decimal('coverage_amount', 14, 2)->nullable();
            $table->decimal('deductible', 10, 2)->nullable();
            $table->decimal('premium', 10, 2)->nullable();
            $table->string('payment_interval', 16)->default('annual');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('auto_renews')->default(true);
            $table->unsignedTinyInteger('notice_period_months')->nullable();
            $table->string('agent')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('insurance_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('insurance_policy_id')->constrained()->cascadeOnDelete();
            $table->date('incident_date');
            $table->string('claim_number')->nullable();
            $table->text('description');
            $table->decimal('damage_amount', 12, 2)->nullable();
            $table->decimal('payout_amount', 12, 2)->nullable();
            $table->string('status', 16)->default('reported');
            $table->timestamps();
        });

        Schema::create('contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('provider');
            $table->string('contract_number')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('auto_renews')->default(true);
            $table->unsignedTinyInteger('notice_period_months')->nullable();
            $table->decimal('cost_amount', 10, 2)->nullable();
            $table->string('cost_interval', 16)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('maintenance_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->unsignedSmallInteger('interval_months')->nullable();
            $table->date('last_done_at')->nullable();
            $table->date('next_due_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('meters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('number')->nullable();
            $table->string('unit', 16)->default('kWh');
            $table->string('location')->nullable();
            $table->timestamps();
        });

        Schema::create('meter_readings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meter_id')->constrained()->cascadeOnDelete();
            $table->date('read_at');
            $table->decimal('value', 14, 3);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('insurance_claim_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 16)->default('open');
            $table->string('priority', 16)->default('normal');
            $table->date('reported_at');
            $table->date('resolved_at')->nullable();
            $table->decimal('cost_estimate', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('deadlines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('property_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->date('due_date');
            $table->unsignedSmallInteger('repeat_months')->nullable();
            $table->date('done_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['deadlines', 'tickets', 'meter_readings', 'meters', 'maintenance_tasks', 'contracts', 'insurance_claims', 'insurance_policies', 'contact_property', 'contacts'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
