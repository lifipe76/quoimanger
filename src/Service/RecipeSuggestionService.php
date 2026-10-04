<?php

namespace App\Service;

use App\Entity\Recette;

class RecipeSuggestionService
{
    /**
     * Calcule la meilleure suggestion de recette pour un jour donné selon 3 critères :
     * 1. Habitude du jour de la semaine (recette souvent cuisinée ce jour-là)
     * 2. Recette préférée (fréquence globale de réalisation)
     * 3. Ancienneté de la dernière dégustation (évite ce qu'on vient de manger, favorise ce qui date)
     *
     * @param Recette[] $recettes
     * @return array{recette: Recette, reason: string}|null
     */
    public function getSuggestion(array $recettes, ?\DateTimeImmutable $forDate = null): ?array
    {
        if (empty($recettes)) {
            return null;
        }

        $forDate = $forDate ?? new \DateTimeImmutable('today');
        $currentDayOfWeek = (int) $forDate->format('N'); // 1 (Lundi) à 7 (Dimanche)

        $joursNoms = [
            1 => 'le lundi',
            2 => 'le mardi',
            3 => 'le mercredi',
            4 => 'le jeudi',
            5 => 'le vendredi',
            6 => 'le samedi',
            7 => 'le dimanche',
        ];

        $bestRecette = null;
        $bestScore = -PHP_FLOAT_MAX;
        $bestReason = '';

        foreach ($recettes as $recette) {
            $realisations = $recette->getRealisations();
            $totalCount = $realisations->count();
            $daysSince = $recette->getDaysSinceLastRealisation();

            // 1. Habitude du jour de la semaine
            $dayOfWeekCount = 0;
            foreach ($realisations as $r) {
                if ($r->getRealiseAt() && (int) $r->getRealiseAt()->format('N') === $currentDayOfWeek) {
                    $dayOfWeekCount++;
                }
            }

            // Calcul du score
            $score = 0;

            // Critère 1 : Habitude du jour (+25 points par réalisation ce même jour)
            $score += $dayOfWeekCount * 25;

            // Critère 2 : Recette préférée (+6 points par réalisation au total)
            $score += $totalCount * 6;

            // Critère 3 : Temps depuis la dernière fois
            if ($daysSince === 0) {
                // Déjà mangé aujourd'hui
                $score -= 2000;
            } elseif ($daysSince === 1) {
                // Mangé hier
                $score -= 1000;
            } elseif ($daysSince === 2) {
                $score -= 100;
            } elseif ($daysSince === null) {
                // Jamais cuisiné : opportunité de découverte
                $score += 35;
            } else {
                // Plus ça fait longtemps, plus c'est recommandé
                $score += min($daysSince, 60) * 1.5;
            }

            // Explication de la suggestion
            $reasons = [];
            if ($dayOfWeekCount > 0) {
                $reasons[] = sprintf('Habitude %s (%d fois)', $joursNoms[$currentDayOfWeek], $dayOfWeekCount);
            }
            if ($totalCount >= 3) {
                $reasons[] = sprintf('Un de vos favoris (%d fois)', $totalCount);
            }
            if ($daysSince !== null && $daysSince >= 7) {
                $reasons[] = sprintf('Pas cuisiné depuis %d jours', $daysSince);
            } elseif ($daysSince === null) {
                $reasons[] = 'À tester (jamais cuisiné)';
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRecette = $recette;
                $bestReason = !empty($reasons) ? implode(' • ', array_slice($reasons, 0, 2)) : 'Une excellente idée pour aujourd\'hui !';
            }
        }

        if (!$bestRecette) {
            $bestRecette = $recettes[0];
            $bestReason = 'Une excellente idée pour aujourd\'hui !';
        }

        return [
            'recette' => $bestRecette,
            'reason' => $bestReason,
        ];
    }
}
