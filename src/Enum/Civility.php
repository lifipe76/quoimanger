<?php

namespace App\Enum;

enum Civility: int
{
    case UNDEFINED = 0;
    case MONSIEUR = 1;
    case MADAME = 2;
    case MADEMOISELLE = 3;

    public function label(): string
    {
        return match ($this) {
            self::MONSIEUR => 'civility.monsieur',
            self::MADAME => 'civility.madame',
            self::MADEMOISELLE => 'civility.mademoiselle',
            default => '',
        };
    }

    public function short(): string
    {
        return match ($this) {
            self::MONSIEUR => 'civility.short.monsieur',
            self::MADAME => 'civility.short.madame',
            self::MADEMOISELLE => 'civility.short.mademoiselle',
            default => '',
        };
    }

    public static function fromString(?string $value): ?self
    {
        return match (strtolower($value)) {
            'm' => self::MONSIEUR,
            'mme' => self::MADAME,
            'mlle' => self::MADEMOISELLE,
            default => null,
        };
    }
}
