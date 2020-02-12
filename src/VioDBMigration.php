<?php
declare(strict_types=1);


namespace Viosys\VioDBMigration;


use Composer\Autoload\ClassLoader;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Viosys\VioDBMigration\CompilerPass\AddMigrationPathCompilerPass;

class VioDBMigration extends Plugin
{
    /**
     * @var ClassLoader
     */
    private $classLoader;

    public function getAdditionalBundles(AdditionalBundleParameters $parameters): array
    {
        $this->classLoader = $parameters->getClassLoader();
        return parent::getAdditionalBundles($parameters);
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new AddMigrationPathCompilerPass($this->classLoader), PassConfig::TYPE_AFTER_REMOVING, -1000);
    }
}
