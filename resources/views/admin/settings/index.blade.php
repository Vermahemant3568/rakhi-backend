@extends('admin.layouts.app')

@section('title', 'System Settings')
@section('page-title', 'System Settings')

@section('content')
<div class="container-fluid">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-cog"></i> System Configuration</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Pricing Settings</h6>
                                
                                <div class="mb-3">
                                    <label for="trial_price" class="form-label">Trial Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control @error('trial_price') is-invalid @enderror" 
                                               name="trial_price" value="{{ old('trial_price', $settings['trial_price'] ?? '7') }}" required>
                                    </div>
                                    @error('trial_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="trial_days" class="form-label">Trial Days</label>
                                    <input type="number" class="form-control @error('trial_days') is-invalid @enderror" 
                                           name="trial_days" value="{{ old('trial_days', $settings['trial_days'] ?? '7') }}" required>
                                    @error('trial_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="monthly_price" class="form-label">Monthly Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control @error('monthly_price') is-invalid @enderror" 
                                               name="monthly_price" value="{{ old('monthly_price', $settings['monthly_price'] ?? '299') }}" required>
                                    </div>
                                    @error('monthly_price')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subscription_grace_days" class="form-label">Subscription Grace Days</label>
                                    <input type="number" class="form-control @error('subscription_grace_days') is-invalid @enderror" 
                                           name="subscription_grace_days" value="{{ old('subscription_grace_days', $settings['subscription_grace_days'] ?? '0') }}" required>
                                    @error('subscription_grace_days')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-primary">Feature Settings</h6>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="autopay_required" value="1" 
                                               {{ old('autopay_required', $settings['autopay_required'] ?? 'true') == 'true' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="autopay_required">
                                            Autopay Required
                                        </label>
                                    </div>
                                    <small class="text-muted">Require automatic payment setup for subscriptions</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="voice_call_only" value="1" 
                                               {{ old('voice_call_only', $settings['voice_call_only'] ?? 'true') == 'true' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="voice_call_only">
                                            Voice Call Only
                                        </label>
                                    </div>
                                    <small class="text-muted">Restrict communication to voice calls only</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="audio_message_allowed" value="1" 
                                               {{ old('audio_message_allowed', $settings['audio_message_allowed'] ?? 'false') == 'true' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="audio_message_allowed">
                                            Audio Messages Allowed
                                        </label>
                                    </div>
                                    <small class="text-muted">Allow users to send audio messages</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save Settings
                                </button>
                                <a href="{{ route('admin.dashboard') }}" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection