<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;

return (new Configuration())
    ->addPathToScan(__DIR__.'/bin', true)
    ->addPathToExclude(__DIR__.'/tests/Fixtures')
    ->addPathToExclude(__DIR__.'/tests/LuckyController.php')
;
