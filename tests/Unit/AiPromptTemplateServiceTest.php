<?php

namespace App\Tests\Unit;

use App\Ai\AiPromptFileStore;
use App\Ai\AiPromptTemplateService;
use App\Entity\AiPrompt;
use App\Entity\AiPromptVersion;
use App\Entity\User;
use App\Repository\AiPromptRepository;
use App\Repository\AiPromptVersionRepository;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

final class AiPromptTemplateServiceTest extends TestCase
{
    public function testResolveSystemPromptUsesFileBackedPromptBeforeDatabase(): void
    {
        $entityManager = $this->createEntityManager();
        [$promptRepository, $versionRepository] = $this->createRepositories($entityManager);
        $twig = new Environment(new ArrayLoader([
            'ai/prompts/test_prompt/system.v1.twig' => 'File system prompt for {{ name }}.',
            'ai/prompts/test_prompt/user.v1.twig' => 'Unused user prompt',
        ]));

        $service = new AiPromptTemplateService(
            $promptRepository,
            $versionRepository,
            new AiPromptFileStore($twig, [
                'test_prompt' => [
                    'version' => 1,
                    'system' => 'ai/prompts/test_prompt/system.v1.twig',
                    'user' => 'ai/prompts/test_prompt/user.v1.twig',
                ],
            ]),
            $twig,
        );

        self::assertSame(
            'File system prompt for Amaury.',
            $service->resolveSystemPrompt('test_prompt', 'Database fallback', ['name' => 'Amaury']),
        );
    }

    public function testResolveUserPromptTemplateFallsBackToDatabaseWhenNoFilePromptExists(): void
    {
        $entityManager = $this->createEntityManager();
        [$promptRepository, $versionRepository] = $this->createRepositories($entityManager);

        $prompt = (new AiPrompt())
            ->setCode('db_only_prompt')
            ->setLabel('DB only');

        $version = (new AiPromptVersion())
            ->setPrompt($prompt)
            ->setVersionNumber(3)
            ->setSystemPrompt('Unused system prompt')
            ->setUserPromptTemplate('DB user prompt for {{ name }}.');

        $entityManager->persist($prompt);
        $entityManager->persist($version);
        $entityManager->flush();

        $twig = new Environment(new ArrayLoader([]));

        $service = new AiPromptTemplateService(
            $promptRepository,
            $versionRepository,
            new AiPromptFileStore($twig, []),
            $twig,
        );

        self::assertSame(
            'DB user prompt for Amaury.',
            $service->resolveUserPromptTemplate('db_only_prompt', 'Fallback user prompt', ['name' => 'Amaury']),
        );
    }

    private function createEntityManager(): EntityManager
    {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            [dirname(__DIR__, 2).'/src/Entity'],
            true,
        );
        $config->enableNativeLazyObjects(true);
        $connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ], $config);
        $entityManager = new EntityManager($connection, $config);

        $schemaTool = new SchemaTool($entityManager);
        $metadata = [
            $entityManager->getClassMetadata(AiPrompt::class),
            $entityManager->getClassMetadata(AiPromptVersion::class),
            $entityManager->getClassMetadata(User::class),
        ];
        $schemaTool->createSchema($metadata);

        return $entityManager;
    }

    /**
     * @return array{AiPromptRepository, AiPromptVersionRepository}
     */
    private function createRepositories(EntityManager $entityManager): array
    {
        $registry = new class($entityManager) implements ManagerRegistry {
            public function __construct(private readonly EntityManager $entityManager)
            {
            }

            public function getDefaultManagerName(): string
            {
                return 'default';
            }

            public function getManager(string|null $name = null): ObjectManager
            {
                return $this->entityManager;
            }

            public function getManagers(): array
            {
                return ['default' => $this->entityManager];
            }

            public function resetManager(string|null $name = null): ObjectManager
            {
                return $this->entityManager;
            }

            public function getManagerNames(): array
            {
                return ['default' => 'default'];
            }

            public function getRepository(string $persistentObject, string|null $persistentManagerName = null): ObjectRepository
            {
                return $this->entityManager->getRepository($persistentObject);
            }

            public function getManagerForClass(string $class): ?ObjectManager
            {
                return $this->entityManager;
            }

            public function getDefaultConnectionName(): string
            {
                return 'default';
            }

            public function getConnection(string|null $name = null): object
            {
                return $this->entityManager->getConnection();
            }

            public function getConnections(): array
            {
                return ['default' => $this->entityManager->getConnection()];
            }

            public function getConnectionNames(): array
            {
                return ['default' => 'default'];
            }
        };

        return [
            new AiPromptRepository($registry),
            new AiPromptVersionRepository($registry),
        ];
    }
}
