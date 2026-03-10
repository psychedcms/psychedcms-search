<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\State;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\ProviderInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use PsychedCms\Search\Client\ElasticsearchClientInterface;
use PsychedCms\Search\Index\IndexNameResolver;
use PsychedCms\Search\Indexing\EntityMetadataReader;
use PsychedCms\Search\Search\SearchResult;
use PsychedCms\Search\Search\SearchServiceInterface;
use PsychedCms\Search\State\SearchCollectionProvider;
use PsychedCms\Search\Tests\Fixtures\IndexedEntity;
use PsychedCms\Search\Tests\Fixtures\NonIndexedEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class SearchCollectionProviderTest extends TestCase
{
    private ProviderInterface $decorated;
    private EntityMetadataReader $metadataReader;
    private SearchServiceInterface $searchService;
    private ElasticsearchClientInterface $esClient;
    private IndexNameResolver $nameResolver;
    private EntityManagerInterface $entityManager;
    private RequestStack $requestStack;
    private SearchCollectionProvider $provider;

    protected function setUp(): void
    {
        $this->decorated = $this->createMock(ProviderInterface::class);
        $this->metadataReader = $this->createMock(EntityMetadataReader::class);
        $this->searchService = $this->createMock(SearchServiceInterface::class);
        $this->esClient = $this->createMock(ElasticsearchClientInterface::class);
        $this->nameResolver = $this->createMock(IndexNameResolver::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->requestStack = new RequestStack();

        $this->provider = new SearchCollectionProvider(
            $this->decorated,
            $this->metadataReader,
            $this->searchService,
            $this->esClient,
            $this->nameResolver,
            $this->entityManager,
            $this->requestStack,
        );
    }

    public function testDelegatesToDoctrineForNonIndexedEntity(): void
    {
        $operation = new GetCollection(class: NonIndexedEntity::class);

        $this->metadataReader->method('isIndexed')
            ->with(NonIndexedEntity::class)
            ->willReturn(false);

        $this->decorated->expects(self::once())
            ->method('provide')
            ->willReturn([]);

        $result = $this->provider->provide($operation);

        self::assertSame([], $result);
    }

    public function testDelegatesToDoctrineForAdminWithoutSearch(): void
    {
        $operation = new GetCollection(class: IndexedEntity::class);
        $request = new Request();
        $request->headers->set('X-Client-Type', 'admin');
        $this->requestStack->push($request);

        $this->metadataReader->method('isIndexed')->willReturn(true);
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');

        $this->decorated->expects(self::once())
            ->method('provide')
            ->willReturn([]);

        $result = $this->provider->provide($operation);

        self::assertSame([], $result);
    }

    public function testUsesEsForPublicRequest(): void
    {
        $operation = new GetCollection(class: IndexedEntity::class);
        $request = new Request(['locale' => 'en']);
        $this->requestStack->push($request);

        $this->metadataReader->method('isIndexed')->willReturn(true);
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->esClient->method('isAvailable')->willReturn(true);

        $this->searchService->expects(self::once())
            ->method('search')
            ->willReturn(new SearchResult([], 0, 1, 20));

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findBy')->willReturn([]);
        $this->entityManager->method('getRepository')->willReturn($repository);

        $result = $this->provider->provide($operation);

        self::assertSame([], $result);
    }

    public function testUsesEsForAdminWithSearchParam(): void
    {
        $operation = new GetCollection(class: IndexedEntity::class);
        $request = new Request(['search' => 'test', 'locale' => 'en']);
        $request->headers->set('X-Client-Type', 'admin');
        $this->requestStack->push($request);

        $this->metadataReader->method('isIndexed')->willReturn(true);
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->esClient->method('isAvailable')->willReturn(true);

        $this->searchService->expects(self::once())
            ->method('search')
            ->willReturn(new SearchResult([], 0, 1, 20));

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findBy')->willReturn([]);
        $this->entityManager->method('getRepository')->willReturn($repository);

        $result = $this->provider->provide($operation);

        self::assertSame([], $result);
    }

    public function testFallsBackToDoctrineWhenEsUnavailable(): void
    {
        $operation = new GetCollection(class: IndexedEntity::class);
        $request = new Request();
        $this->requestStack->push($request);

        $this->metadataReader->method('isIndexed')->willReturn(true);
        $this->nameResolver->method('resolve')->willReturn('psychedcms_indexedentity');
        $this->esClient->method('isAvailable')->willReturn(false);

        $this->decorated->expects(self::once())
            ->method('provide')
            ->willReturn([]);

        $result = $this->provider->provide($operation);

        self::assertSame([], $result);
    }
}
