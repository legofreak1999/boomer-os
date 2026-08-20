<?php

use App\Models\StorageFolder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('storage_files', function (Blueprint $table) {
            $table->foreignId('folder_id')->nullable()->after('id')->constrained('storage_folders')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('storage_files', function (Blueprint $table) {
            $table->dropForeignIdFor(StorageFolder::class);
            $table->dropColumn('folder_id');
        });
    }
};
