<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class JsonDecodeExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('json_decode', [$this, 'fn_json_decode']),
        ];
    }

    public function fn_json_decode(string $json)
    {
        return \json_decode($json);
    }
}
