<?php

declare(strict_types=1);

use NunoMaduro\PhpInsights\Domain\Insights\CyclomaticComplexityIsHigh;
use NunoMaduro\PhpInsights\Domain\Insights\ForbiddenNormalClasses;
use NunoMaduro\PhpInsights\Domain\Insights\MethodCyclomaticComplexityIsHigh;

return [
    'preset' => 'symfony',
    'exclude' => [
        'var',
        'tmp',
        'vendor',
        'bin',
        'public/index.php',
    ],
    'add' => [],
    'remove' => [
        ForbiddenNormalClasses::class,
    ],
    'config' => [
        ForbiddenNormalClasses::class => [
            'enabled' => false,
        ],
        MethodCyclomaticComplexityIsHigh::class => [
            'maxMethodComplexity' => 7,
        ],
        CyclomaticComplexityIsHigh::class => [
            'maxComplexity' => 30,
        ],
    ],
    'requirements' => [
        'min-quality' => 100,
        'min-complexity' => 100,
        'min-architecture' => 100,
        'min-style' => 100,
    ],
];
