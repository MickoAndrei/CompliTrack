<?php
namespace Tests\Unit;

use App\Services\QmsCycleFolders;
use Tests\TestCase;

class QmsCycleFoldersTest extends TestCase
{
    public function test_outline_follows_the_documented_structure_without_creating_records(): void
    {
        config(['qms.currentAuditCycle' => 'Internal Quality Audit August 2026']);
        $folders = collect(app(QmsCycleFolders::class)->navigation([], ['Information Technology', 'Office of the Dean']));
        $root = $folders->firstWhere('parentId', null);
        $this->assertSame('Internal Quality Audit August 2026', $root['name']);
        $this->assertTrue($root['currentCycle']);
        $campus = $folders->firstWhere('parentId', $root['_id']);
        $this->assertSame('USTP Claveria', $campus['name']);
        $this->assertSame(['01 Audit Administration', '02 Auditor Workspaces', '03 Department and Office Evidence',
            '04 Audit Results and CARs', '05 Consolidated Reports'], $folders->where('parentId', $campus['_id'])->pluck('name')->values()->all());
        $this->assertCount(2, $folders->where('name', 'Information Technology'));
        $this->assertCount(2, $folders->where('name', 'Office of the Dean'));
        foreach ($folders as $folder) {
            $this->assertTrue($folder['permissions']['view']);
            $this->assertEmpty(array_filter($folder['permissions'], fn ($allowed, $action) => $allowed && $action !== 'view', ARRAY_FILTER_USE_BOTH));
        }
    }

    public function test_saved_current_and_past_cycles_keep_their_hierarchy_and_exclude_other_folders(): void
    {
        config(['qms.currentAuditCycle' => 'Internal Quality Audit August 2026']);
        $saved = [
            ['_id' => 'old', 'name' => 'Internal Quality Audit August 2025', 'parentId' => null],
            ['_id' => 'current', 'name' => 'Internal Quality Audit August 2026', 'parentId' => null],
            ['_id' => 'child', 'name' => 'USTP Claveria', 'parentId' => 'old'],
            ['_id' => 'unrelated', 'name' => 'Department documents', 'parentId' => null],
        ];
        $folders = app(QmsCycleFolders::class)->navigation($saved, []);
        $this->assertSame(['old', 'current', 'child'], array_column($folders, '_id'));
        $this->assertSame([false, true, false], array_column($folders, 'currentCycle'));
        $this->assertSame('old', $folders[2]['parentId']);
    }
}
