<?php

namespace App\Tests\Controller\Admin\FileImport;

use App\Domain\Import\FileImportId;
use App\Domain\Import\FileImportRepository;
use App\Infrastructure\Exception\EntityNotFound;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\Controller\Admin\AdminWebTestCase;
use App\Tests\Domain\Import\FileImportBuilder;
use PHPUnit\Framework\Attributes\DataProvider;

class ManageFileImportFormRequestHandlerTest extends AdminWebTestCase
{
    #[DataProvider('provideRedirectToQueryParams')]
    public function testRendersTheDeleteConfirmation(string $query, string $expectedRedirect): void
    {
        $fileImportId = FileImportId::fromUnprefixed('1');
        static::getContainer()->get(FileImportRepository::class)->add(
            FileImportBuilder::fromDefaults()
                ->withFileImportId($fileImportId)
                ->withOriginalFilename('morning-run.fit')
                ->withImportedOn(SerializableDateTime::fromString('2026-06-04 10:00:00'))
                ->build()
        );

        $this->client->loginUser($this->adminUser());

        $crawler = $this->client->request('GET', '/admin/file-imports/'.$fileImportId.'/delete'.$query);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Delete file import', $crawler->filter('h3')->text());

        $form = $crawler->filter('form[data-dispatch-command="delete-file-import"]');
        $this->assertCount(1, $form);
        $this->assertSame((string) $fileImportId, $form->filter('input[name="fileImportId"]')->attr('value'));
        $this->assertCount(1, $form->filter('button.btn--danger'));
        $this->assertStringContainsString('morning-run.fit', $form->text());

        $this->assertSame($expectedRedirect, $form->attr('data-redirect'));
        $this->assertSame($expectedRedirect, $crawler->filter('a[aria-label="Close"]')->attr('href'));
        $this->assertSame($expectedRedirect, $crawler->filter('.btn--secondary')->attr('href'));
    }

    public static function provideRedirectToQueryParams(): \Generator
    {
        yield 'no redirectTo' => ['', '/admin/file-imports'];
        yield 'a filtered overview' => ['?redirectTo='.urlencode('/admin/file-imports?filters%5Bstatus%5D=failed&pagination%5Bpage%5D=2'), '/admin/file-imports?filters%5Bstatus%5D=failed&pagination%5Bpage%5D=2'];
        yield 'absolute url' => ['?redirectTo='.urlencode('https://evil.com'), '/admin/file-imports'];
    }

    public function testCannotDeleteAnUnknownFileImport(): void
    {
        $fileImportId = FileImportId::fromUnprefixed('nope');

        $this->client->loginUser($this->adminUser());
        $this->client->catchExceptions(false);

        $this->expectExceptionObject(new EntityNotFound(sprintf('File import "%s" is no longer available', $fileImportId)));
        $this->client->request('GET', '/admin/file-imports/'.$fileImportId.'/delete');
    }
}
