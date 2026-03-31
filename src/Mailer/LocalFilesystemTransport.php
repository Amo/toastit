<?php

namespace App\Mailer;

use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\TransportFactoryInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\RawMessage;

final class LocalFilesystemTransport extends AbstractTransport implements TransportFactoryInterface
{
    public function __construct(
        private readonly string $projectDir,
        ?\Symfony\Component\EventDispatcher\EventDispatcherInterface $dispatcher = null,
        ?\Psr\Log\LoggerInterface $logger = null,
    ) {
        parent::__construct($dispatcher, $logger);
    }

    public function create(Dsn $dsn): self
    {
        if (!$this->supports($dsn)) {
            throw new \InvalidArgumentException(sprintf('Unsupported DSN "%s".', (string) $dsn));
        }

        return $this;
    }

    public function supports(Dsn $dsn): bool
    {
        return 'local' === $dsn->getScheme();
    }

    public function __toString(): string
    {
        return 'local://default';
    }

    protected function doSend(SentMessage $message): void
    {
        $rawMessage = $message->getOriginalMessage();

        if (!$rawMessage instanceof RawMessage) {
            return;
        }

        $payload = $this->buildPayload($rawMessage, $message->getEnvelope());
        $directory = sprintf('%s/var/storage/mails', $this->projectDir);

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException(sprintf('Unable to create mail storage directory "%s".', $directory));
        }

        $filename = sprintf(
            '%s/%s-%s.json',
            $directory,
            (new \DateTimeImmutable())->format('YmdHis'),
            bin2hex(random_bytes(4))
        );

        file_put_contents($filename, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function buildPayload(RawMessage $rawMessage, Envelope $envelope): array
    {
        if ($rawMessage instanceof Email) {
            return [
                'subject' => $rawMessage->getSubject(),
                'from' => array_map(static fn ($address) => $address->toString(), $rawMessage->getFrom()),
                'to' => array_map(static fn ($address) => $address->toString(), $rawMessage->getTo()),
                'envelope' => [
                    'sender' => $envelope->getSender()?->toString(),
                    'recipients' => array_map(static fn ($address) => $address->toString(), $envelope->getRecipients()),
                ],
                'html' => $rawMessage->getHtmlBody(),
                'text' => $rawMessage->getTextBody(),
            ];
        }

        return [
            'raw' => $rawMessage->toString(),
        ];
    }
}
