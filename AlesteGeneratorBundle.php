<?php

namespace Aleste\GeneratorBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlesteGeneratorBundle extends Bundle
{
    public function getParent()
    {
        return 'SensioGeneratorBundle';
    }
}
