<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Pushword\Core\PushwordCoreBundle;

return static function (ContainerConfigurator $container): void {
    $services = $container->services()
        ->defaults()
            ->autowire()
            ->autoconfigure()
            ->bind('$projectDir', '%kernel.project_dir%')
            ->bind('$mediaDir', '%pw.media_dir%')
    ;
    $services->load('Pushword\Flat\\', __DIR__.'/../../src/')
        ->exclude([
            __DIR__.'/../'.PushwordCoreBundle::SERVICE_AUTOLOAD_EXCLUDE_PATH,
        ]);
};
