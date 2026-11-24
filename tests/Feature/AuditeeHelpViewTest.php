<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AuditeeHelpViewTest extends TestCase
{
    public function test_help_retains_guidance_and_links_only_to_auditee_pages(): void
    {
        $user = new User(['fullName' => 'Help test', 'roleId' => 3, 'isActive' => true]);
        $user->setAttribute('_id', new \MongoDB\BSON\ObjectId());
        $this->actingAs($user);
        $html = view('auditee.help')->render();
        foreach (['Prepare for Audit', 'Present Evidence', 'Respond to Findings', 'Understanding N/C/O',
            'Reference Standards', 'Omit restricted titles and identifiers.', 'A file is not mandatory.',
            'Never upload protected QMS materials solely because a request exists.',
            'Neither submitting evidence nor uploading a file changes N/C/O or closes a CAR.',
            'Pending means unanswered, not a compliance result.', 'ISO 9001:2015', 'ARTA',
            'maximum 20 MB', 'closed-app OS push is not supported.', 'No approved external resources listed'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $dom = new \DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new \DOMXPath($dom);
        $this->assertCount(5, $xpath->query('//*[@data-help-topic]'));
        $this->assertCount(5, $xpath->query('//*[@id="prepare"]//ol/li'));
        $this->assertCount(4, $xpath->query('//*[@id="respond"]//ol/li'));
        $this->assertCount(3, $xpath->query('//*[@id="present"]//details'));
        $this->assertCount(3, $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " help-decision ")]'));
        foreach ($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " help-center ")]//a') as $link) {
            $path = parse_url($link->getAttribute('href'), PHP_URL_PATH);
            $this->assertContains($path, ['/auditee/evidence-requests', '/auditee/my-findings', '/auditee/notifications']);
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create($path));
            $this->assertContains('role:3', $route->gatherMiddleware());
        }
    }

    public function test_help_route_keeps_authentication_and_role_guard(): void
    {
        $this->get('/auditee/help')->assertRedirect('/login');
        $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create('/auditee/help'));
        $this->assertContains('auth', $route->gatherMiddleware());
        $this->assertContains('role:3', $route->gatherMiddleware());
    }

    public function test_each_role_has_relevant_guidance_and_guarded_navigation(): void
    {
        foreach ([0 => 'super-admin', 1 => 'admin', 2 => 'auditor', 3 => 'auditee'] as $role => $portal) {
            $user = new User(['fullName' => 'Role help test', 'roleId' => $role, 'isActive' => true]);
            $user->setAttribute('_id', new \MongoDB\BSON\ObjectId());
            $this->actingAs($user);
            $html = view($role === 3 ? 'auditee.help' : 'help.index')->render();
            $route = app('router')->getRoutes()->match(\Illuminate\Http\Request::create('/'.$portal.'/help'));
            $this->assertContains('role:'.$role, $route->gatherMiddleware());
            $this->assertContains('auth', $route->gatherMiddleware());
            $this->assertStringContainsString('href="/'.$portal.'/help"', $html);
            $dom = new \DOMDocument();
            @$dom->loadHTML($html);
            $xpath = new \DOMXPath($dom);
            $this->assertCount(5, $xpath->query('//*[@data-help-topic]'));
            foreach ($xpath->query('//*[@data-help-topic]//a') as $link) {
                $path = parse_url($link->getAttribute('href'), PHP_URL_PATH);
                $this->assertTrue(str_starts_with($path, '/'.$portal.'/') || ($role === 1 && $path === '/audit-plans')
                    || (in_array($role, [1, 2], true) && $path === '/audits'), 'Help links stay within role scope: '.$path);
                app('router')->getRoutes()->match(\Illuminate\Http\Request::create($path));
            }
            if ($role === 0) {
                $this->assertStringContainsString('does not authorize the Super Admin to create compliance checks', $html);
            } elseif ($role === 1) {
                $this->assertStringContainsString('ATL status alone does not grant approval authority', $html);
            } elseif ($role === 2) {
                $this->assertStringContainsString('Base checklist decisions on reviewed evidence', $html);
            }
        }
    }
}
