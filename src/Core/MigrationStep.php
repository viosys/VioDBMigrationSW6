<?php
declare(strict_types=1);

namespace Viosys\VioDBMigration\Core;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Exception;
use JsonException;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\HttpKernel;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Shopware\Core\Framework\Migration\MigrationStep as CoreMigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

abstract class MigrationStep extends CoreMigrationStep
{
    #region core functions

    protected ?string $deId = null;
    protected ?string $enId = null;

    protected ?KernelInterface $kernel = null;
    protected ?ContainerInterface $container = null;

    protected function getContainer(): ContainerInterface
    {
        if($this->container === null) {
            $this->container = $this->getKernel()->getContainer();
        }
        return $this->container;
    }

    private function getKernel(): KernelInterface
    {
        if( $this->kernel !== null ){
            return $this->kernel;
        }
        /** @var HttpKernel $kernel */
        global $kernel;
        if( $kernel instanceof HttpKernel ){
            $this->kernel = $kernel->getKernel();
        }
        // try to get kernel from global $app
        global $app;
        if ($app instanceof Application ) {
            $this->kernel = $app->getKernel();
        }
        if ($this->kernel === null ) {
            throw new Exception('Could not get kernel');
        }
        return $this->kernel;
    }

    protected function getContext(): Context
    {
        /** @noinspection PhpInternalEntityUsedInspection */
        return Context::createDefaultContext();
    }

    #endregion

    #region snippet functions
    /**
     * @throws DBALException
     */
    protected function getSnippetSetId(string $locale, Connection $connection): string
    {
        return $connection->executeQuery(
            'SELECT id FROM snippet_set WHERE iso = ?',
            [$locale]
        )->fetchOne();
    }

    /**
     * @throws DBALException
     */
    protected function setSnippet(string $key, string $value, string $locale, Connection $connection): void
    {
        $snippetSetId = $this->getSnippetSetId($locale, $connection);
        $connection->delete('snippet', [
            'translation_key' => $key,
            'snippet_set_id' => $snippetSetId
        ]);
        $connection->insert('snippet', [
            'id' => Uuid::randomBytes(),
            'translation_key' => $key,
            'value' => $value,
            'snippet_set_id' => $snippetSetId,
            'author' => 'VioDbMigration',
            'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            'updated_at' => (new DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
        ]);
    }

    #endregion

