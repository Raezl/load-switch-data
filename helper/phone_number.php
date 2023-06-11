<?php

class PhoneNumber
{
    private static $customer_prefix = array(
        "#7750",
        "#7751",
        "#7752",
        "#7753",
        "#7754",
        "#7755",
        "#7756",
        "#7757",
        "#7758",
        "#7759",
        "88001",
        "88003",
        "88004",
        "#7000",
        "7800#",
        "10055",
        "50000",
        "50001",
        "33501",
        "33502",
        "5002",
        "#7850",
        "#7950",
        "#7951",
        "#7952",
        "#7953",
        "#7954",
        "#7955",
        "#7956",
        "#7100",
        "#7957",
        "#7958"
    );

    public static function normalise($number): string
    {
        //Pattern checks if number starts with 1800 or 1300 or of length 6
        $country_code_missing_pattern = '/(^1800)|(^1300)|(^.{6}$)/';

        //add country code to 1300, 1800, numbers with length 6
        if (preg_match($country_code_missing_pattern, $number)) {
            return "61{$number}";

        }
        //add 617 to numbers of length 8
        else if (strlen($number) == 8) {
            return "617{$number}";
        }
        //replace first 0 and add country code to number
        else if (str_starts_with($number, '0011') || str_starts_with($number, '0')) {
            return substr_replace($number, '61', 0, 1);
        }
        //remove customer prefix
        else if (in_array(substr($number, 0, 5), PhoneNumber::$customer_prefix)) {
            return substr($number, 5);

        }

        return $number;
    }


    public static function get_iso2($number, $country_codes)
    {
        foreach ($country_codes as $code) {
            if (str_starts_with($number, $code['phone_code'])) {
                return $code['iso2'];
            }
        }
        return 'none';
    }
}