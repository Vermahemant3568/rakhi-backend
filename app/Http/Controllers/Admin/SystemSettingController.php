<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class SystemSettingController extends Controller
{
    public function index()
    {
        return view('admin.settings.index');
    }
    
    public function data()
    {
        $defaults = [
            'trial_price' => '7',
            'trial_days' => '7', 
            'monthly_price' => '299',
            'subscription_grace_days' => '0',
            'autopay_required' => 'true',
            'voice_call_only' => 'true',
            'audio_message_allowed' => 'false'
        ];
        
        $settings = SystemSetting::all()->pluck('value', 'key')->toArray();
        $settings = array_merge($defaults, $settings);
        
        return response()->json($settings);
    }
    
    public function update(Request $request)
    {
        $request->validate([
            'trial_price' => 'required|numeric|min:0',
            'trial_days' => 'required|integer|min:1',
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
        
        return response()->json(['message' => 'Settings updated successfully']);
    }
}