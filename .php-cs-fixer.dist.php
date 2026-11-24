<?php

$finder = new PhpCsFixer\Finder()
    ->in(__DIR__)
    ->exclude('var');

return new PhpCsFixer\Config()
    ->setUnsupportedPhpVersionAllowed(true)
    ->setParallelConfig(PhpCsFixer\Runner\Parallel\ParallelConfigFactory::detect())
    ->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder);
