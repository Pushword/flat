<?php

namespace Pushword\Flat;

use Pushword\Flat\DependencyInjection\PushwordFlatExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PushwordFlatBundle extends Bundle
{
    /**
     * @return \Pushword\Flat\DependencyInjection\PushwordFlatExtension|null
     */
    public function getContainerExtension()
    {
        if (null === $this->extension) {
            $this->extension = new PushwordFlatExtension();
        }

        return $this->extension;
    }
}
