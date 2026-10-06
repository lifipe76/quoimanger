<?php

namespace App\Service;

use App\Entity\Recette;

class RecipeSuggestionService
{
    /**
     * Calcule le classement des recettes par rapport au nombre de réalisations
     *
     * @param Recette[] $recettes
     * @return array<int, int|null> [recetteId => rank]
     */
    public function calculateRecipeRanks(array $recettes): array
    {
        $counts = [];
        foreach ($recettes as $r) {
            if ($r->getId() !== null) {
                $counts[$r->getId()] = $r->getRealisations()->count();
            }
        }

        arsort($counts);

        $ranks = [];
        $currentRank = 0;
        $prevCount = null;
        $position = 0;

        foreach ($counts as $id => $count) {
            $position++;
            if ($count === 0) {
                $ranks[$id] = null;
                continue;
            }
            if ($count !== $prevCount) {
                $currentRank = $position;
                $prevCount = $count;
            }
            $ranks[$id] = $currentRank;
        }

        return $ranks;
    }

    /**
     * Calcule le score de pertinence / probabilité d'une recette pour une date donnée
     * Critères :
     * 1. Habitude du jour de la semaine (recette souvent cuisinée ce jour-là) (+30 pts / fois)
     * 2. Ancienneté de la dernière fois (pénalité si mangé très récemment, bonus si plus vieux)
     * 3. Rang / Fréquence globale (les recettes préférées ont plus de points)
     *
     * @param Recette $recette
     * @param \DateTimeImmutable $forDate
     * @param array<int, int|null> $ranks
     * @return float
     */
    public function calculateScore(Recette $recette, \DateTimeImmutable $forDate, array $ranks = []): float
    {
        $realisations = $recette->getRealisations();
        $totalCount = $realisations->count();
        $daysSince = $recette->getDaysSinceLastRealisation();
        $currentDayOfWeek = (int) $forDate->format('N');

        // 1. Habitude du jour de la semaine
        $dayOfWeekCount = 0;
        foreach ($realisations as $r) {
            if ($r->getRealiseAt() && (int) $r->getRealiseAt()->format('N') === $currentDayOfWeek) {
                $dayOfWeekCount++;
            }
        }

        $score = 0.0;

        // Critère 1 : Habitude du jour (+30 points par réalisation ce même jour de la semaine)
        $score += $dayOfWeekCount * 30.0;

        // Critère 2 : Ancienneté de la dernière dégustation
        if ($daysSince === 0) {
            // Déjà mangé aujourd'hui
            $score -= 2000.0;
        } elseif ($daysSince === 1) {
            // Mangé hier
            $score -= 1000.0;
        } elseif ($daysSince === 2) {
            $score -= 200.0;
        } elseif ($daysSince === 3) {
            $score -= 50.0;
        } elseif ($daysSince === null) {
            // Jamais cuisiné : bonne opportunité
            $score += 40.0;
        } else {
            // Plus c'est ancien / plus vieux, plus c'est favorisé
            $score += min($daysSince, 90) * 2.0;
        }

        // Critère 3 : Fréquence / Rank
        $id = $recette->getId();
        $rank = ($id !== null && isset($ranks[$id])) ? $ranks[$id] : null;

        if ($rank !== null) {
            // Bonus selon le rank (rang 1 = +50, rang 2 = +45, etc.)
            $rankBonus = max(5, 55 - ($rank * 5));
            $score += $rankBonus;
        } else {
            $score += $totalCount * 5.0;
        }

        return $score;
    }

    /**
     * Trie les recettes par ordre de recommandation / probabilité pour une date donnée
     *
     * @param Recette[] $recettes
     * @param \DateTimeImmutable|null $forDate
     * @param array<int, int|null>|null $ranks
     * @return Recette[]
     */
    public function sortRecettesByProbability(array $recettes, ?\DateTimeImmutable $forDate = null, ?array $ranks = null): array
    {
        if (empty($recettes)) {
            return [];
        }

        $forDate = $forDate ?? new \DateTimeImmutable('today');
        $ranks = $ranks ?? $this->calculateRecipeRanks($recettes);

        $scores = [];
        foreach ($recettes as $r) {
            if ($r->getId() !== null) {
                $scores[$r->getId()] = $this->calculateScore($r, $forDate, $ranks);
            }
        }

        $sorted = $recettes;
        usort($sorted, function (Recette $a, Recette $b) use ($scores) {
            $scoreA = $scores[$a->getId() ?? 0] ?? 0.0;
            $scoreB = $scores[$b->getId() ?? 0] ?? 0.0;

            if ($scoreA === $scoreB) {
                return strcmp($a->getDesignation() ?? '', $b->getDesignation() ?? '');
            }

            return ($scoreB <=> $scoreA);
        });

        return $sorted;
    }

    /**
     * Calcule la meilleure suggestion de recette pour un jour donné
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
        $currentDayOfWeek = (int) $forDate->format('N');

        $joursNoms = [
            1 => 'le lundi',
            2 => 'le mardi',
            3 => 'le mercredi',
            4 => 'le jeudi',
            5 => 'le vendredi',
            6 => 'le samedi',
            7 => 'le dimanche',
        ];

        $ranks = $this->calculateRecipeRanks($recettes);
        $bestRecette = null;
        $bestScore = -PHP_FLOAT_MAX;
        $bestReason = '';

        foreach ($recettes as $recette) {
            $score = $this->calculateScore($recette, $forDate, $ranks);
            $realisations = $recette->getRealisations();
            $totalCount = $realisations->count();
            $daysSince = $recette->getDaysSinceLastRealisation();

            $dayOfWeekCount = 0;
            foreach ($realisations as $r) {
                if ($r->getRealiseAt() && (int) $r->getRealiseAt()->format('N') === $currentDayOfWeek) {
                    $dayOfWeekCount++;
                }
            }

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
