<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Tests\Unit\Middleware;

use MaikSchneider\SchedulerAsCode\Middleware\SchedulerModuleBadges;
use MaikSchneider\SchedulerAsCode\Service\TaskStateResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class SchedulerModuleBadgesTest extends UnitTestCase
{
    private TaskStateResolver&Stub $taskStateResolver;
    private PageRenderer&MockObject $pageRenderer;
    private SchedulerModuleBadges $subject;
    private ResponseInterface $response;
    private RequestHandlerInterface $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->taskStateResolver = self::createStub(TaskStateResolver::class);
        $this->pageRenderer = $this->createMock(PageRenderer::class);
        $languageService = self::createStub(LanguageService::class);
        $languageService->method('sL')->willReturnCallback(static fn (string $label): string => 'translated ' . substr($label, strrpos($label, ':') + 1));
        $languageServiceFactory = self::createStub(LanguageServiceFactory::class);
        $languageServiceFactory->method('createFromUserPreferences')->willReturn($languageService);
        $this->subject = new SchedulerModuleBadges($this->taskStateResolver, $this->pageRenderer, $languageServiceFactory);

        $this->response = new Response();
        $handler = self::createStub(RequestHandlerInterface::class);
        $handler->method('handle')->willReturn($this->response);
        $this->handler = $handler;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function schedulerRouteDataProvider(): array
    {
        return [
            'TYPO3 13' => ['scheduler_manage'],
            'TYPO3 14' => ['scheduler'],
        ];
    }

    #[Test]
    #[DataProvider('schedulerRouteDataProvider')]
    public function schedulerModuleGetsTheTaskStatesAndTheBadgeScript(string $routeIdentifier): void
    {
        $states = [7 => ['identifier' => 'cleanup', 'source' => 'config/scheduler/cleanup.yaml', 'orphaned' => false, 'stale' => true]];
        $this->taskStateResolver->method('getStates')->willReturn($states);

        $this->pageRenderer->expects(self::once())->method('addInlineSettingArray')->with('schedulerAsCode', [
            'tasks' => $states,
            'labels' => [
                'managed' => 'translated badge.managed',
                'orphaned' => 'translated badge.orphaned',
                'orphanedDescription' => 'translated badge.orphaned.description',
                'stale' => 'translated badge.stale',
                'staleDescription' => 'translated badge.stale.description',
            ],
        ]);
        $this->pageRenderer->expects(self::once())->method('loadJavaScriptModule')
            ->with('@maikschneider/scheduler-as-code/managed-badges.js');

        $response = $this->subject->process($this->request($routeIdentifier), $this->handler);

        self::assertSame($this->response, $response);
    }

    #[Test]
    public function otherModulesAreLeftAlone(): void
    {
        $this->pageRenderer->expects(self::never())->method('addInlineSettingArray');
        $this->pageRenderer->expects(self::never())->method('loadJavaScriptModule');

        self::assertSame($this->response, $this->subject->process($this->request('web_layout'), $this->handler));
    }

    #[Test]
    public function requestWithoutRouteIsLeftAlone(): void
    {
        $this->pageRenderer->expects(self::never())->method('addInlineSettingArray');

        self::assertSame($this->response, $this->subject->process(new ServerRequest('https://example.com/typo3/'), $this->handler));
    }

    private function request(string $routeIdentifier): ServerRequestInterface
    {
        return (new ServerRequest('https://example.com/typo3/module/' . $routeIdentifier))
            ->withAttribute('route', new Route('/module/' . $routeIdentifier, ['_identifier' => $routeIdentifier]));
    }
}
