<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AdminLoginAttempt;
use App\Models\AdminAuditLog as AdminAuditLogModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $admin = AdminUser::where('email', $request->email)->first();

        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 401);
        }

        if (!$admin->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Admin account disabled'
            ], 403);
        }

        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'data' => [
                'admin' => [
                    'id' => $admin->id,
                    'name' => $admin->name,
                    'email' => $admin->email
                ],
                'token' => $token
            ]
        ]);
    }
    
    public function webLogin(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'two_factor_code' => 'nullable|string|size:6'
        ]);

        $key = 'admin-login:' . $request->ip();
        
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds."
            ]);
        }

        $admin = AdminUser::where('email', $request->email)->first();
        
        // Log attempt
        AdminLoginAttempt::create([
            'email' => $request->email,
            'ip_address' => $request->ip(),
            'attempted_at' => now(),
            'successful' => false
        ]);
        
        if (!$admin) {
            RateLimiter::hit($key, 300);
            return back()->withErrors(['email' => 'Admin not found'])->withInput();
        }
        
        if ($admin->isLocked()) {
            return back()->withErrors(['email' => 'Account temporarily locked due to failed attempts'])->withInput();
        }
        
        if (!Hash::check($request->password, $admin->password)) {
            RateLimiter::hit($key, 300);
            $admin->incrementFailedAttempts();
            return back()->withErrors(['email' => 'Invalid password'])->withInput();
        }

        if (!$admin->is_active) {
            return back()->withErrors(['email' => 'Admin account disabled'])->withInput();
        }

        // Check 2FA if enabled
        if ($admin->two_factor_enabled) {
            if (!$request->two_factor_code) {
                return back()->withErrors(['two_factor_code' => '2FA code required'])->withInput();
            }
            
            if (!$admin->verifyTwoFactorCode($request->two_factor_code)) {
                RateLimiter::hit($key, 300);
                return back()->withErrors(['two_factor_code' => 'Invalid 2FA code'])->withInput();
            }
        }

        // Successful login
        RateLimiter::clear($key);
        $admin->resetFailedAttempts();
        
        // Update login attempt
        AdminLoginAttempt::where('email', $request->email)
            ->where('ip_address', $request->ip())
            ->latest()
            ->first()
            ->update(['successful' => true]);
        
        // Regenerate session
        $request->session()->regenerate();
        session(['admin_id' => $admin->id, 'admin_email' => $admin->email]);
        
        // Log successful login
        AdminAuditLogModel::create([
            'admin_id' => $admin->id,
            'action' => 'LOGIN',
            'resource' => 'admin.login',
            'details' => ['method' => 'web'],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
        
        return redirect()->route('admin.dashboard');
    }
    
    public function logout(Request $request)
    {
        if (session('admin_id')) {
            AdminAuditLogModel::create([
                'admin_id' => session('admin_id'),
                'action' => 'LOGOUT',
                'resource' => 'admin.logout',
                'details' => [],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
        }
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect()->route('admin.login');
    }
    
    public function securitySettings()
    {
        $admin = AdminUser::find(session('admin_id'));
        return view('admin.settings.security', compact('admin'));
    }
    
    public function disable2FA(Request $request)
    {
        $admin = AdminUser::find(session('admin_id'));
        $admin->update([
            'two_factor_enabled' => false,
            'two_factor_secret' => null
        ]);
        
        AdminAuditLogModel::create([
            'admin_id' => $admin->id,
            'action' => 'DISABLE_2FA',
            'resource' => 'admin.security',
            'details' => [],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
        
        return response()->json(['success' => true]);
    }
    
    public function enable2FA(Request $request)
    {
        try {
            $admin = AdminUser::find(session('admin_id'));
            
            // Generate a simple secret without Google2FA package
            $secret = base32_encode(random_bytes(20));
            $admin->two_factor_secret = $secret;
            $admin->save();
            
            // Create QR code URL using qr-server.com (more reliable)
            $qrCodeUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode(
                'otpauth://totp/' . urlencode(config('app.name') . ':' . $admin->email) . 
                '?secret=' . $secret . 
                '&issuer=' . urlencode(config('app.name'))
            );
            
            return response()->json([
                'secret' => $secret,
                'qr_code_url' => $qrCodeUrl
            ]);
        } catch (\Exception $e) {
            \Log::error('2FA Enable Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }
    
    public function confirm2FA(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6'
        ]);
        
        $admin = AdminUser::find(session('admin_id'));
        
        if ($admin->verifyTwoFactorCode($request->code)) {
            $admin->update(['two_factor_enabled' => true]);
            
            AdminAuditLogModel::create([
                'admin_id' => $admin->id,
                'action' => 'ENABLE_2FA',
                'resource' => 'admin.security',
                'details' => [],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);
            
            return response()->json(['success' => true]);
        }
        
        return response()->json(['success' => false, 'message' => 'Invalid code'], 400);
    }
}
