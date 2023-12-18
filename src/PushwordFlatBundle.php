<?php

namespace Pushword\Flat;

use Pushword\Flat\DependencyInjection\PushwordFlatExtension;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordFlatBundle extends Bundle
{
    public function getContainerExtension(): ?ExtensionInterface
    {
        if (null === $this->extension) {
            $this->extension = new PushwordFlatExtension();
        }

        return false === $this->extension ? null : $this->extension;
    }
}
