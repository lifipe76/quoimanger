<?php

namespace App\Tests\Controller;

use App\Entity\ResetPasswordRequest;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

class AuthAndResetPasswordTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        if ($user) {
            $user->setPassword($hasher->hashPassword($user, 'Testtest1'));
            $em->flush();
        }
    }

    public function testLoginPageIsRenderedInSiteStyle(): void
    {
        $crawler = $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Connexion');
        $this->assertSelectorNotExists('.nav-header');
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
        $this->assertSelectorExists('input[name="_csrf_token"]');
        $this->assertSelectorTextContains('a[href="/reset-password"]', 'Mot de passe oublié ?');
        $this->assertSelectorTextContains('button[type="submit"]', 'Se connecter');
    }

    public function testSuccessfulLoginWithSeededUser(): void
    {
        $crawler = $this->client->request('GET', '/login');

        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'test@test.com',
            '_password' => 'Testtest1',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('http://localhost/');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.nav-links', 'Se déconnecter');
    }

    public function testAlreadyLoggedInShowsConnectedState(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        $this->client->loginUser($user);
        $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.card h1', 'Vous êtes déjà connecté');
        $this->assertSelectorTextContains('.card', 'test@test.com');
        $this->assertSelectorExists('a[href="/logout"]');
    }

    public function testForgotPasswordRequestPageIsRenderedInSiteStyle(): void
    {
        $this->client->request('GET', '/reset-password');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mot de passe oublié');
        $this->assertSelectorNotExists('.nav-header');
        $this->assertSelectorExists('input[name="reset_password_request_form[email]"]');
        $this->assertSelectorTextContains('button[type="submit"]', 'Envoyer le lien de réinitialisation');
    }

    public function testForgotPasswordRequestSubmissionRedirectsToCheckEmail(): void
    {
        $crawler = $this->client->request('GET', '/reset-password');

        $form = $crawler->selectButton('Envoyer le lien de réinitialisation')->form([
            'reset_password_request_form[email]' => 'test@test.com',
        ]);

        $this->client->submit($form);

        $this->assertResponseRedirects('/reset-password/check-email');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Vérifiez vos emails');
        $this->assertSelectorExists('a[href="/login"]');
        $this->assertSelectorExists('a[href="/reset-password"]');
    }

    public function testResetPasswordFormWithValidToken(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        $requestRepo = $em->getRepository(ResetPasswordRequest::class);
        $requestRepo->removeRequests($user);

        $resetHelper = static::getContainer()->get(ResetPasswordHelperInterface::class);
        $token = $resetHelper->generateResetToken($user)->getToken();

        // Access via token URL redirects to /reset-password/reset
        $this->client->request('GET', '/reset-password/reset/' . $token);
        $this->assertResponseRedirects('/reset-password/reset');

        $crawler = $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Nouveau mot de passe');
        $this->assertSelectorTextContains('.nav-brand', '🍽️ QuoiManger');
        $this->assertSelectorExists('input[type="password"]');
        $this->assertSelectorTextContains('button[type="submit"]', 'Enregistrer le mot de passe');

        // Submit new password form
        $form = $crawler->selectButton('Enregistrer le mot de passe')->form([
            'change_password_form[plainPassword][first]' => 'NewPassword123!',
            'change_password_form[plainPassword][second]' => 'NewPassword123!',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/');

        $this->client->followRedirect();
        // Since home requires ROLE_USER and user is not auto-logged in, it redirects to /login
        $this->assertResponseRedirects('http://localhost/login');
    }
}
