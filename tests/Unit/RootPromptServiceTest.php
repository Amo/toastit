<?php

namespace App\Tests\Unit;

use App\Admin\RootPromptService;
use App\Ai\AiPromptFileStore;
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

final class RootPromptServiceTest extends TestCase
{
    public function testGetPromptExposesFileBackedCurrentSourceWhileKeepingDatabaseHistory(): void
    {
        $entityManager = $this->createEntityManager();
        [$promptRepository, $versionRepository] = $this->createRepositories($entityManager);

        $prompt = (new AiPrompt())
            ->setCode('file_backed_prompt')
            ->setLabel('File backed')
            ->setDescription('Prompt description')
            ->setAvailableVariables([[
                'name' => 'workspace_name',
                'description' => 'Workspace name',
                'example' => 'Product',
            ]]);

        $version = (new AiPromptVersion())
            ->setPrompt($prompt)
            ->setVersionNumber(4)
            ->setSystemPrompt('Database system prompt')
            ->setUserPromptTemplate('Database user prompt');

        $entityManager->persist($prompt);
        $entityManager->persist($version);
        $entityManager->flush();

        $twig = new Environment(new ArrayLoader([
            'ai/prompts/file_backed_prompt/system.v9.twig' => 'File system prompt',
            'ai/prompts/file_backed_prompt/user.v9.twig' => 'File user prompt',
        ]));

        $service = new RootPromptService(
            $promptRepository,
            $versionRepository,
            new AiPromptFileStore($twig, [
                'file_backed_prompt' => [
                    'version' => 9,
                    'system' => 'ai/prompts/file_backed_prompt/system.v9.twig',
                    'user' => 'ai/prompts/file_backed_prompt/user.v9.twig',
                ],
            ]),
            $entityManager,
        );

        $payload = $service->getPrompt('file_backed_prompt');

        self::assertIsArray($payload);
        self::assertTrue($payload['isFileBacked']);
        self::assertSame(9, $payload['currentVersionNumber']);
        self::assertSame('File system prompt', $payload['currentSystemPrompt']);
        self::assertSame('File user prompt', $payload['currentUserPromptTemplate']);
        self::assertSame(
            [
                'system' => 'ai/prompts/file_backed_prompt/system.v9.twig',
                'user' => 'ai/prompts/file_backed_prompt/user.v9.twig',
            ],
            $payload['sourceTemplatePaths'],
        );
        self::assertCount(1, $payload['versions']);
        self::assertSame(4, $payload['versions'][0]['versionNumber']);
        self::assertSame('Database system prompt', $payload['versions'][0]['systemPrompt']);
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
