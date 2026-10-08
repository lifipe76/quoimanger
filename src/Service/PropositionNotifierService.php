<?php

namespace App\Service;

use App\Entity\RepasProposition;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;

class PropositionNotifierService
{
    public function __construct(
        private MailerSyncService $mailerSyncService,
        private LoggerInterface $logger,
    ) {}

    public function notifyFamille(RepasProposition $proposition): void
    {
        $famille = $proposition->getFamille();
        $proposeur = $proposition->getProposePar();

        if (!$famille || !$proposeur) {
            return;
        }

        $dsn = $_ENV['MAILER_DSN'] ?? '';
        // Si DSN local, vérifier rapidement (0.3s max) si le port est ouvert pour ne jamais bloquer l'application
        if (str_contains($dsn, 'localhost') || str_contains($dsn, '127.0.0.1')) {
            $parsed = parse_url($dsn);
            $port = (int) ($parsed['port'] ?? 25);
            $fp = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.3);
            if (!$fp) {
                $this->logger->warning("Serveur SMTP local (port {$port}) injoignable, notification email ignorée pour éviter de bloquer l'application.");
                return;
            }
            fclose($fp);
        }

        $isToday = $proposition->getDateRepas()?->format('Y-m-d') === (new \DateTimeImmutable())->format('Y-m-d');
        $moment = strtolower($proposition->getMoment());

        if ($isToday) {
            $momentLabel = match ($moment) {
                'midi' => 'de ce midi',
                'matin' => 'de ce matin',
                default => 'de ce soir',
            };
        } else {
            $dateStr = $proposition->getDateRepas()?->format('d/m/Y') ?? '';
            $momentLabel = "du {$moment} ({$dateStr})";
        }

        $subject = "QuoiManger : Demande pour le repas {$momentLabel}";

        foreach ($famille->getMembres() as $membre) {
            if ($membre->getId() === $proposeur->getId()) {
                continue;
            }

            $emailTo = method_exists($membre, 'getEmailForMailer') ? ($membre->getEmailForMailer() ?? $membre->getEmail()) : $membre->getEmail();
            if (!$emailTo) {
                continue;
            }

            try {
                $senderMail = $_ENV['MAIL_WEBMASTER'] ?? 'no-reply@quoimanger.fr';
                $clientName = $_ENV['CLIENT'] ?? 'QuoiManger';

                $email = (new TemplatedEmail())
                    ->from(new Address($senderMail, $clientName))
                    ->to(new Address($emailTo, $membre->getDisplayName()))
                    ->subject($subject)
                    ->htmlTemplate('_email/proposition_repas.html.twig')
                    ->context([
                        'mail_title' => $subject,
                        'moment_label' => $momentLabel,
                        'proposition' => $proposition,
                        'proposeur' => $proposeur,
                        'famille' => $famille,
                        'destinataire' => $membre,
                    ]);

                if (!empty($_ENV['MAIL_DEV'])) {
                    $email->bcc($_ENV['MAIL_DEV']);
                }

                $this->mailerSyncService->sendSync($email);
            } catch (\Throwable $e) {
                $this->logger->warning("Impossible d'envoyer l'email de proposition à {$emailTo}: " . $e->getMessage());
                // En cas d'erreur de connexion/timeout, ne pas répéter l'attente sur les autres membres
                break;
            }
        }
    }
}
