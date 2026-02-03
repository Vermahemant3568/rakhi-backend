@extends('admin.layouts.app')

@section('title', 'Security Settings')

@section('content')
<div class="card">
    <div class="card-header">
        <h5 class="mb-0">Two-Factor Authentication (2FA)</h5>
    </div>
    <div class="card-body">
        @if($admin->two_factor_enabled)
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Two-Factor Authentication is <strong>ENABLED</strong>
            </div>
            <p>Your account is protected with 2FA. You'll need to enter a code from your authenticator app when logging in.</p>
            <button class="btn btn-danger" onclick="disable2FA()">
                <i class="fas fa-times"></i> Disable 2FA
            </button>
        @else
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Two-Factor Authentication is <strong>DISABLED</strong>
            </div>
            <p>Enable 2FA to add an extra layer of security to your admin account.</p>
            <button class="btn btn-primary" onclick="enable2FA()">
                <i class="fas fa-shield-alt"></i> Enable 2FA
            </button>
        @endif

        <div id="qr-section" class="mt-4" style="display: none;">
            <h6>Setup 2FA</h6>
            <ol>
                <li>Install Google Authenticator or similar app on your phone</li>
                <li>Scan this QR code or enter the secret key manually:</li>
            </ol>
            
            <div class="text-center my-3">
                <img id="qr-image" src="" alt="QR Code" class="img-fluid" style="max-width: 200px;" onerror="this.style.display='none'; document.getElementById('qr-fallback').style.display='block';">
                <div id="qr-fallback" style="display: none;">
                    <p class="text-muted">QR Code not available. Please enter the secret key manually in your authenticator app.</p>
                </div>
            </div>
            
            <div class="alert alert-info">
                <strong>Secret Key:</strong> <code id="secret-key"></code>
            </div>
            
            <div class="form-group mb-3">
                <label for="verify-code">Enter 6-digit code from your app:</label>
                <input type="text" id="verify-code" class="form-control" style="width: 200px;" maxlength="6" placeholder="123456">
            </div>
            <button class="btn btn-success" onclick="confirm2FA()">
                <i class="fas fa-check"></i> Verify & Enable
            </button>
            <button class="btn btn-secondary" onclick="cancel2FA()">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>
</div>

<div id="message"></div>
@endsection

@push('scripts')
<script>
function showMessage(text, type = 'success') {
    const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
    const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
    document.getElementById('message').innerHTML = `
        <div class="alert ${alertClass} mt-3">
            <i class="fas ${icon}"></i> ${text}
        </div>
    `;
    setTimeout(() => document.getElementById('message').innerHTML = '', 5000);
}

function enable2FA() {
    fetch('{{ route("admin.2fa.enable") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success === false) {
            showMessage(data.message, 'error');
        } else {
            document.getElementById('secret-key').textContent = data.secret;
            document.getElementById('qr-image').src = data.qr_code_url;
            document.getElementById('qr-section').style.display = 'block';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error enabling 2FA', 'error');
    });
}

function confirm2FA() {
    const code = document.getElementById('verify-code').value;
    if (!code || code.length !== 6) {
        showMessage('Please enter a 6-digit code', 'error');
        return;
    }

    fetch('{{ route("admin.2fa.confirm") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ code: code })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('2FA enabled successfully!');
            setTimeout(() => location.reload(), 2000);
        } else {
            showMessage(data.message || 'Invalid code', 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error confirming 2FA', 'error');
    });
}

function disable2FA() {
    if (!confirm('Are you sure you want to disable 2FA? This will make your account less secure.')) {
        return;
    }

    fetch('{{ route("admin.2fa.disable") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showMessage('2FA disabled successfully');
            setTimeout(() => location.reload(), 2000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showMessage('Error disabling 2FA', 'error');
    });
}

function cancel2FA() {
    document.getElementById('qr-section').style.display = 'none';
    document.getElementById('verify-code').value = '';
}
</script>
@endpush