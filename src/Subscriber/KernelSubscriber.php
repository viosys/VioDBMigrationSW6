<?php
declare(strict_types=1);


namespace Viosys\VioDBMigration\Subscriber;


use Composer\Autoload\ClassLoader;
use Composer\Autoload\ClassMapGenerator;
use Shopware\Production\Kernel;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class KernelSubscriber implements EventSubscriberInterface
{
    /**
     * @var string
     */
    private $namespace;
    /**
     * @var string
     */
    private $directory;
    /**
     * @var \Shopware\Core\Kernel
     */
    private $kernel;

    /**
     * KernelSubscriber constructor.
     * @param \Shopware\Core\Kernel $kernel
     * @param string $namespace
     * @param string $directory
     */
    public function __construct(\Shopware\Core\Kernel $kernel, string $namespace, string $directory)
    {
        $this->namespace = $namespace;
        $this->directory = $directory;
        $this->kernel = $kernel;
    }

    /**
     * @inheritDoc
     */
    public static function getSubscribedEvents()
    {
        return [ConsoleEvents::COMMAND => 'onCommand'];
    }

    public function onCommand(ConsoleCommandEvent $event)
    {
        $mappedPaths = $this->directory;
        if (is_string($mappedPaths)) {
            $mappedPaths = [$mappedPaths];
        }
        /** @var Kernel $kernel */
        $classLoader = $this->kernel->getPluginLoader()->getClassLoader();
        /** @var ClassLoader $classLoad */
        $classLoader->addPsr4($this->namespace . '\\', $this->directory);
        if ($classLoader->isClassMapAuthoritative()) {
            foreach ($mappedPaths as $mappedPath) {
                $classLoader->addClassMap(ClassMapGenerator::createMap($mappedPath));
            }
        }

    }
}
