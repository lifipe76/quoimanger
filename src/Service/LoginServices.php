<?php

namespace App\Service;

use App\Service\MailerSyncService;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Translation\LocaleSwitcher;
use Symfony\Contracts\Translation\TranslatorInterface;

class LoginServices extends AbstractController
{

    use ResetPasswordControllerTrait;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ResetPasswordHelperInterface $resetPasswordHelper,
        private MailerSyncService $mailerSyncService,
        private UserRepository $userRepository,
        private LocaleSwitcher $localeSwitcher,
        private TranslatorInterface $translator,
    ) {}

    public function processSendingPasswordResetEmail(
        string $adresseEmail,
    ) {
        $user = $this->userRepository->findOneBy([
            'email' => $adresseEmail,
        ]);

        if (!$user) {
            return false;
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface $e) {
            return false;
        }

        $isDev = ($_ENV['DEVLOCAL'] ?? false) || ($_ENV['LIFIPE'] ?? false);
        $emailTitre = ($isDev ? 'TEST ' : '') . $this->translator->trans('reset_password.mail.sujet');
        $clientName = $_ENV['CLIENT'] ?? 'QuoiManger';
        $senderMail = $_ENV['MAIL_WEBMASTER'] ?? 'webmaster@quoimanger.local';
        $email = (new TemplatedEmail())
            ->from(new Address($senderMail, $clientName))
            ->to($user->getEmailForMailer())
            ->subject($emailTitre)
            ->htmlTemplate('_email/_logs/reset_password.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'mail_title' => $emailTitre,
                'langue' => $this->localeSwitcher->getLocale(),
            ]);

        if (!empty($_ENV['MAIL_DEV'])) {
            $email->bcc($_ENV['MAIL_DEV']);
        }


        $this->mailerSyncService->sendSync($email);
        // } catch (\Exception $e) {
        // }


        $this->setTokenObjectInSession($resetToken);

        return true;
    }

    public function AppCreateToken(
        USER $user
    ) {
        $token = bin2hex(random_bytes(32));

        $user->setAppToken($token);

        $this->entityManager->persist($user);

        $this->entityManager->flush();

        return $token;
    }
}
