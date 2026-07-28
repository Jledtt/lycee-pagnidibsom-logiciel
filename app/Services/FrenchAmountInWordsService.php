<?php

namespace App\Services;

use InvalidArgumentException;

class FrenchAmountInWordsService
{
    private const GROUPS = [
        4 => ['billion', 'billions'],
        3 => ['milliard', 'milliards'],
        2 => ['million', 'millions'],
        1 => ['mille', 'mille'],
    ];

    public function convert(int|float|string $amount, string $currency = 'FCFA'): string
    {
        if (! is_numeric($amount)) {
            throw new InvalidArgumentException('Le montant doit etre numerique.');
        }

        $roundedAmount = (int) round((float) $amount);

        if ($roundedAmount < 0) {
            throw new InvalidArgumentException('Le montant doit etre positif ou nul.');
        }

        $currencyWords = $currency === 'FCFA'
            ? ($roundedAmount === 1 ? 'franc CFA' : 'francs CFA')
            : $currency;
        $currencyConnector = $currency === 'FCFA'
            && $roundedAmount >= 1_000_000
            && $roundedAmount % 1_000_000 === 0
                ? ' de '
                : ' ';

        return ucfirst($this->spell($roundedAmount)).$currencyConnector.$currencyWords;
    }

    public function spell(int $number): string
    {
        if ($number < 0) {
            throw new InvalidArgumentException('Le nombre doit etre positif ou nul.');
        }

        if ($number === 0) {
            return 'zéro';
        }

        if ($number < 1000) {
            return $this->spellUnderThousand($number);
        }

        $parts = [];
        $remainder = $number;

        foreach (self::GROUPS as $power => [$singular, $plural]) {
            $divisor = 1000 ** $power;
            $group = intdiv($remainder, $divisor);

            if ($group === 0) {
                continue;
            }

            if ($power === 1) {
                $parts[] = $group === 1 ? 'mille' : $this->beforeMille($this->spell($group)).' mille';
            } else {
                $parts[] = ($group === 1 ? 'un' : $this->spell($group)).' '.($group > 1 ? $plural : $singular);
            }

            $remainder %= $divisor;
        }

        if ($remainder > 0) {
            $parts[] = $this->spellUnderThousand($remainder);
        }

        return implode(' ', $parts);
    }

    private function beforeMille(string $words): string
    {
        if (str_ends_with($words, 'quatre-vingts')) {
            return substr($words, 0, -1);
        }

        if (str_ends_with($words, 'cents')) {
            return substr($words, 0, -1);
        }

        return $words;
    }

    private function spellUnderThousand(int $number): string
    {
        if ($number < 100) {
            return $this->spellUnderHundred($number);
        }

        $hundreds = intdiv($number, 100);
        $remainder = $number % 100;
        $prefix = $hundreds === 1 ? 'cent' : $this->spellUnderHundred($hundreds).' cent';

        if ($remainder === 0) {
            return $hundreds > 1 ? $prefix.'s' : $prefix;
        }

        return $prefix.' '.$this->spellUnderHundred($remainder);
    }

    private function spellUnderHundred(int $number): string
    {
        $units = [
            0 => 'zéro',
            1 => 'un',
            2 => 'deux',
            3 => 'trois',
            4 => 'quatre',
            5 => 'cinq',
            6 => 'six',
            7 => 'sept',
            8 => 'huit',
            9 => 'neuf',
            10 => 'dix',
            11 => 'onze',
            12 => 'douze',
            13 => 'treize',
            14 => 'quatorze',
            15 => 'quinze',
            16 => 'seize',
        ];

        if (isset($units[$number])) {
            return $units[$number];
        }

        if ($number < 20) {
            return 'dix-'.$units[$number - 10];
        }

        if ($number < 70) {
            $tens = [
                2 => 'vingt',
                3 => 'trente',
                4 => 'quarante',
                5 => 'cinquante',
                6 => 'soixante',
            ];
            $ten = intdiv($number, 10);
            $unit = $number % 10;

            if ($unit === 0) {
                return $tens[$ten];
            }

            return $tens[$ten].($unit === 1 ? ' et ' : '-').$units[$unit];
        }

        if ($number < 80) {
            $remainder = $number - 60;

            return 'soixante'.($remainder === 11 ? ' et ' : '-').$this->spellUnderHundred($remainder);
        }

        $remainder = $number - 80;

        if ($remainder === 0) {
            return 'quatre-vingts';
        }

        return 'quatre-vingt-'.$this->spellUnderHundred($remainder);
    }
}
