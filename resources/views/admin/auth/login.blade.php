<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Admin Login - {{ config('app.name') }}</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; margin: 0; padding: 50px; }
        .login-container { max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
        button { width: 100%; padding: 12px; background: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        button:hover { background: #005a87; }
        .error { color: #d32f2f; font-size: 14px; margin-top: 5px; }
        .title { text-align: center; margin-bottom: 30px; color: #333; }
    </style>
</head>
<body>
    <div class="login-container">
        <h2 class="title">Admin Login</h2>
        
        <form method="POST" action="{{ route('admin.login.post') }}">
            @csrf
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required>
                @error('email')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                @error('password')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            
            <div class="form-group">
                <label for="two_factor_code">2FA Code (if enabled)</label>
                <input type="text" id="two_factor_code" name="two_factor_code" maxlength="6" placeholder="123456">
                @error('two_factor_code')
                    <div class="error">{{ $message }}</div>
                @enderror
            </div>
            
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>