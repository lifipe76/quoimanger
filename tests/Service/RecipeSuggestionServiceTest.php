<?php

namespace App\Tests\Service;

use App\Entity\Recette;
use App\Entity\RecetteRealisation;
use App\Service\RecipeSuggestionService;
use PHPUnit\Framework\TestCase;

class RecipeSuggestionServiceTest extends TestCase
{
    private RecipeSuggestionService $service;

    protected function setUp(): void
    {
        $this->service = new RecipeSuggestionService();
    }

    public function testReturnsNullOnEmptyRecipes(): void
    {
        $this->assertNull($this->service->getSuggestion([]));
    }

    public function testPrioritizesDayOfWeekHabit(): void
    {
        // Supposons que nous testons un dimanche (jour 7)
        $sunday = new \DateTimeImmutable('2026-10-04'); // Dimanche

        $recetteSunday = new Recette();
        $recetteSunday->setDesignation('Poulet rôti dominical');
        // 3 fois le dimanche dans le passé (il y a 7, 14 et 21 jours)
        foreach ([7, 14, 21] as $daysAgo) {
            $r = new RecetteRealisation();
            $r->setRealiseAt($sunday->modify("-{$daysAgo} days"));
            $recetteSunday->addRealisation($r);
        }

        $recetteOther = new Recette();
        $recetteOther->setDesignation('Pâtes du mardi');
        // 3 fois le mardi (pas le dimanche), il y a 5, 12, 19 jours
        foreach ([5, 12, 19] as $daysAgo) {
            $r = new RecetteRealisation();
            $r->setRealiseAt($sunday->modify("-{$daysAgo} days"));
            $recetteOther->addRealisation($r);
        }

        $suggestion = $this->service->getSuggestion([$recetteOther, $recetteSunday], $sunday);

        $this->assertNotNull($suggestion);
        $this->assertSame($recetteSunday, $suggestion['recette']);
        $this->assertStringContainsString('Habitude le dimanche', $suggestion['reason']);
    }

    public function testDeprioritizesRecentlyEatenMeal(): void
    {
        $today = new \DateTimeImmutable('today');

        // Recette A : un grand favori (10 fois) mais mangé hier (1 jour)
        $recetteHier = new Recette();
        $recetteHier->setDesignation('Pizza maison');
        $rHier = new RecetteRealisation();
        $rHier->setRealiseAt($today->modify('-1 day'));
        $recetteHier->addRealisation($rHier);
        for ($i = 2; $i <= 10; $i++) {
            $r = new RecetteRealisation();
            $r->setRealiseAt($today->modify("-{$i}0 days"));
            $recetteHier->addRealisation($r);
        }

        // Recette B : mangée il y a 25 jours
        $recetteAncienne = new Recette();
        $recetteAncienne->setDesignation('Gratin dauphinois');
        $rAncien = new RecetteRealisation();
        $rAncien->setRealiseAt($today->modify('-25 days'));
        $recetteAncienne->addRealisation($rAncien);

        $suggestion = $this->service->getSuggestion([$recetteHier, $recetteAncienne], $today);

        $this->assertNotNull($suggestion);
        $this->assertSame($recetteAncienne, $suggestion['recette'], 'Should not suggest meal eaten yesterday');
    }

    public function testFavoriteBonusWhenNotRecentlyEaten(): void
    {
        $today = new \DateTimeImmutable('today');

        // Recette favorite (cuisinée 8 fois, dernière fois il y a 20 jours)
        $recetteFavori = new Recette();
        $recetteFavori->setDesignation('Boeuf Bourguignon');
        for ($i = 1; $i <= 8; $i++) {
            $r = new RecetteRealisation();
            $r->setRealiseAt($today->modify('-' . ($i * 20) . ' days'));
            $recetteFavori->addRealisation($r);
        }

        // Recette rare (cuisinée 1 fois, dernière fois il y a 20 jours)
        $recetteRare = new Recette();
        $recetteRare->setDesignation('Soupe à l\'oignon');
        $r = new RecetteRealisation();
        $r->setRealiseAt($today->modify('-20 days'));
        $recetteRare->addRealisation($r);

        $suggestion = $this->service->getSuggestion([$recetteRare, $recetteFavori], $today);

        $this->assertNotNull($suggestion);
        $this->assertSame($recetteFavori, $suggestion['recette']);
        $this->assertStringContainsString('favoris', $suggestion['reason']);
    }
}
