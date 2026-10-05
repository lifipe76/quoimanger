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

            $emailTo = $membre->getEmail();
            if (!$emailTo) {
                continue;
            }

            try {
                $email = (new TemplatedEmail())
                    ->from(new Address('no-reply@quoimanger.fr', 'QuoiManger'))
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

                $this->mailerSyncService->sendSync($email);
            } catch (\Throwable $e) {
                $this->logger->warning("Impossible d'envoyer l'email de proposition à {$emailTo}: " . $e->getMessage());
            }
        }
    }
}
