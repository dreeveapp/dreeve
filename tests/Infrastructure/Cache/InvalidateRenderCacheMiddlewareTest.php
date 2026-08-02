<?php

namespace App\Tests\Infrastructure\Cache;

use App\Domain\Import\UploadActivityFile\UploadActivityFile;
use App\Infrastructure\Cache\Cacheability;
use App\Infrastructure\Cache\CacheableRenderer;
use App\Infrastructure\Cache\CacheContextRegistry;
use App\Infrastructure\Cache\CacheTag;
use App\Infrastructure\Cache\CacheTags;
use App\Infrastructure\Cache\InvalidateRenderCacheMiddleware;
use App\Infrastructure\Cache\RenderCache;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\CQRS\Command\Bus\RunAnOperation\RunAnOperation;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\StackMiddleware;

class InvalidateRenderCacheMiddlewareTest extends ContainerTestCase
{
    private RenderCache $renderCache;
    private CacheableRenderer $cacheableRenderer;
    private InvalidateRenderCacheMiddleware $middleware;

    public function testItInvalidatesTheTagsTheCommandProvidesAtRuntime(): void
    {
        $cacheable = $this->renderAndCache(CacheTag::SETTINGS_APPEARANCE);

        $this->handle(new CommandProvidingCacheTagsStub(CacheTag::SETTINGS_APPEARANCE));

        $this->cacheableRenderer->render($cacheable);
        $this->assertEquals(2, $cacheable->renderCount);
    }

    public function testItLeavesRendersAloneWhenTheCommandProvidesAnotherTag(): void
    {
        $cacheable = $this->renderAndCache(CacheTag::SETTINGS_APPEARANCE);

        $this->handle(new CommandProvidingCacheTagsStub(CacheTag::SETTINGS_INTEGRATIONS));

        $this->cacheableRenderer->render($cacheable);
        $this->assertEquals(1, $cacheable->renderCount);
    }

    public function testItInvalidatesNothingWhenTheCommandDeclaresNoTags(): void
    {
        $cacheable = $this->renderAndCache(CacheTag::ACTIVITIES);

        $this->handle(new RunAnOperation('test'));

        $this->cacheableRenderer->render($cacheable);
        $this->assertEquals(1, $cacheable->renderCount);
    }

    // Uploading only drops a file in the watch directory, the activity does not exist yet. Invalidating
    // here would throw away a good entry for no change; ImportActivityFiles does it once the file lands.
    public function testUploadingAnActivityFileInvalidatesNothing(): void
    {
        $cacheable = $this->renderAndCache(CacheTag::ACTIVITIES);

        $this->handle(UploadActivityFile::fromPayload([
            'filename' => 'ride.tcx',
            'content' => base64_encode('<xml></xml>'),
        ]));

        $this->cacheableRenderer->render($cacheable);
        $this->assertEquals(1, $cacheable->renderCount);
    }

    private function renderAndCache(CacheTag $cacheTag): CacheableStub
    {
        $cacheable = CacheableStub::for(Cacheability::for(CacheTags::of($cacheTag)));
        $this->cacheableRenderer->render($cacheable);

        return $cacheable;
    }

    private function handle(object $command): void
    {
        $this->middleware->handle(new Envelope($command), new StackMiddleware());
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->renderCache = $this->getContainer()->get(RenderCache::class);
        $this->renderCache->clear();
        $this->cacheableRenderer = new CacheableRenderer(
            $this->renderCache,
            new CacheContextRegistry([]),
        );
        $this->middleware = new InvalidateRenderCacheMiddleware($this->renderCache);
    }
}
