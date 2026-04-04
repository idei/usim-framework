<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('usim_text_values', 'needs_review')) {
            Schema::table('usim_text_values', function (Blueprint $table): void {
                $table->boolean('needs_review')->default(false)->after('text_value');
            });
        }

        if (Schema::hasColumn('usim_text_keys', 'needs_review')) {
            $keyNeedsReview = DB::table('usim_text_keys')
                ->select(['id', 'needs_review'])
                ->get()
                ->keyBy('id');

            DB::table('usim_text_values')
                ->select(['id', 'text_key_id'])
                ->orderBy('id')
                ->chunkById(500, function ($values) use ($keyNeedsReview): void {
                    foreach ($values as $value) {
                        $needsReview = (bool) ($keyNeedsReview[$value->text_key_id]->needs_review ?? false);

                        DB::table('usim_text_values')
                            ->where('id', $value->id)
                            ->update(['needs_review' => $needsReview]);
                    }
                });

            Schema::table('usim_text_keys', function (Blueprint $table): void {
                $table->dropColumn('needs_review');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('usim_text_keys', 'needs_review')) {
            Schema::table('usim_text_keys', function (Blueprint $table): void {
                $table->boolean('needs_review')->default(false)->after('group');
            });
        }

        $keyIdsThatNeedReview = DB::table('usim_text_values')
            ->where('needs_review', true)
            ->distinct()
            ->pluck('text_key_id')
            ->all();

        if ($keyIdsThatNeedReview !== []) {
            DB::table('usim_text_keys')
                ->whereIn('id', $keyIdsThatNeedReview)
                ->update(['needs_review' => true]);
        }

        if (Schema::hasColumn('usim_text_values', 'needs_review')) {
            Schema::table('usim_text_values', function (Blueprint $table): void {
                $table->dropColumn('needs_review');
            });
        }
    }
};
