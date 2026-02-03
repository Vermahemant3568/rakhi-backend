<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use PragmaRX\Google2FA\Google2FA;

class AdminUser extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'admin_users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
        'two_factor_enabled',
        'two_factor_secret',
        'locked_until',
        'failed_attempts'
    ];

    protected $hidden = [
        'password',
        'two_factor_secret'
    ];

    protected $casts = [
        'two_factor_enabled' => 'boolean',
        'locked_until' => 'datetime'
    ];

    public function isLocked()
    {
        return $this->locked_until && $this->locked_until->isFuture();
    }

    public function incrementFailedAttempts()
    {
        $this->increment('failed_attempts');
        if ($this->failed_attempts >= 5) {
            $this->update(['locked_until' => now()->addMinutes(30)]);
        }
    }

    public function resetFailedAttempts()
    {
        $this->update(['failed_attempts' => 0, 'locked_until' => null]);
    }

    public function generateTwoFactorSecret()
    {
        $this->two_factor_secret = base32_encode(random_bytes(20));
        $this->save();
        return $this->two_factor_secret;
    }

    public function verifyTwoFactorCode($code)
    {
        if (!$this->two_factor_secret) {
            return false;
        }
        
        // Simple TOTP verification (30-second window)
        $timeSlice = floor(time() / 30);
        
        for ($i = -1; $i <= 1; $i++) {
            $calculatedCode = $this->generateTOTP($this->two_factor_secret, $timeSlice + $i);
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function generateTOTP($secret, $timeSlice)
    {
        $key = base32_decode($secret);
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hm = hash_hmac('sha1', $time, $key, true);
        $offset = ord(substr($hm, -1)) & 0x0F;
        $hashpart = substr($hm, $offset, 4);
        $value = unpack('N', $hashpart);
        $value = $value[1];
        $value = $value & 0x7FFFFFFF;
        $modulo = pow(10, 6);
        return str_pad($value % $modulo, 6, '0', STR_PAD_LEFT);
    }
}
