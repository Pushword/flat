<?php

namespace Pushword\Flat\DependencyInjection;

use Pushword\Core\DependencyInjection\ExtensionTrait;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ConfigurableExtension;

final class PushwordFlatExtension extends ConfigurableExtension implements PrependExtensionInterface
{
    use ExtensionTrait;

    /**
     * @var string
     */
    protected $configFolder = __DIR__.'/../config';
}
