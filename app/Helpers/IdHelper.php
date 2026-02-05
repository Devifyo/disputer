<?php

use Hashids\Hashids;

if (!function_exists('encrypt_id')) {
    /**
     * Encrypt an Integer ID to a short string.
     * Example: 15 -> 'X9d2M'
     */
    function encrypt_id($id)
    {
        // Salt: Uses your APP_KEY so it's unique to your app
        // Min Length: 10 characters (so it looks complex)
        $hashids = new Hashids(config('app.key'), 10); 
        return $hashids->encode($id);
    }
}

if (!function_exists('decrypt_id')) {
    /**
     * Decrypt a short string back to Integer ID.
     * Example: 'X9d2M' -> 15
     */
    function decrypt_id($hash)
    {
        $hashids = new Hashids(config('app.key'), 10);
        $decoded = $hashids->decode($hash);

        // Return the ID if valid, or null if someone messed with the hash
        return !empty($decoded) ? $decoded[0] : null;
    }
}