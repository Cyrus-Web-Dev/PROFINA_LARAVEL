<?php

namespace App\Mail\Transport;

use App\Services\GmailApiService;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\MessageConverter;

/**
 * Transport Laravel Mail -> Gmail API. Dipakai oleh email bawaan Laravel
 * (mis. link reset password) lewat MAIL_MAILER=gmail_api.
 *
 * Kegagalan SENGAJA dilempar (GmailSendException), tidak ditelan seperti versi lama,
 * supaya pemanggil tahu email tidak terkirim.
 */
class GmailApiTransport extends AbstractTransport
{
    public function __construct(private GmailApiService $gmail)
    {
        parent::__construct();
    }

    protected function doSend(SentMessage $message): void
    {
        $mime = MessageConverter::toEmail($message->getOriginalMessage())->toString();

        $this->gmail->sendRawMime($mime);
    }

    public function __toString(): string
    {
        return 'gmail_api';
    }
}
