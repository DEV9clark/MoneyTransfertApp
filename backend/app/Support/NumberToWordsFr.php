<?php

namespace App\Support;

/**
 * Conversion d'un montant en toutes lettres (français), pour les reçus.
 * Ex : 152500 => "cent cinquante-deux mille cinq cents"
 */
class NumberToWordsFr
{
    private const UNITS = [
        'zéro', 'un', 'deux', 'trois', 'quatre', 'cinq', 'six', 'sept', 'huit', 'neuf',
        'dix', 'onze', 'douze', 'treize', 'quatorze', 'quinze', 'seize',
        'dix-sept', 'dix-huit', 'dix-neuf',
    ];

    private const TENS = [
        2 => 'vingt', 3 => 'trente', 4 => 'quarante', 5 => 'cinquante', 6 => 'soixante',
    ];

    public static function convert(float $amount, string $currency = ''): string
    {
        $integer = (int) floor(round($amount, 2));
        $cents = (int) round(($amount - $integer) * 100);

        $words = self::integerToWords($integer);
        $label = self::currencyLabel($currency, $integer);
        // "un million de francs", "deux milliards d'euros"
        if ($label !== '' && $integer >= 1000000 && $integer % 1000000 === 0) {
            $words .= preg_match('/^[aeiouy]/i', $label) ? " d'" : ' de ';
            $result = trim($words . $label);
        } else {
            $result = trim($words . ' ' . $label);
        }

        if ($cents > 0) {
            $result .= ' et ' . self::integerToWords($cents) . ' centime' . ($cents > 1 ? 's' : '');
        }

        return ucfirst($result);
    }

    private static function currencyLabel(string $currency, int $n): string
    {
        $plural = $n > 1;
        switch (strtoupper($currency)) {
            case 'XOF':
            case 'XAF':
                return $plural ? 'francs CFA' : 'franc CFA';
            case 'EUR':
                return $plural ? 'euros' : 'euro';
            case 'USD':
                return $plural ? 'dollars US' : 'dollar US';
            case 'CAD':
                return $plural ? 'dollars canadiens' : 'dollar canadien';
            default:
                return strtoupper($currency);
        }
    }

    public static function integerToWords(int $n): string
    {
        if ($n < 0) {
            return 'moins ' . self::integerToWords(-$n);
        }
        if ($n === 0) {
            return 'zéro';
        }

        $parts = [];
        $scales = [
            1000000000 => ['milliard', 'milliards'],
            1000000 => ['million', 'millions'],
        ];

        foreach ($scales as $value => [$singular, $plural]) {
            if ($n >= $value) {
                $count = intdiv($n, $value);
                $parts[] = self::below1000($count, false) . ' ' . ($count > 1 ? $plural : $singular);
                $n %= $value;
            }
        }

        if ($n >= 1000) {
            $thousands = intdiv($n, 1000);
            // "mille" est invariable et "un mille" ne se dit pas.
            $parts[] = $thousands === 1 ? 'mille' : self::below1000($thousands, false) . ' mille';
            $n %= 1000;
        }

        if ($n > 0) {
            $parts[] = self::below1000($n, true);
        }

        return implode(' ', $parts);
    }

    /**
     * @param bool $final true si le nombre termine l'expression (accord de "cents" et "quatre-vingts").
     */
    private static function below1000(int $n, bool $final): string
    {
        $hundreds = intdiv($n, 100);
        $rest = $n % 100;
        $words = [];

        if ($hundreds > 0) {
            if ($hundreds === 1) {
                $words[] = 'cent';
            } else {
                $words[] = self::UNITS[$hundreds] . ' cent' . ($rest === 0 && $final ? 's' : '');
            }
        }

        if ($rest > 0) {
            $words[] = self::below100($rest, $final);
        }

        return implode(' ', $words);
    }

    private static function below100(int $n, bool $final): string
    {
        if ($n < 20) {
            return self::UNITS[$n];
        }

        $tens = intdiv($n, 10);
        $unit = $n % 10;

        if ($tens === 7 || $tens === 9) {
            // soixante-dix..., quatre-vingt-dix...
            $base = $tens === 7 ? 'soixante' : 'quatre-vingt';
            $sub = 10 + $unit;
            if ($tens === 7 && $unit === 1) {
                return 'soixante et onze';
            }
            return $base . '-' . self::UNITS[$sub];
        }

        if ($tens === 8) {
            if ($unit === 0) {
                return 'quatre-vingt' . ($final ? 's' : '');
            }
            return 'quatre-vingt-' . self::UNITS[$unit];
        }

        $word = self::TENS[$tens];
        if ($unit === 0) {
            return $word;
        }
        if ($unit === 1) {
            return $word . ' et un';
        }

        return $word . '-' . self::UNITS[$unit];
    }
}
