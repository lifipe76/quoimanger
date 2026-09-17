<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class MenuTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        if ($user) {
            $user->setFirstname('Test');
            $user->setLastname('User');
            $user->setPassword($hasher->hashPassword($user, 'Testtest1'));
            $em->flush();
        }
    }

    public function testMenuIsOmittedOnLoginPage(): void
    {
        $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('.nav-header');
    }

    public function testMenuIsRenderedOnRecettesPageWithActiveRecettesLink(): void
    {
        $this->client->request('GET', '/recettes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.nav-header');
        $this->assertSelectorTextContains('.nav-links a.active', 'Recettes');
    }

    public function testMenuIsRenderedOnIngredientsPageWithActiveIngredientsLink(): void
    {
        $this->client->request('GET', '/ingredients');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.nav-header');
        $this->assertSelectorTextContains('.nav-links a.active', 'Ingrédients');
    }

    public function testMenuIsRenderedOnHomePageWhenAuthenticatedWithPopupAndLogoutIcon(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        $this->client->loginUser($user);
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.nav-header');

        // Check avatarMenuImg disconnect icon
        $this->assertSelectorExists('#avatarMenuImg');
        $this->assertSelectorExists('#avatarMenuImg i.fa-sign-out-alt');

        // Check popup menu with account and logout items
        $this->assertSelectorExists('.popup-menu-user');
        $this->assertSelectorTextContains('.popup-user-name', 'Test User');
        $this->assertSelectorTextContains('.popup-user-role', 'test@test.com');
        $this->assertSelectorExists('.popup-menu-user a[href="/compte"]');
        $this->assertSelectorTextContains('.popup-menu-user a[href="/compte"]', 'Mon compte');
        $this->assertSelectorExists('.popup-menu-user a[href="http://localhost/logout"]');
    }

    public function testAccountProfilePageAccessAndEdit(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/compte');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mon compte');
        $this->assertSelectorExists('input[name="user_profile[firstname]"]');
        $this->assertSelectorExists('input[name="user_profile[lastname]"]');
        $this->assertSelectorExists('input[name="user_profile[email]"]');

        // Submit form to update profile name
        $form = $crawler->selectButton('Enregistrer les modifications')->form([
            'user_profile[firstname]' => 'Julien',
            'user_profile[lastname]' => 'Dupont',
            'user_profile[email]' => 'test@test.com',
        ]);

        $this->client->submit($form);
        $this->assertResponseRedirects('/compte');

        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-success', 'Vos informations de compte ont été mises à jour');
        $this->assertSelectorTextContains('.popup-user-name', 'Julien Dupont');
    }
}
