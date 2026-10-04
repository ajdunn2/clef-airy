<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;
use Native\Desktop\Facades\System;

class StoredPassword
{
    public function encrypt(string $password): string
    {
        if ($password === '') {
            return '';
        }

        if (! config('nativephp-internal.running')) {
            return Crypt::encryptString($password);
        }

        $encrypted = System::canEncrypt() ? System::encrypt($password) : null;

        if (! is_string($encrypted) || $encrypted === '') {
            throw ValidationException::withMessages([
                'password' => 'Secure storage is unavailable. Unlock your system keychain and try again.',
            ]);
        }

        return 'native:v1:'.$encrypted;
    }

    public function decrypt(string $value): ?string
    {
        if ($value === '') {
            return '';
        }

        if (str_starts_with($value, 'native:v1:')) {
            return config('nativephp-internal.running') ? System::decrypt(substr($value, 10)) : null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (DecryptException) {
            return null;
        }
    }
}
