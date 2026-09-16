<?php

namespace App\Service;

use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

class MailerSyncService
{
    public function __construct(
        private TransportInterface $transport
    ) {}

    public function sendSync(Email $email): void
    {
        $this->transport->send($email); // Envoi 100% synchrone
    }
}
