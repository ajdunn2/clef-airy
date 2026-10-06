<?php

namespace App\Models;

use App\Services\StoredPassword;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

#[Fillable(['base_url', 'username', 'password', 'model', 'auth_type', 'yes_threshold'])]
#[Hidden(['password'])]
class SystemOneSetting extends Model
{
    protected function password(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => app(StoredPassword::class)->decrypt($value ?? ''),
            set: fn (?string $value): string => app(StoredPassword::class)->encrypt($value ?? ''),
        );
    }

    public function passwordNeedsReset(): bool
    {
        return filled($this->getRawOriginal('password')) && $this->password === null;
    }

    public static function current(): ?self
    {
        $setting = static::query()->oldest('id')->first();

        if ($setting !== null && config('nativephp-internal.running')) {
            $stored = (string) $setting->getRawOriginal('password');

            if ($stored !== '' && ! str_starts_with($stored, 'native:v1:')) {
                $password = $setting->password;

                if ($password !== null) {
                    try {
                        $setting->password = $password;
                        $setting->save();
                    } catch (ValidationException) {
                        // The keychain is unavailable. Leave the readable legacy value so the page can open.
                    }
                }
            }
        }

        return $setting;
    }
}
