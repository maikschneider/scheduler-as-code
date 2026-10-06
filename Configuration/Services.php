<?php

declare(strict_types=1);

use MaikSchneider\SchedulerAsCode\Persistence\Storage\Typo3v13TaskStorage;
use MaikSchneider\SchedulerAsCode\Persistence\Storage\Typo3v14TaskStorage;
use MaikSchneider\SchedulerAsCode\Persistence\TaskStorageInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Core\Information\Typo3Version;

return static function (ContainerConfigurator $configurator): void {
    $storage = (new Typo3Version())->getMajorVersion() >= 14 ? Typo3v14TaskStorage::class : Typo3v13TaskStorage::class;
    $services = $configurator->services();
    $services->set($storage)->autowire();
    $services->alias(TaskStorageInterface::class, $storage);
};
