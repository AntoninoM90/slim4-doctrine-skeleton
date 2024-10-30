<?php

declare(strict_types=1);

use App\Application\Settings\SettingsInterface;
use DI\ContainerBuilder;
use Doctrine\DBAL\DriverManager;
use Psr\Container\ContainerInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Processor\UidProcessor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;

/**
 * Set dependencies of the application.
 *
 * @param ContainerBuilder $containerBuilder The container builder
 *
 * @return void
 */
return function (
    ContainerBuilder $containerBuilder
) {
    $containerBuilder->addDefinitions([
        // Logger
        LoggerInterface::class => function (ContainerInterface $c): LoggerInterface {
            $settings = $c->get(SettingsInterface::class);

            // Get Logger settings
            $loggerSettings = $settings->get('logger');

            // Initialize the Logger
            $logger = new Logger($loggerSettings['name']);

            // Push Uid processor
            $processor = new UidProcessor();
            $logger->pushProcessor($processor);

            // Push stream handler
            $handler = new StreamHandler($loggerSettings['path'], $loggerSettings['level']);
            $logger->pushHandler($handler);

            // Return the logger
            return $logger;
        },

        // Doctrine Entity Manager
        EntityManager::class => function (ContainerInterface $container): EntityManager {
            /** @var SettingsInterface $settings */
            $settings = $container->get(SettingsInterface::class);

            /** @var array $doctrineSettings */
            $doctrineSettings = $settings->get('doctrine');

            // Create the configuration for annotation metadata
            $config = ORMSetup::createAttributeMetadataConfiguration(
                $doctrineSettings['metadata_dirs'],
                $doctrineSettings['dev_mode'],
                $doctrineSettings['proxy_dir'],
                null,
                false
            );

            // Set cache
            if ($doctrineSettings['dev_mode']) {
                $metadataCache = new ArrayAdapter();
                $queryCache = new ArrayAdapter();
                $resultCache = new ArrayAdapter();
            } else {
                $metadataCache = new PhpFilesAdapter('doctrine_metadata');
                $queryCache = new PhpFilesAdapter('doctrine_queries');
                $resultCache = new PhpFilesAdapter('doctrine_cache');
            }

            // Set metadata cache
            $config->setMetadataCache(
                $metadataCache
            );

            // Set query cache
            $config->setQueryCache(
                $queryCache
            );

            // Set result cache
            $config->setResultCache(
                $resultCache
            );

            // configuring the database connection
            $connection = DriverManager::getConnection(
                $doctrineSettings['connections']['default'],
                $config
            );

            // obtaining the entity manager
            $entityManager = new EntityManager($connection, $config);

            // Return the new entity manager created
            return $entityManager;
        },
    ]);
};
