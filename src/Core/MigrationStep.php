<?php
declare(strict_types=1);

namespace Viosys\VioDBMigration\Core;

use Doctrine\DBAL\Connection;
use Shopware\Core\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use \Shopware\Core\Framework\Migration\MigrationStep as CoreMigrationStep;

abstract class MigrationStep extends CoreMigrationStep
{
    protected function setConfiguration(string $key, $configuration, Connection $connection): void
    {
        $connection->delete('system_config', [
            'configuration_key' => $key
        ]);
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => $key,
            'configuration_value' => json_encode($configuration),
            'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            'updated_at' => (new DateTime())->format(Defaults::STORAGE_DATE_FORMAT)
        ]);
    }

    protected function InstallPlugins(array $pluginList, bool $activate = true): void
    {
        $application = new Application($this->getKernel());
        $application->setAutoExit(false);

        $input =  new ArrayInput([
            0 => 'plugin:install',
            '--activate' => $activate,
            '--no-interaction',
            'plugins' => $pluginList
        ]);
        $application->run($input);
    }

    protected function getContainer(): ContainerInterface
    {
        return $this->getKernel()->getContainer();
    }

    private function getKernel(): KernelInterface
    {
        global $kernel;
        return $kernel->getKernel();
    }
}
