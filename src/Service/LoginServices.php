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

        // try {
        $emailTitre = ($_ENV['DEVLOCAL'] || $_ENV['LIFIPE'] ? 'TEST ' : '') . $this->translator->trans('reset_password.mail.sujet');
        $email = (new TemplatedEmail())
            ->from(new Address($_ENV['MAIL_WEBMASTER'], ($_ENV['CLIENT'])))
            ->to($user->getEmailForMailer())
            ->bcc($_ENV['MAIL_DEV'])
            ->subject($emailTitre)
            ->htmlTemplate('_email/_logs/reset_password.html.twig')
            ->context([
                'resetToken' => $resetToken,
                'mail_title' => $emailTitre,
                'langue' => $this->localeSwitcher->getLocale(),
            ]);


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
