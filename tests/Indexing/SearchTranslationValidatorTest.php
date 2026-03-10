<?php

declare(strict_types=1);

namespace PsychedCms\Search\Tests\Indexing;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PsychedCms\Search\Indexing\SearchTranslationValidator;
use PsychedCms\Search\Tests\Fixtures\IndexedEntity;

final class SearchTranslationValidatorTest extends TestCase
{
    private SearchTranslationValidator $validator;

    protected function setUp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $this->validator = new SearchTranslationValidator($entityManager);
    }

    public function testDefaultLocaleAlwaysComplete(): void
    {
        $entity = new IndexedEntity();

        // IndexedEntity has no ContentType attribute, so defaults to ['en']
        self::assertTrue($this->validator->isLocaleComplete($entity, 'en'));
    }

    public function testEntityWithNoTranslatableFieldsAlwaysComplete(): void
    {
        $entity = new IndexedEntity();

        // IndexedEntity has no Gedmo\Translatable fields
        self::assertTrue($this->validator->isLocaleComplete($entity, 'fr'));
    }

    public function testGetLocalesFallsBackToEn(): void
    {
        $entity = new IndexedEntity();

        $locales = $this->validator->getLocales($entity);

        self::assertSame(['en'], $locales);
    }

    public function testGetDefaultLocale(): void
    {
        $entity = new IndexedEntity();

        self::assertSame('en', $this->validator->getDefaultLocale($entity));
    }
}
