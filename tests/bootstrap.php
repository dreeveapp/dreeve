<?php

use App\Domain\Import\ImportMode;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Dotenv\Dotenv;

require __DIR__.'/../vendor/autoload.php';

if (file_exists(__DIR__.'/../config/bootstrap.php')) {
    require __DIR__.'/../config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    new Dotenv()->bootEnv(__DIR__.'/../.env');
}

libxml_use_internal_errors(true);
$_ENV['DAEMON_DEBUG'] = 1;
$_SERVER['IMPORT_MODE'] = $_ENV['IMPORT_MODE'] = ImportMode::STRAVA_API->value;

// Build the test database schema exactly once per test process. This runs
// before DAMA's PHPUnit extension enables static connections, so the DDL is
// committed to the SQLite file instead of being wrapped in (and rolled back
// with) the per-test transaction. Every test then runs against this schema,
// with DAMA rolling back only the data between tests.
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

/** @var EntityManagerInterface $entityManager */
$entityManager = $kernel->getContainer()->get('doctrine')->getManager();
$schemaTool = new SchemaTool($entityManager);
$schemaTool->dropDatabase();
$schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

$kernel->shutdown();