    #region configuration functions
    /**
     * @throws DBALException
     * @throws JsonException
     */
    protected function setConfiguration(string $key, $configuration, Connection $connection): void
    {
        $connection->delete('system_config', [
            'configuration_key' => $key
        ]);
        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => $key,
            'configuration_value' => json_encode($configuration, JSON_THROW_ON_ERROR),
            'created_at' => (new DateTime())->format(Defaults::STORAGE_DATE_FORMAT),
            'updated_at' => (new DateTime())->format(Defaults::STORAGE_DATE_FORMAT)
        ]);
    }

    #endregion

    #region plugin-lifecycle functions

    /**
     * @throws Exception
     */
    protected function InstallPlugins(array $pluginList, bool $activate = true): void
    {
        $application = new Application($this->getKernel());
        $application->setAutoExit(false);

        $input =  new ArrayInput([
            0 => 'plugin:install',
            '--activate' => $activate,
            'plugins' => $pluginList
        ]);
        $input->setInteractive(false);
        $application->run($input);
    }

    /**
     * @throws Exception
     */
    #[Deprecated]
    protected function pluginRefresh(#[Deprecated] KernelInterface $kernel = null): void
    {
        return;
    }


    /**
     * @throws Exception
     */
    protected function updatePlugins(array $pluginList): void
    {
        $application = new Application($this->getKernel());
        $application->setAutoExit(false);

        $input = new ArrayInput([
            0 => 'plugin:update',
            'plugins' => $pluginList
        ]);
        $input->setInteractive(false);
        $application->run($input);
    }

    #endregion

    #region language functions

    protected function getLanguageIdByCode(Context $context, string $languageCode): string
    {
        /** @var EntityRepository $repository */
        $repository = $this->getContainer()->get(LanguageDefinition::ENTITY_NAME . '.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter(LanguageDefinition::ENTITY_NAME . '.translationCode.code', $languageCode));

        return $repository->searchIds($criteria, $context)->firstId() ?? '';
    }

    protected function getDeDeLanguageId(Context $context): string
    {
        if ($this->deId === null) {
            $this->deId = $this->getLanguageIdByCode($context, 'de-DE');
        }
        return $this->deId;
    }

    protected function getEnGbLanguageId(Context $context): string
    {
        if ( $this->enId === null) {
            $this->enId = $this->getLanguageIdByCode($context, 'en-GB');
        }
        return $this->enId;
    }

    #endregion

    #region state-machine functions

    protected function getStateMachineId(
        Context $context,
        string $stateMachineName
    ): ?string
    {
        $stateMachineRepo = $this->getContainer()->get(StateMachineDefinition::ENTITY_NAME.'.repository');
        if ($stateMachineRepo instanceof EntityRepository) {
            $stateMachineId = $stateMachineRepo->searchIds(
                (new Criteria())->addFilter(
                    new EqualsFilter('technicalName', $stateMachineName)
                ),
                $context
            )->firstId();
            if (!empty($stateMachineId)) {
                return $stateMachineId;
            }
        }
        return null;
    }

    protected function getStateId(
        string $stateName,
        string $stateMachineId,
        Context $context): ?string
    {
        $stateRepo = $this->getContainer()->get(StateMachineStateDefinition::ENTITY_NAME.'.repository');
        if ($stateRepo instanceof EntityRepository) {
            return $stateRepo->searchIds(
                (new Criteria())->addFilter(
                    new AndFilter(
                        [
                            new EqualsFilter('technicalName', $stateName),
                            new EqualsFilter('stateMachineId', $stateMachineId)
                        ]
                    )
                ),
                $context
            )->firstId();
        }
        return null;
    }

    protected function upsertState(
        Context $context,
        string $stateName,
        string $stateMachineId,
        array $translations = []
    ): ?string
    {
        $stateRepo = $this->getContainer()->get(StateMachineStateDefinition::ENTITY_NAME.'.repository');
        if ($stateRepo instanceof EntityRepository) {
            $stateId = $this->getStateId($stateName, $stateMachineId, $context);

            if (empty($stateId)) {
                $stateRepo->upsert([
                    [
                        'technicalName' => $stateName,
                        'stateMachineId' => $stateMachineId,
                        'translations' => $translations,
                    ],
                ], $context);
                $stateId = $this->getStateId($stateName, $stateMachineId, $context);
            }
            return $stateId;
        }
        return null;
    }

    protected function getActionId(
        string $actionName,
        string $stateMachineId,
        string $fromStateId,
        string $toStateId,
        Context $context): ?string
    {
        $transitionsRepo = $this->getContainer()->get(StateMachineTransitionDefinition::ENTITY_NAME.'.repository');
        if( $transitionsRepo instanceof EntityRepository) {
            return $transitionsRepo->searchIds(
                (new Criteria())->addFilter(
                    new AndFilter(
                        [
                            new EqualsFilter('actionName', $actionName),
                            new EqualsFilter('stateMachineId', $stateMachineId),
                            new EqualsFilter('fromStateId', $fromStateId),
                            new EqualsFilter('toStateId', $toStateId)
                        ]
                    )
                ),
                $context
            )->firstId();
        }
        return null;
    }

    protected function upsertAction(
        Context $context,
        string $actionName,
        string $stateMachineId,
        string $openStateId,
        string $articleReceivedId
    ): ?string
    {
        $transitionsRepo = $this->container->get(StateMachineTransitionDefinition::ENTITY_NAME.'.repository');
        if ($transitionsRepo instanceof EntityRepository) {
            $actionId = $this->getActionId(
                $actionName,
                $stateMachineId,
                $openStateId,
                $articleReceivedId,
                $context);

            if (empty($actionId)) {
                $transitionsRepo->upsert([
                    [
                        'actionName' => $actionName,
                        'stateMachineId' => $stateMachineId,
                        'fromStateId' => $openStateId,
                        'toStateId' => $articleReceivedId
                    ],
                ], $context);
                $actionId = $this->getActionId(
                    $actionName,
                    $stateMachineId,
                    $openStateId,
                    $articleReceivedId,
                    $context);
            }
            return $actionId;
        }
        return null;
    }

    #endregion
}
