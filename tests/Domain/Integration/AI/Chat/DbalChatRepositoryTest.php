<?php

namespace App\Tests\Domain\Integration\AI\Chat;

use App\Domain\Integration\AI\Chat\ChatMessageId;
use App\Domain\Integration\AI\Chat\ChatRepository;
use App\Domain\Integration\AI\Chat\DbalChatRepository;
use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use App\Tests\ContainerTestCase;
use App\Tests\Infrastructure\Time\Clock\PausedClock;
use NeuronAI\Chat\Enums\MessageRole;
use PHPUnit\Framework\MockObject\MockObject;
use Spatie\Snapshots\MatchesSnapshots;

class DbalChatRepositoryTest extends ContainerTestCase
{
    use MatchesSnapshots;

    private ChatRepository $chatRepository;
    private MockObject $messageIdFactory;

    public function testAddAndGetHistory(): void
    {
        $this->chatRepository->add(
            ChatMessageBuilder::fromDefaults()
                ->withMessageId(ChatMessageId::fromUnprefixed('test'))
                ->withMessage('User Message')
                ->withMessageRole(MessageRole::USER)
                ->withFirstLetterOfFirstName('R')
                ->build()
        );

        $this->chatRepository->add(
            ChatMessageBuilder::fromDefaults()
                ->withMessageId(ChatMessageId::fromUnprefixed('test-2'))
                ->withMessage('Assistant Message')
                ->withMessageRole(MessageRole::ASSISTANT)
                ->withFirstLetterOfFirstName('R')
                ->build()
        );

        $this->assertEquals(
            [
                ChatMessageBuilder::fromDefaults()
                    ->withMessageId(ChatMessageId::fromUnprefixed('test'))
                    ->withMessage('User Message')
                    ->withMessageRole(MessageRole::USER)
                    ->withFirstLetterOfFirstName('R')
                    ->build(),
                ChatMessageBuilder::fromDefaults()
                    ->withMessageId(ChatMessageId::fromUnprefixed('test-2'))
                    ->withMessage('Assistant Message')
                    ->withMessageRole(MessageRole::ASSISTANT)
                    ->withFirstLetterOfFirstName('R')
                    ->build(),
            ],
            $this->chatRepository->findAll()
        );

        $this->chatRepository->clear();
        $this->assertEmpty($this->chatRepository->findAll());
    }

    public function testCreate(): void
    {
        $message = $this->chatRepository->buildMessage('The message', MessageRole::USER);
        $this->assertEquals(
            'The message',
            $message->getMessage(),
        );
        $this->assertEquals(
            MessageRole::USER,
            $message->getMessageRole(),
        );
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->chatRepository = new DbalChatRepository(
            $this->getConnection(),
            PausedClock::on(SerializableDateTime::fromString('2019-08-14')),
            $this->getContainer()->get(SettingsRepository::class),
        );
    }
}
