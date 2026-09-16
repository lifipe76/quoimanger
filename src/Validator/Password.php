<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class Password
{
    public static function constraints(): array
    {
        return [
            new NotBlank(message: 'password.notBlank'),
            new Length(
                min: 8,
                max: 32,
                minMessage: 'password.size.min',
                maxMessage: 'password.size.max'
            ),
            // Exige au moins un chiffre
            new Regex(
                pattern: '/\d/',
                message: 'password.required.digit' // Clé de traduction ou message en clair
            ),
            // Exige au moins une lettre (minuscule ou majuscule)
            new Regex(
                pattern: '/[a-zA-Z]/',
                message: 'password.required.letter'
            ),
        ];
    }
}
