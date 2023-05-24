<?php
declare(strict_types=1);

namespace Viosys\VioDBMigration\Subscriber;

use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelInterface;

class SystemSubscriber implements EventSubscriberInterface
{
    protected KernelInterface $kernel;
    protected string $migrationNamespace;

    public function __construct(
        KernelInterface $kernel,
        string $migrationNamespace = 'VioDbMigration'
    )
    {
        $this->kernel = $kernel;
        $this->migrationNamespace = $migrationNamespace;
    }

    public static function getSubscribedEvents()
    {
        return [
            UpdatePostFinishEvent::class => 'onPostFinish',
        ];
    }

    public function onPostFinish(UpdatePostFinishEvent $event)
    {
        // refresh the plugin list
        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            0 => 'plugin:refresh',
            '--no-interaction' => null,
        ]);
        $application->run($input);

        $application = new Application($this->kernel);
        $application->setAutoExit(false);

        $input = new ArrayInput([
            0 => 'database:migrate',
            '--no-interaction' => null,
            '--all' => null,
            'identifier' => $this->migrationNamespace,
        ]);
        $application->run($input);
    }

}
