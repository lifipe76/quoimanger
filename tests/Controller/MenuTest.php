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
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);
        $this->client->loginUser($user);

        $this->client->request('GET', '/recettes');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.nav-header');
        $this->assertSelectorTextContains('.nav-links a.active', 'Recettes');
    }

    public function testMenuIsRenderedOnIngredientsPageWithActiveIngredientsLink(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);
        $this->client->loginUser($user);

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
        $this->assertSelectorExists('.popup-menu-user a[href="/compte?tab=famille"]');
        $this->assertSelectorTextContains('.popup-menu-user a[href="/compte?tab=famille"]', 'Ma famille');
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
        $this->assertStringContainsString('.account-tabs', $this->client->getResponse()->getContent());
        $this->assertSelectorExists('.account-tabs');
        $this->assertSelectorExists('.account-tab-btn[data-tab="profil"].active');
        $this->assertSelectorExists('.account-tab-btn[data-tab="famille"]');
        $this->assertSelectorExists('#tab-profil.active');
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

    public function testAccountFamilleTabDirectAccess(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);

        $this->client->loginUser($user);
        $this->client->request('GET', '/compte?tab=famille');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.account-tabs');
        $this->assertSelectorExists('.account-tab-btn[data-tab="famille"].active');
        $this->assertSelectorExists('#tab-famille.active');
        $this->assertSelectorExists('.family-invite-box');
    }

    public function testBottomAppNavigationIsRenderedWithItemsAndActiveStates(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        $this->assertNotNull($user);
        $this->client->loginUser($user);

        // 1. Sur la page d'accueil (Timeline)
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Vérification de la présence de la barre de navigation mobile
        $this->assertSelectorExists('.bottom-app-nav');
        $this->assertSelectorExists('.bottom-app-nav a[href="/"].active');
        $this->assertSelectorExists('.bottom-app-nav a[href="/recettes"]');
        $this->assertSelectorExists('.bottom-app-nav a[href="/ingredients"]');
        $this->assertSelectorExists('.bottom-app-nav a[href="/compte"]');
        $this->assertSelectorExists('.bottom-nav-center-action');

        // Vérification des métadonnées viewport et PWA
        $this->assertSelectorExists('meta[name="viewport"]');
        $this->assertSelectorExists('link[rel="manifest"][href="/manifest.json"]');
        $this->assertSelectorExists('meta[name="theme-color"][content="#db4807"]');
        $this->assertSelectorExists('meta[name="apple-mobile-web-app-capable"][content="yes"]');

        // 2. Sur la page des recettes
        $this->client->request('GET', '/recettes');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.bottom-app-nav a[href="/recettes"].active');

        // 3. Sur la page des ingrédients
        $this->client->request('GET', '/ingredients');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.bottom-app-nav a[href="/ingredients"].active');
    }

    public function testPwaManifestAndServiceWorkerAreAccessible(): void
    {
        // 1. Test du manifest
        $this->client->request('GET', '/manifest.json');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/manifest+json; charset=utf-8');

        $manifestData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($manifestData);
        $this->assertEquals('QuoiManger - Suivi des repas & Recettes', $manifestData['name']);
        $this->assertEquals('QuoiManger', $manifestData['short_name']);
        $this->assertEquals('standalone', $manifestData['display']);
        $this->assertEquals('#db4807', $manifestData['theme_color']);
        $this->assertNotEmpty($manifestData['icons']);

        // 2. Test du Service Worker
        $this->client->request('GET', '/sw.js');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/javascript; charset=utf-8');
        $this->assertResponseHeaderSame('service-worker-allowed', '/');
        $this->assertStringContainsString('CACHE_NAME', $this->client->getResponse()->getContent());
    }
}
