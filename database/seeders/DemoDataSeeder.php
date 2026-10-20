<?php

namespace Database\Seeders;

use App\Models\ComplianceCheck;
use App\Models\Department;
use App\Models\Notification;
use App\Models\User;
use App\Services\CounterService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            'DEMO-IT' => Department::firstOrCreate(
                ['departmentCode' => 'DEMO-IT'],
                [
                    'departmentName' => '[DEMO] Information Technology',
                    'collegeName' => 'CET',
                    'campusName' => 'Claveria',
                    'headUserId' => null,
                    'isActive' => true,
                ],
            ),
            'DEMO-ENG' => Department::firstOrCreate(
                ['departmentCode' => 'DEMO-ENG'],
                [
                    'departmentName' => '[DEMO] Engineering',
                    'collegeName' => 'CET',
                    'campusName' => 'Claveria',
                    'headUserId' => null,
                    'isActive' => true,
                ],
            ),
        ];

        $accountDefinitions = [
            'demo.admin@example.test' => [
                'name' => '[DEMO] CET Administrator',
                'roleId' => 1,
                'department' => 'DEMO-IT',
                'officeName' => null,
            ],
            'demo.auditor.it@example.test' => [
                'name' => '[DEMO] IT Auditor',
                'roleId' => 2,
                'department' => 'DEMO-IT',
                'officeName' => null,
            ],
            'demo.auditee.it@example.test' => [
                'name' => '[DEMO] IT Auditee',
                'roleId' => 3,
                'department' => 'DEMO-IT',
                'officeName' => '[DEMO] IT Office',
            ],
            'demo.auditor.eng@example.test' => [
                'name' => '[DEMO] Engineering Auditor',
                'roleId' => 2,
                'department' => 'DEMO-ENG',
                'officeName' => null,
            ],
            'demo.auditee.eng@example.test' => [
                'name' => '[DEMO] Engineering Auditee',
                'roleId' => 3,
                'department' => 'DEMO-ENG',
                'officeName' => '[DEMO] Engineering Office',
            ],
        ];

        $users = [];
        $generatedCredentials = [];
        $counter = app(CounterService::class);

        foreach ($accountDefinitions as $email => $account) {
            $department = $departments[$account['department']];
            $password = Str::random(32);
            $roleSlug = [1 => 'admin', 2 => 'auditor', 3 => 'auditee'][$account['roleId']];
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'userCode' => $counter->next(match ($account['roleId']) {
                        1 => 'ADM',
                        2 => 'AUD',
                        3 => 'ATE',
                    }),
                    'fullName' => $account['name'],
                    'password' => Hash::make($password, ['rounds' => 12]),
                    'roleId' => $account['roleId'],
                    'roleSlug' => $roleSlug,
                    'departmentId' => $department->getKey(),
                    'assignedDepartmentIds' => $account['roleId'] === 2 ? [$department->getKey()] : [],
                    'officeName' => $account['officeName'],
                    'isActive' => true,
                ],
            );

            $users[$email] = $user;
            if ($user->wasRecentlyCreated) {
                $generatedCredentials[$email] = $password;
            }
        }

        $checkDefinitions = [
            [
                'code' => 'DEMO-CHK-IT-COMPLIANT',
                'department' => 'DEMO-IT',
                'auditor' => 'demo.auditor.it@example.test',
                'auditee' => 'demo.auditee.it@example.test',
                'result' => 'compliant',
                'status' => null,
                'remarks' => '[DEMO] Required evidence was reviewed and meets the selected standard.',
            ],
            [
                'code' => 'DEMO-CHK-IT-OPEN',
                'department' => 'DEMO-IT',
                'auditor' => 'demo.auditor.it@example.test',
                'auditee' => 'demo.auditee.it@example.test',
                'result' => 'non_compliant',
                'status' => 'open',
                'remarks' => '[DEMO] A current approved procedure needs to be uploaded.',
            ],
            [
                'code' => 'DEMO-CHK-ENG-PROGRESS',
                'department' => 'DEMO-ENG',
                'auditor' => 'demo.auditor.eng@example.test',
                'auditee' => 'demo.auditee.eng@example.test',
                'result' => 'non_compliant',
                'status' => 'in_progress',
                'remarks' => '[DEMO] Corrective evidence is being prepared for review.',
            ],
            [
                'code' => 'DEMO-CHK-ENG-CLOSED',
                'department' => 'DEMO-ENG',
                'auditor' => 'demo.auditor.eng@example.test',
                'auditee' => 'demo.auditee.eng@example.test',
                'result' => 'non_compliant',
                'status' => 'closed',
                'remarks' => '[DEMO] Corrective evidence was reviewed and accepted.',
            ],
        ];

        $checks = [];
        foreach ($checkDefinitions as $definition) {
            $auditor = $users[$definition['auditor']];
            $status = $definition['status'];
            $check = ComplianceCheck::firstOrCreate(
                ['checkCode' => $definition['code']],
                [
                    'departmentId' => $departments[$definition['department']]->getKey(),
                    'auditorId' => $auditor->getKey(),
                    'auditeeId' => $users[$definition['auditee']]->getKey(),
                    'referenceStandard' => 'ISO9001-9.2',
                    'result' => $definition['result'],
                    'remarks' => $definition['remarks'],
                    'correctiveStatus' => $status,
                    'statusHistory' => $status === null ? [] : [[
                        'status' => $status,
                        'changedByUserId' => $auditor->getKey(),
                        'remarks' => '[DEMO] Sample seeded status.',
                        'timestamp' => now()->utc(),
                    ]],
                    'linkedArchiveIds' => [],
                    'dueDate' => $status !== null ? now()->utc()->addDays(7) : null,
                    'closedAt' => $status === 'closed' ? now()->utc() : null,
                ],
            );

            $checks[$definition['code']] = $check;
        }

        foreach ([
            [
                'key' => 'demo-it-open-finding',
                'recipient' => 'demo.auditee.it@example.test',
                'sender' => 'demo.auditor.it@example.test',
                'check' => 'DEMO-CHK-IT-OPEN',
                'title' => '[DEMO] Corrective action required',
                'message' => '[DEMO] Please upload the approved procedure for your open compliance finding.',
                'type' => 'corrective_action_opened',
                'isRead' => false,
            ],
            [
                'key' => 'demo-eng-evidence-review',
                'recipient' => 'demo.auditor.eng@example.test',
                'sender' => 'demo.auditee.eng@example.test',
                'check' => 'DEMO-CHK-ENG-PROGRESS',
                'title' => '[DEMO] Evidence ready for review',
                'message' => '[DEMO] Corrective evidence has been submitted for the in-progress finding.',
                'type' => 'evidence_uploaded',
                'isRead' => false,
            ],
            [
                'key' => 'demo-eng-action-closed',
                'recipient' => 'demo.auditee.eng@example.test',
                'sender' => 'demo.auditor.eng@example.test',
                'check' => 'DEMO-CHK-ENG-CLOSED',
                'title' => '[DEMO] Corrective action closed',
                'message' => '[DEMO] Your corrective evidence was reviewed and the action is closed.',
                'type' => 'corrective_action_closed',
                'isRead' => true,
            ],
        ] as $notification) {
            Notification::firstOrCreate(
                ['dedupeKey' => $notification['key']],
                [
                    'recipientUserId' => $users[$notification['recipient']]->getKey(),
                    'senderUserId' => $users[$notification['sender']]->getKey(),
                    'type' => $notification['type'],
                    'title' => $notification['title'],
                    'message' => $notification['message'],
                    'relatedCheckId' => $checks[$notification['check']]->getKey(),
                    'isRead' => $notification['isRead'],
                    'fcmSent' => false,
                ],
            );
        }

        if ($this->command && $generatedCredentials !== []) {
            $this->command->warn('New fictional demo account credentials (save them now; passwords are not stored in plaintext):');
            foreach ($generatedCredentials as $email => $password) {
                $this->command->line("{$email}  {$password}");
            }
        } elseif ($this->command) {
            $this->command->info('Demo accounts already exist; their passwords were left unchanged.');
        }
    }
}
