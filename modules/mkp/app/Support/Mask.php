<?php

namespace App\Support;

class Mask
{
    /** "budi.santoso@mail.com" -> "bu*********@mail.com" */
    public static function email(string $email): string
    {
        if (! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = min(2, strlen($local));

        return substr($local, 0, $visible).str_repeat('*', max(strlen($local) - $visible, 3)).'@'.$domain;
    }
}
