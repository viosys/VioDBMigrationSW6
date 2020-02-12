<?php
declare(strict_types=1);


namespace Viosys\VioDBMigration\CompilerPass;

use Composer\Autoload\ClassLoader;
use Composer\Autoload\ClassMapGenerator;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class AddMigrationPathCompilerPass implements CompilerPassInterface
{
    /**
     * @var ClassLoader
     */
    private $classLoader;

    /**
     * AddMigrationPathCompilerPass constructor.
     * @param ClassLoader $classLoader
     */
    public function __construct(ClassLoader $classLoader)
    {
        $this->classLoader = $classLoader;
    }

    /**
     * @inheritDoc
     */
    public function process(ContainerBuilder $container)
    {
        if ($container->hasParameter('migration.directories')) {
            $directories = $container->getParameter('migration.directories');
            $namespace = $container->resolveEnvPlaceholders($container->getParameter('viosys.migration.namespace'), true);
            $directory = $container->resolveEnvPlaceholders($container->getParameter('viosys.migration.directory'), true);

            $directories[$namespace] = $directory;
            $container->setParameter('migration.directories', $directories);

            $mappedPaths = $directory;
            if (is_string($mappedPaths)) {
                $mappedPaths = [$mappedPaths];
            }

            $this->classLoader->addPsr4($namespace . '\\', $mappedPaths);
            if ($this->classLoader->isClassMapAuthoritative()) {
                foreach ($mappedPaths as $mappedPath) {
                    $this->classLoader->addClassMap(ClassMapGenerator::createMap($mappedPath));
                }
            }


        }
    }
}
