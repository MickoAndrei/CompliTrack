<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const INDEXES = [
        'roles' => [
            [['roleId' => 1], ['unique' => true, 'name' => 'roles_role_id_unique']],
        ],
        'users' => [
            [['email' => 1], ['unique' => true, 'name' => 'users_email_unique']],
            [['userCode' => 1], ['unique' => true, 'name' => 'users_user_code_unique']],
            [['roleId' => 1, 'departmentId' => 1], ['name' => 'users_role_department']],
            [['roleId' => 1, 'assignedDepartmentIds' => 1], ['name' => 'users_role_assigned_departments']],
        ],
        'departments' => [
            [['departmentCode' => 1], ['unique' => true, 'name' => 'departments_code_unique']],
        ],
        'archive_documents' => [
            [['fileCode' => 1], ['unique' => true, 'name' => 'archives_file_code_unique']],
            [['departmentId' => 1, 'uploadedByUserId' => 1], ['name' => 'archives_department_uploader']],
            [['checksum' => 1], ['name' => 'archives_checksum']],
            [['referenceStandard' => 1, 'createdAt' => -1], ['name' => 'archives_standard_created']],
        ],
        'compliance_checks' => [
            [['checkCode' => 1], ['unique' => true, 'name' => 'checks_code_unique']],
            [['departmentId' => 1, 'result' => 1, 'correctiveStatus' => 1], ['name' => 'checks_department_result_status']],
            [['auditeeId' => 1, 'correctiveStatus' => 1], ['name' => 'checks_auditee_status']],
            [['auditorId' => 1, 'createdAt' => -1], ['name' => 'checks_auditor_created']],
        ],
        'notifications' => [
            [['recipientUserId' => 1, 'isRead' => 1, 'createdAt' => -1], ['name' => 'notifications_recipient_read_created']],
            [['createdAt' => 1], ['expireAfterSeconds' => 15552000, 'name' => 'notifications_created_ttl']],
            [['dedupeKey' => 1], ['unique' => true, 'sparse' => true, 'name' => 'notifications_dedupe_unique']],
        ],
        'audit_logs' => [
            [['userId' => 1, 'createdAt' => -1], ['name' => 'audit_logs_user_created']],
            [['action' => 1, 'createdAt' => -1], ['name' => 'audit_logs_action_created']],
        ],
        'personal_access_tokens' => [
            [['token' => 1], ['unique' => true, 'name' => 'personal_access_tokens_token_unique']],
            [['tokenable_id' => 1], ['name' => 'personal_access_tokens_tokenable_id']],
        ],
        'password_reset_tokens' => [
            [['email' => 1], ['unique' => true, 'name' => 'password_reset_tokens_email_unique']],
            [['createdAt' => 1], ['expireAfterSeconds' => 600, 'name' => 'password_reset_tokens_created_ttl']],
        ],
    ];

    public function up(): void
    {
        $database = DB::connection('mongodb')->getMongoDB();

        foreach (self::INDEXES as $collectionName => $indexes) {
            $collection = $database->selectCollection($collectionName);

            foreach ($indexes as [$keys, $options]) {
                $collection->createIndex($keys, $options);
            }
        }
    }

    public function down(): void
    {
        $database = DB::connection('mongodb')->getMongoDB();

        foreach (self::INDEXES as $collectionName => $indexes) {
            $collection = $database->selectCollection($collectionName);

            foreach ($indexes as [, $options]) {
                $collection->dropIndex($options['name']);
            }
        }
    }
};
