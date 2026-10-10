<?php

namespace App\Tests\Controller;

use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class CalendarViewTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);

        $user = $this->em->getRepository(User::class)->findOneBy(['email' => 'test@test.com']);
        if ($user) {
            $this->client->loginUser($user);
        }
    }

    public function testHomePageDisplaysViewSwitcherAndCalendar(): void
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // 1. Boutons de bascule de vue
        $this->assertSelectorExists('.home-view-toggle-bar');
        $this->assertSelectorExists('#btn-view-timeline');
        $this->assertSelectorExists('#btn-view-calendar');
        $this->assertSelectorExists('#home-view-timeline');
        $this->assertSelectorExists('#home-view-calendar');

        // 2. Éléments du calendrier
        $this->assertSelectorExists('#calendar-app');
        $this->assertSelectorExists('.calendar-header-card');
        $this->assertSelectorExists('#calendar-prev-btn');
        $this->assertSelectorExists('#calendar-next-btn');
        $this->assertSelectorExists('#calendar-today-btn');
        $this->assertSelectorExists('#calendar-current-month-label');
        $this->assertSelectorExists('.calendar-grid-card');
        $this->assertSelectorExists('.calendar-weekdays-row');
        $this->assertSelectorExists('#calendar-days-grid');
        $this->assertSelectorExists('#calendar-day-details-panel');
        $this->assertSelectorExists('#calendar-meals-data');
    }

    public function testCalendarSerializesMealDataCorrectly(): void
    {
        // Créer une recette et une réalisation de test
        $recette = new Recette();
        $recette->setDesignation('Paella royale calendrier test');
        $recette->setPhoto('test_paella.webp');
        $this->em->persist($recette);

        $realisation = new RecetteRealisation();
        $realisation->setRecette($recette);
        $realisation->setRealiseAt(new \DateTimeImmutable('2026-10-15'));
        $realisation->setMoment('midi');
        $realisation->setComplement('riz safrané');
        $this->em->persist($realisation);
        $this->em->flush();

        $crawler = $this->client->request('GET', '/?view=calendar');
        $this->assertResponseIsSuccessful();

        $jsonData = $crawler->filter('#calendar-meals-data')->text();
        $this->assertNotEmpty($jsonData);

        $meals = json_decode($jsonData, true);
        $this->assertIsArray($meals);

        // Trouver notre repas dans les données JSON
        $found = null;
        foreach ($meals as $m) {
            if ($m['title'] === 'Paella royale calendrier test') {
                $found = $m;
                break;
            }
        }

        $this->assertNotNull($found, 'Le repas créé doit être présent dans le flux JSON du calendrier');
        $this->assertEquals('2026-10-15', $found['date']);
        $this->assertEquals('midi', $found['moment']);
        $this->assertEquals('Midi', $found['momentLabel']);
        $this->assertEquals('riz safrané', $found['complement']);
        $this->assertTrue($found['hasPhoto']);
        $this->assertStringContainsString('test_paella.webp', $found['photoUrl']);
        $this->assertNotEmpty($found['updateDateUrl']);
        $this->assertNotEmpty($found['updateToken']);
        $this->assertNotEmpty($found['deleteUrl']);
        $this->assertNotEmpty($found['deleteToken']);

        // Nettoyage
        $this->em->remove($realisation);
        $this->em->remove($recette);
        $this->em->flush();
    }
}
