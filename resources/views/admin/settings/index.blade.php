@extends('admin.layouts.app')

@section('title', 'Subscription Settings')
@section('page-title', 'Subscription Settings')

@section('content')
<div class="container-fluid">
    <div id="alert-container"></div>
    
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-credit-card"></i> Rakhi Subscription Plans</h5>
                </div>
                <div class="card-body">
                    <form id="subscriptionForm">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Pricing Settings</h6>
                                
                                <div class="mb-3">
                                    <label for="trial_price" class="form-label">Trial Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" name="trial_price" id="trial_price" value="8" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="trial_days" class="form-label">Trial Days</label>
                                    <input type="number" class="form-control" name="trial_days" id="trial_days" value="7" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="monthly_price" class="form-label">Monthly Price</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₹</span>
                                        <input type="number" class="form-control" name="monthly_price" id="monthly_price" value="299" required>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="subscription_grace_days" class="form-label">Subscription Grace Days</label>
                                    <input type="number" class="form-control" name="subscription_grace_days" id="subscription_grace_days" value="3" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h6 class="text-primary">Feature Settings</h6>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="autopay_required" id="autopay_required" checked>
                                        <label class="form-check-label" for="autopay_required">
                                            Autopay Required
                                        </label>
                                    </div>
                                    <small class="text-muted">Require automatic payment setup for subscriptions</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="voice_call_only" id="voice_call_only" checked>
                                        <label class="form-check-label" for="voice_call_only">
                                            Voice Call Only
                                        </label>
                                    </div>
                                    <small class="text-muted">Restrict communication to voice calls only</small>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="audio_message_allowed" id="audio_message_allowed">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadSettings();
    
    document.getElementById('subscriptionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData();
        formData.append('trial_price', document.getElementById('trial_price').value);
        formData.append('trial_days', document.getElementById('trial_days').value);
        formData.append('monthly_price', document.getElementById('monthly_price').value);
        formData.append('subscription_grace_days', document.getElementById('subscription_grace_days').value);
        formData.append('autopay_required', document.getElementById('autopay_required').checked ? 1 : 0);
        formData.append('voice_call_only', document.getElementById('voice_call_only').checked ? 1 : 0);
        formData.append('audio_message_allowed', document.getElementById('audio_message_allowed').checked ? 1 : 0);
        formData.append('_token', document.querySelector('input[name="_token"]').value);
        
        fetch('{{ route("admin.settings.update") }}', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            showAlert('Settings updated successfully!', 'success');
        })
        .catch(error => {
            showAlert('Error updating settings', 'danger');
        });
    });
    
    function loadSettings() {
        fetch('{{ route("admin.settings.data") }}')
        .then(response => response.json())
        .then(data => {
            console.log('Settings loaded:', data);
            document.getElementById('trial_price').value = data.trial_price || '8';
            document.getElementById('trial_days').value = data.trial_days || '7';
            document.getElementById('monthly_price').value = data.monthly_price || '299';
            document.getElementById('subscription_grace_days').value = data.subscription_grace_days || '3';
            
            document.getElementById('autopay_required').checked = data.autopay_required === 'true' || data.autopay_required === '1' || data.autopay_required === 1;
            document.getElementById('voice_call_only').checked = data.voice_call_only === 'true' || data.voice_call_only === '1' || data.voice_call_only === 1;
            document.getElementById('audio_message_allowed').checked = data.audio_message_allowed === 'true' || data.audio_message_allowed === '1' || data.audio_message_allowed === 1;
        })
        .catch(error => {
            console.error('Failed to load settings:', error);
        });
    }
    
    function showAlert(message, type) {
        const alert = `<div class="alert alert-${type} alert-dismissible fade show" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
        document.getElementById('alert-container').innerHTML = alert;
        setTimeout(() => {
            const alertEl = document.querySelector('.alert');
            if (alertEl) alertEl.remove();
        }, 3000);
    }
});
</script>
@endsection