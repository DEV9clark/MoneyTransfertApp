<?php

namespace App\Support;

use App\Models\Country;

/**
 * Normalisation des numéros : on stocke le numéro NATIONAL en chiffres
 * (ex. "771112233"), l'indicatif vient du pays (ex. 221).
 */
class Phone
{
    public static function normalize(string $phone, ?Country $country = null): string
    {
        $digits = preg_replace('/\D/', '', $phone);
        $hadPrefix = preg_match('/^\s*(\+|00)/', $phone) === 1;

        if (strpos($digits, '00') === 0 && $hadPrefix) {
            $digits = substr($digits, 2);
        }

        $code = $country && $country->phone_code ? preg_replace('/\D/', '', $country->phone_code) : '';
        if ($code !== '' && strpos($digits, $code) === 0 && ($hadPrefix || strlen($digits) >= strlen($code) + 8)) {
            $digits = substr($digits, strlen($code));
        }

        return $digits;
    }

    public static function isPlausible(string $nationalDigits): bool
    {
        return (bool) preg_match('/^\d{7,12}$/', $nationalDigits);
    }
}
