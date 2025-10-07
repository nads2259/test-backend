<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NotificationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $httpClient,
        private readonly string $fromEmail,
        private readonly ?string $defaultSlackWebhook,
        private readonly string $smtpHost,
        private readonly int $smtpPort
    ) {
    }

    public function sendEmail(?string $recipient, string $subject, string $body): bool
    {
        if (! $recipient) {
            return false;
        }

        $headers = [
            'From: ' . $this->fromEmail,
            'To: ' . $recipient,
            'Subject: ' . $subject,
            'Content-Type: text/plain; charset=UTF-8',
        ];

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";

        $socket = @stream_socket_client(sprintf('%s:%d', $this->smtpHost, $this->smtpPort), $errno, $errstr, 5);
        if (! $socket) {
            $this->logger->error('Unable to connect to SMTP server', [
                'host' => $this->smtpHost,
                'port' => $this->smtpPort,
                'errno' => $errno,
                'errstr' => $errstr,
            ]);

            return false;
        }

        try {
            $this->expect($socket, 220);
            $this->command($socket, 'HELO backend-home-task', 250);
            $this->command($socket, sprintf('MAIL FROM:<%s>', $this->fromEmail), 250);
            $this->command($socket, sprintf('RCPT TO:<%s>', $recipient), 250);
            $this->command($socket, 'DATA', 354);
            $this->write($socket, $message . "\r\n");
            $this->expect($socket, 250);
            $this->command($socket, 'QUIT', 221, false);

            $this->logger->info('Email notification dispatched', [
                'recipient' => $recipient,
                'subject' => $subject,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email notification', [
                'recipient' => $recipient,
                'subject' => $subject,
                'exception' => $e,
            ]);

            return false;
        } finally {
            fclose($socket);
        }
    }

    public function sendSlack(?string $webhookUrl, string $message): bool
    {
        $target = $webhookUrl ?: $this->defaultSlackWebhook;
        if (! $target) {
            return false;
        }

        try {
            $response = $this->httpClient->request('POST', $target, [
                'json' => ['text' => $message],
            ]);

            if ($response->getStatusCode() >= 400) {
                $this->logger->error('Slack webhook returned an error status', [
                    'status' => $response->getStatusCode(),
                    'content' => $response->getContent(false),
                ]);

                return false;
            }

            $this->logger->info('Slack notification dispatched', [
                'webhook' => $target,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send Slack notification', [
                'webhook' => $target,
                'exception' => $e,
            ]);

            return false;
        }
    }

    private function command($socket, string $command, int $expectedCode, bool $appendCrlf = true): void
    {
        $this->write($socket, $appendCrlf ? $command . "\r\n" : $command);
        $this->expect($socket, $expectedCode);
    }

    private function write($socket, string $payload): void
    {
        $bytes = fwrite($socket, $payload);
        if ($bytes === false) {
            throw new \RuntimeException('Failed to write to SMTP socket');
        }
    }

    private function expect($socket, int $code): void
    {
        $response = fgets($socket);
        if ($response === false || (int) substr($response, 0, 3) !== $code) {
            throw new \RuntimeException(sprintf('Unexpected SMTP response, expected %d got %s', $code, $response));
        }
    }
}
