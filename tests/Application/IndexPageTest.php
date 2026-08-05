<?php

namespace App\Tests\Application;

use App\Application\IndexPage;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\KeyValue\Key;
use App\Infrastructure\KeyValue\KeyValue;
use App\Infrastructure\KeyValue\KeyValueStore;
use App\Infrastructure\KeyValue\Value;
use App\Tests\ContainerTestCase;
use App\Tests\ProvideTestData;
use Spatie\Snapshots\MatchesSnapshots;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;

class IndexPageTest extends ContainerTestCase
{
    use MatchesSnapshots;
    use ProvideTestData;

    private IndexPage $indexPage;

    public function testRender(): void
    {
        $this->provideFullTestSet();
        $this->getContainer()->get(KeyValueStore::class)->save(KeyValue::fromState(
            key: Key::APP_LAST_BUILD_DATE_TIME,
            value: Value::fromString('2023-10-17T16:15:04+00:00'),
        ));

        $this->assertMatchesHtmlSnapshot($this->indexPage->render());
    }

    public function testRenderWhenTheAppHasNeverBeenBuilt(): void
    {
        $this->provideFullTestSet();

        $this->assertStringNotContainsString('Updated on', $this->indexPage->render());
    }

    public function testRenderIsTheSameForEveryVisitor(): void
    {
        $this->provideFullTestSet();

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->getContainer()->get('security.token_storage');

        $tokenStorage->setToken(null);
        $renderedForAnonymousVisitor = $this->indexPage->render();

        $tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('admin', null, ['ROLE_ADMIN']),
            'main',
            ['ROLE_ADMIN'],
        ));
        $renderedForAuthenticatedVisitor = $this->indexPage->render();
        $tokenStorage->setToken(null);

        $this->assertEquals($renderedForAnonymousVisitor, $renderedForAuthenticatedVisitor);
    }

    public function testGetCacheKey(): void
    {
        $this->assertEquals('index', $this->indexPage->getCacheKey());
    }

    public function testGetCacheability(): void
    {
        $cacheability = $this->indexPage->getCacheability();

        $this->assertTrue($cacheability->isCacheable());
        $this->assertEquals(
            [
                CacheTag::SETTINGS_APPEARANCE->value,
                CacheTag::SETTINGS_GENERAL->value,
                CacheTag::APP_BUILD->value,
                CacheTag::ACTIVITIES->value,
                CacheTag::ACTIVITY_IMAGES->value,
                CacheTag::CHALLENGES->value,
                CacheTag::SETTINGS_INTEGRATIONS->value,
                CacheTag::SETTINGS_MAPS->value,
            ],
            $cacheability->getCacheTags()->toTagStrings()
        );
        $this->assertEmpty($cacheability->getCacheContexts()->toArray());
        $this->assertNull($cacheability->getTtlInSeconds());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->indexPage = $this->getContainer()->get(IndexPage::class);
    }
}
