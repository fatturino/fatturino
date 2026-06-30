<?php

namespace App\Mail;

use Illuminate\Http\Client\Factory as HttpFactory;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\MessageConverter;
use Symfony\Component\Mime\Part\DataPart;

class ScalewayTemTransport extends AbstractTransport
{
    private const MAX_ATTACHMENT_BYTES = 2 * 1024 * 1024;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $secretKey,
        private readonly string $projectId,
        private readonly string $region = 'fr-par',
    ) {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        if ($this->secretKey === '' || $this->projectId === '') {
            throw new TransportException('Scaleway TEM non configurato: secret key e project ID sono obbligatori.');
        }

        $email = MessageConverter::toEmail($message->getOriginalMessage());

        $payload = array_filter([
            'from' => $this->formatAddress($this->resolveSender($email, $message->getEnvelope())),
            'to' => $this->formatAddresses($email->getTo()),
            'cc' => $this->formatAddresses($email->getCc()),
            'bcc' => $this->formatAddresses($email->getBcc()),
            'subject' => $email->getSubject(),
            'text' => $email->getTextBody(),
            'html' => $email->getHtmlBody(),
            'project_id' => $this->projectId,
            'attachments' => $this->formatAttachments($email),
            'additional_headers' => $this->formatAdditionalHeaders($email),
        ], fn ($value) => $value !== null && $value !== [] && $value !== '');

        $response = $this->http
            ->withHeaders(['X-Auth-Token' => $this->secretKey])
            ->asJson()
            ->post($this->endpoint(), $payload);

        if ($response->failed()) {
            throw new TransportException(sprintf(
                'Invio Scaleway TEM non riuscito (%s): %s',
                $response->status(),
                $response->body(),
            ));
        }

        $messageId = $response->json('id') ?? $response->json('email_id');
        if (is_string($messageId) && $messageId !== '') {
            $message->setMessageId($messageId);
        }
    }

    private function endpoint(): string
    {
        return sprintf(
            'https://api.scaleway.com/transactional-email/v1alpha1/regions/%s/emails',
            $this->region ?: 'fr-par',
        );
    }

    private function resolveSender(Email $email, Envelope $envelope): Address
    {
        return $email->getFrom()[0] ?? $envelope->getSender();
    }

    private function formatAddress(Address $address): array
    {
        return array_filter([
            'name' => $address->getName(),
            'email' => $address->getAddress(),
        ], fn (?string $value) => $value !== null && $value !== '');
    }

    /**
     * @param  Address[]  $addresses
     */
    private function formatAddresses(array $addresses): array
    {
        return array_map(fn (Address $address) => $this->formatAddress($address), $addresses);
    }

    private function formatAttachments(Email $email): array
    {
        return array_map(function (DataPart $attachment) {
            $content = $attachment->getBody();

            if (strlen($content) > self::MAX_ATTACHMENT_BYTES) {
                throw new TransportException('Scaleway TEM API accetta allegati fino a 2 MB.');
            }

            return [
                'name' => $attachment->getFilename() ?? 'attachment',
                'type' => $attachment->getContentType(),
                'content' => base64_encode($content),
            ];
        }, $email->getAttachments());
    }

    private function formatAdditionalHeaders(Email $email): array
    {
        $headers = [];
        $ignored = ['from', 'to', 'cc', 'bcc', 'sender', 'subject', 'content-type'];

        foreach ($email->getHeaders()->all() as $header) {
            if (in_array(strtolower($header->getName()), $ignored, true)) {
                continue;
            }

            $headers[] = [
                'key' => $header->getName(),
                'value' => $header->getBodyAsString(),
            ];
        }

        return $headers;
    }

    public function __toString(): string
    {
        return 'scaleway_tem';
    }
}
