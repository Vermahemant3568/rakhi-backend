<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

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
            'password' => 'required'
        ]);

        $credentials = $request->only('email', 'password');
        
        // Try to find admin user
        $admin = AdminUser::where('email', $request->email)->first();
        
        if (!$admin) {
            return back()->withErrors(['email' => 'Admin not found'])->withInput();
        }
        
        if (!Hash::check($request->password, $admin->password)) {
            return back()->withErrors(['email' => 'Invalid password'])->withInput();
        }

        if (!$admin->is_active) {
            return back()->withErrors(['email' => 'Admin account disabled'])->withInput();
        }

        // Log in the admin using session
        session(['admin_id' => $admin->id, 'admin_email' => $admin->email]);
        
        return redirect()->route('admin.dashboard');
    }
}
