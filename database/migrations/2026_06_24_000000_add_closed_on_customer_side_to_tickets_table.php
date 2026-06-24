<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->boolean('closed_on_customer_side')->default(false)->after('status');
            $table->timestamp('closed_on_customer_side_at')->nullable()->after('closed_on_customer_side');
            $table->index('closed_on_customer_side');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex(['closed_on_customer_side']);
            $table->dropColumn(['closed_on_customer_side', 'closed_on_customer_side_at']);
        });
    }
};
