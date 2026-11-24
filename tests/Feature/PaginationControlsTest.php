<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PaginationControlsTest extends TestCase
{
    public function test_server_selector_keeps_filters_and_returns_to_first_page(): void
    {
        $this->app->instance('request', Request::create('/audits?search=engineering&status=ready&page=3&perPage=5'));
        $paginator = new LengthAwarePaginator([], 16, 5, 3, ['path' => '/audits']);
        $html = Blade::render('<x-server-pagination :paginator="$paginator" />', compact('paginator'));
        $this->assertStringContainsString('search=engineering&amp;status=ready&amp;page=1&amp;perPage=20', $html);
        $this->assertStringContainsString('Page 3 of 4', $html);
        $this->assertLessThan(strpos($html, '>Previous<'), strpos($html, 'Rows per page'));
    }

    public function test_single_page_still_offers_page_size_choices(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 12, 1);
        $html = Blade::render('<x-server-pagination :paginator="$paginator" />', compact('paginator'));
        $this->assertStringContainsString('Rows per page', $html);
        $this->assertSame(2, substr_count($html, 'aria-disabled="true"'));
        $this->assertStringContainsString('selected>12</option>', $html);
    }
}
