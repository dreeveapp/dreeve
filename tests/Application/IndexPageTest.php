<?php

namespace App\Tests\Application;

use App\Application\IndexPage;
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

        $this->assertMatchesHtmlSnapshot($this->indexPage->render());
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
        $this->assertEquals('index', $this->indexPage->getCacheability()->getCacheKey());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->indexPage = $this->getContainer()->get(IndexPage::class);
    }
}
