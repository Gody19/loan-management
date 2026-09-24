<?php

use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected const INDEX_NAME = 'audit_logs_organization_id_index';

    protected const FOREIGN_NAME = 'audit_logs_organization_id_foreign';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('audit_logs') && ! Schema::hasColumn('audit_logs', 'organization_id')) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('user_id');
            });

            if (! Schema::hasIndex('audit_logs', self::INDEX_NAME)) {
                Schema::table('audit_logs', function (Blueprint $table) {
                    $table->index('organization_id');
                });
            }

            // SQLite cannot add a foreign key to an existing table, so the
            // constraint is a non-SQLite concern (MySQL receives it for real).
            if (Schema::getConnection()->getDriverName() !== 'sqlite') {
                Schema::table('audit_logs', function (Blueprint $table) {
                    $table->foreign('organization_id')
                        ->references('id')
                        ->on('organizations')
                        ->nullOnDelete();
                });
            }
        }

        $this->backfill();
    }

    /**
     * Backfill organization_id for existing audit logs.
     *
     * The organization is resolved from, in order of preference:
     *  - an Organization auditable (its own id),
     *  - a User auditable (their single organization),
     *  - an auditable model exposing organization_id,
     *  - the user performing the action when linked to exactly one organization.
     * Otherwise the column stays NULL (platform-level or ambiguous events).
     */
    public function backfill(): void
    {
        if (! Schema::hasTable('audit_logs') || ! Schema::hasColumn('audit_logs', 'organization_id')) {
            return;
        }

        if (! Schema::hasTable('organizations')) {
            return;
        }

        DB::table('audit_logs')
            ->whereNull('organization_id')
            ->chunkById(200, function ($logs) {
                foreach ($logs as $log) {
                    $organizationId = $this->resolveOrganizationId($log);

                    if ($organizationId !== null) {
                        DB::table('audit_logs')
                            ->where('id', $log->id)
                            ->update(['organization_id' => $organizationId]);
                    }
                }
            });
    }

    /**
     * Resolve the most likely organization for a single audit log row.
     *
     * @param  object  $log  Audit log row from the query builder.
     */
    protected function resolveOrganizationId(object $log): ?int
    {
        if (! empty($log->auditable_type)) {
            if (is_a($log->auditable_type, Organization::class, true)) {
                return (int) $log->auditable_id;
            }

            if (is_a($log->auditable_type, User::class, true)) {
                $user = User::find($log->auditable_id);

                if ($user) {
                    return $this->organizationIdForUser($user);
                }
            }

            if (is_subclass_of($log->auditable_type, Model::class)) {
                $model = $log->auditable_type::query()->find($log->auditable_id);

                if ($model && ! empty($model->organization_id)) {
                    return (int) $model->organization_id;
                }
            }
        }

        if (! empty($log->user_id)) {
            $user = User::find($log->user_id);

            if ($user) {
                return $this->organizationIdForUser($user);
            }
        }

        return null;
    }

    /**
     * Return the organization id only when the user belongs to exactly one.
     */
    protected function organizationIdForUser(User $user): ?int
    {
        $ids = $user->organizations()->pluck('organizations.id')->all();

        return count($ids) === 1 ? (int) reset($ids) : null;
    }

    /**
     * Reverse the migrations.
     *
     * Order matters: SQLite refuses to drop an indexed column, and MySQL
     * refuses to drop a foreign key backing index while the key exists.
     */
    public function down(): void
    {
        if (! Schema::hasTable('audit_logs') || ! Schema::hasColumn('audit_logs', 'organization_id')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
            });
        }

        if (Schema::hasIndex('audit_logs', self::INDEX_NAME)) {
            Schema::table('audit_logs', function (Blueprint $table) {
                $table->dropIndex(['organization_id']);
            });
        }

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('organization_id');
        });
    }
};