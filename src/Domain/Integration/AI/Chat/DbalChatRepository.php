<?php

declare(strict_types=1);

namespace App\Domain\Integration\AI\Chat;

use App\Domain\Settings\SettingsRepository;
use App\Infrastructure\Repository\DbalRepository;
use App\Infrastructure\Time\Clock\Clock;
use App\Infrastructure\ValueObject\Time\SerializableDateTime;
use Doctrine\DBAL\Connection;
use NeuronAI\Chat\Enums\MessageRole;

final readonly class DbalChatRepository extends DbalRepository implements ChatRepository
{
    public function __construct(
        Connection $connection,
        private Clock $clock,
        private SettingsRepository $settingsRepository,
    ) {
        parent::__construct($connection);
    }

    public function add(ChatMessage $message): void
    {
        $sql = 'INSERT INTO ChatMessage (messageId, message, messageRole, `on`) 
                VALUES (:messageId, :message, :messageRole, :on)';

        $this->connection->executeStatement($sql, [
            'messageId' => $message->getMessageId(),
            'message' => $message->getMessage(),
            'messageRole' => $message->getMessageRole()->value,
            'on' => $message->getOn(),
        ]);
    }

    public function findAll(): array
    {
        $results = $this->connection->executeQuery('SELECT * FROM ChatMessage ORDER BY `on` ASC')
            ->fetchAllAssociative();

        $general = $this->settingsRepository->general();

        $history = [];
        foreach ($results as $result) {
            $history[] = new ChatMessage(
                messageId: ChatMessageId::fromString($result['messageId']),
                message: (string) $result['message'],
                messageRole: MessageRole::from($result['messageRole']),
                on: SerializableDateTime::fromString($result['on']),
            )->withUserProfilePictureUrl($general->getProfilePictureUrl())
                ->withFirstLetterOfFirstName($general->getAthlete()->getFirstLetterOfFirstName());
        }

        return $history;
    }

    public function clear(): void
    {
        $this->connection->executeStatement('DELETE FROM ChatMessage');
    }

    public function buildMessage(string $message, MessageRole $messageRole): ChatMessage
    {
        $general = $this->settingsRepository->general();

        return new ChatMessage(
            messageId: ChatMessageId::random(),
            message: $message,
            messageRole: $messageRole,
            on: $this->clock->getCurrentDateTimeImmutable()
        )->withUserProfilePictureUrl($general->getProfilePictureUrl())
            ->withFirstLetterOfFirstName(substr((string) $general->getAthlete()->getName(), 0, 1));
    }
}
