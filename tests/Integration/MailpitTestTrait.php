<?php

namespace App\Tests\Integration;

trait MailpitTestTrait
{
    private function clearMailpit(): void
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'DELETE',
                'ignore_errors' => true,
            ],
        ]);

        file_get_contents('http://mailer:8025/api/v1/messages', false, $context);
    }

    private function fetchMailpitMessages(): array
    {
        $payload = json_decode((string) file_get_contents('http://mailer:8025/api/v1/messages'), true, 512, JSON_THROW_ON_ERROR);

        return $payload['messages'] ?? [];
    }

    private function fetchMailpitMessage(string $id): array
    {
        return json_decode((string) file_get_contents(sprintf('http://mailer:8025/api/v1/message/%s', $id)), true, 512, JSON_THROW_ON_ERROR);
    }

    private function fetchSingleMailpitMessage(): array
    {
        $messages = [];

        for ($attempt = 0; $attempt < 20; ++$attempt) {
            $messages = $this->fetchMailpitMessages();

            if (1 === count($messages)) {
                break;
            }

            usleep(100000);
        }

        self::assertCount(1, $messages);

        return $this->fetchMailpitMessage($messages[0]['ID']);
    }
}
