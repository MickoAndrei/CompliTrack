<?php

namespace App\Support;

class AuditLogFields
{
    public const CATEGORIES = ['authentication', 'accounts', 'organization', 'documents', 'audits', 'permissions', 'security', 'notifications', 'system'];

    // Keep legacy classification and the database filter in the same order.
    private const RULES = [
        ['action', 'permission|QMS.*shar|(?:shar|revok).*QMS|QMS.*(?:responsibility|position)', 'permissions'],
        ['action', 'sign(ed)?[ -]?(in|out)|logged|login|logout|password|authentication', 'authentication'],
        ['entityType', '^authentication$', 'authentication'],
        ['entityType', '^security$', 'security'],
        ['entityType', '^user$', 'accounts'],
        ['entityType', '^(department|college)$', 'organization'],
        ['entityType', '^(archive_document|qms_category)$', 'documents'],
        ['entityType', '^(audit|compliance_check)$', 'audits'],
        ['entityType', '^notification$', 'notifications'],
    ];

    public static function category(string $action, string $entityType): string
    {
        foreach (self::RULES as [$field, $pattern, $category]) {
            if (preg_match('/'.$pattern.'/i', $field === 'action' ? $action : $entityType)) {
                return $category;
            }
        }

        return 'system';
    }

    public static function categoryFilter(string $category): array
    {
        $branches = array_map(fn ($rule) => [
            'case' => ['$regexMatch' => ['input' => ['$ifNull' => ['$'.$rule[0], '']], 'regex' => $rule[1], 'options' => 'i']],
            'then' => $rule[2],
        ], self::RULES);

        return ['$or' => [
            ['category' => $category],
            ['category' => null, '$expr' => ['$eq' => [['$switch' => ['branches' => $branches, 'default' => 'system']], $category]]],
        ]];
    }

    public static function outcomeFilter(string $outcome): array
    {
        return $outcome === 'success'
            ? ['$or' => [['outcome' => 'success'], ['outcome' => null]]]
            : ['outcome' => $outcome];
    }
}
