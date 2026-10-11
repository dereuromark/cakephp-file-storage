<?php declare(strict_types=1);

namespace FileStorage\Test\TestCase\View;

use Cake\Datasource\Paging\PaginatedResultSet;
use Cake\Http\ServerRequest;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use Cake\View\View;

class PaginationElementTest extends TestCase
{
    public function testRendersAllNavigationControls(): void
    {
        $this->loadPlugins(['FileStorage' => ['bootstrap' => false, 'routes' => false]]);
        $request = (new ServerRequest())->withParam('controller', 'FileStorage')->withParam('action', 'index');
        Router::createRouteBuilder('/')->fallbacks();
        Router::setRequest($request);
        $view = new View($request);
        $view->loadHelper('Paginator');
        $view->Paginator->setPaginated(new PaginatedResultSet([], [
            'alias' => 'FileStorage',
            'currentPage' => 2,
            'pageCount' => 4,
            'totalCount' => 40,
            'count' => 10,
            'perPage' => 10,
            'current' => 10,
            'start' => 11,
            'end' => 20,
            'hasPrevPage' => true,
            'hasNextPage' => true,
        ]));

        $html = $view->element('FileStorage.pagination');

        foreach (['angle-double-left', 'angle-left', 'angle-right', 'angle-double-right'] as $icon) {
            $this->assertStringContainsString('<i class="fas fa-' . $icon . '"></i>', $html);
        }
        $this->assertStringNotContainsString("'escapeAttributes' =>", $html);
        $this->assertStringNotContainsString('&lt;i', $html);
    }
}
