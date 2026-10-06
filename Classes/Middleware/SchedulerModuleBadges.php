<?php

declare(strict_types=1);

namespace MaikSchneider\SchedulerAsCode\Middleware;

use MaikSchneider\SchedulerAsCode\Persistence\ManagedTaskRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Page\PageRenderer;

/**
 * Marks file-managed tasks in the Scheduler module. The core offers no hook into the task list,
 * and overriding its template would mean copying it per TYPO3 version, so the badges are added
 * client side to the rows the core already tags with data-task-id.
 */
final readonly class SchedulerModuleBadges implements MiddlewareInterface
{
    private const LABELS = 'LLL:EXT:scheduler_as_code/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private ManagedTaskRepository $managedTaskRepository,
        private PageRenderer $pageRenderer,
        private LanguageServiceFactory $languageServiceFactory,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $route = $request->getAttribute('route');
        if ($route instanceof Route && in_array($route->getOption('_identifier'), ['scheduler', 'scheduler_manage'], true)) {
            $languageService = $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER'] ?? null);
            $this->pageRenderer->addInlineSettingArray('schedulerAsCode', [
                'tasks' => $this->managedTaskRepository->findStates(),
                'labels' => [
                    'managed' => $languageService->sL(self::LABELS . 'badge.managed'),
                    'orphaned' => $languageService->sL(self::LABELS . 'badge.orphaned'),
                    'orphanedDescription' => $languageService->sL(self::LABELS . 'badge.orphaned.description'),
                ],
            ]);
            $this->pageRenderer->loadJavaScriptModule('@maikschneider/scheduler-as-code/managed-badges.js');
        }
        return $handler->handle($request);
    }
}
