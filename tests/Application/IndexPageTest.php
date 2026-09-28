<?php

namespace App\Tests\Application;

use App\Application\IndexPage;
use App\Application\Navigation\NavigationSection;
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

        $this->assertMatchesHtmlSnapshot($this->indexPage->forSection(null)->render());
    }

    public function testRenderIsTheSameForEveryVisitor(): void
    {
        $this->provideFullTestSet();

        /** @var TokenStorageInterface $tokenStorage */
        $tokenStorage = $this->getContainer()->get('security.token_storage');

        $tokenStorage->setToken(null);
        $renderedForAnonymousVisitor = $this->indexPage->forSection(null)->render();

        $tokenStorage->setToken(new UsernamePasswordToken(
            new InMemoryUser('admin', null, ['ROLE_ADMIN']),
            'main',
            ['ROLE_ADMIN'],
        ));
        $renderedForAuthenticatedVisitor = $this->indexPage->forSection(null)->render();
        $tokenStorage->setToken(null);

        $this->assertEquals($renderedForAnonymousVisitor, $renderedForAuthenticatedVisitor);
    }

    public function testGetCacheKey(): void
    {
        $this->assertEquals('index', $this->indexPage->forSection(null)->getCacheability()->getCacheKey());
        $this->assertEquals('index.activities', $this->indexPage->forSection(NavigationSection::ACTIVITIES)->getCacheability()->getCacheKey());
    }

    public function testRenderMarksTheActiveSection(): void
    {
        $this->provideFullTestSet();

        $render = $this->indexPage->forSection(NavigationSection::ACTIVITIES)->render();

        $this->assertStringContainsString('href="/activities" aria-selected="true"', $render);
        $this->assertStringContainsString('href="/dashboard" aria-selected="false"', $render);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->indexPage = $this->getContainer()->get(IndexPage::class);
    }
}
