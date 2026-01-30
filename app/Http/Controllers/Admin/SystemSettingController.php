<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function index()
    {
        $settings = SystemSetting::all()->pluck('value', 'key');
        return view('admin.settings.index', compact('settings'));
    }
    
    public function update(Request $request)
    {
        $request->validate([
            'trial_price' => 'required|numeric|min:0',
            'trial_days' => 'required|integer|min:0',
            'monthly_price' => 'required|numeric|min:0',
            'subscription_grace_days' => 'required|integer|min:0',
            'autopay_required' => 'required|boolean',
            'voice_call_only' => 'required|boolean',
            'audio_message_allowed' => 'required|boolean'
        ]);
        
        foreach ($request->only([
            'trial_price', 'trial_days', 'monthly_price', 'subscription_grace_days',
            'autopay_required', 'voice_call_only', 'audio_message_allowed'
        ]) as $key => $value) {
            SystemSetting::set($key, $value);
        }
        
        return redirect()->route('admin.settings.index')->with('success', 'Settings updated successfully');
    }
}
