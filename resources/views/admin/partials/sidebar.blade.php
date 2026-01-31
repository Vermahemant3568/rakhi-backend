<div class="bg-dark text-white" style="width: 250px; min-height: 100vh;">
    <div class="p-3">
        <h4><i class="fas fa-cog"></i> Admin Panel</h4>
    </div>
    
    <nav class="nav flex-column">
        <a class="nav-link text-white" href="{{ route('admin.dashboard') }}">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
        
        <!-- AI Control Panel -->
        <div class="nav-section mt-3">
            <h6 class="text-muted px-3 mb-2">AI CONTROL PANEL</h6>
            <a class="nav-link text-white" href="{{ route('admin.rakhi-rules.index') }}">
                <i class="fas fa-brain"></i> Rakhi Rules
            </a>
            <a class="nav-link text-white" href="{{ route('admin.prompt-templates.index') }}">
                <i class="fas fa-file-alt"></i> Prompt Templates
            </a>
            <a class="nav-link text-white" href="{{ route('admin.ai-models.index') }}">
                <i class="fas fa-robot"></i> AI Models
            </a>
            <a class="nav-link text-white" href="{{ route('admin.memory-policies.index') }}">
                <i class="fas fa-memory"></i> Memory Policies
            </a>
        </div>
        
        <!-- System Management -->
        <div class="nav-section mt-3">
            <h6 class="text-muted px-3 mb-2">SYSTEM</h6>
            <a class="nav-link text-white" href="{{ route('admin.languages.index') }}">
                <i class="fas fa-language"></i> Language Manager
            </a>
            <a class="nav-link text-white" href="{{ route('admin.goals.index') }}">
                <i class="fas fa-bullseye"></i> Goal Manager
            </a>
            <a class="nav-link text-white" href="#">
                <i class="fas fa-users"></i> Users
            </a>
            <a class="nav-link text-white" href="{{ route('admin.settings.index') }}">
                <i class="fas fa-cog"></i> Settings
            </a>
        </div>
        
        <!-- Financial -->
        <div class="nav-section mt-3">
            <h6 class="text-muted px-3 mb-2">FINANCIAL</h6>
            <a class="nav-link text-white" href="{{ route('admin.payments.index') }}">
                <i class="fas fa-credit-card"></i> Payments
            </a>
            <a class="nav-link text-white" href="{{ route('admin.revenue.summary') }}">
                <i class="fas fa-chart-bar"></i> Revenue
            </a>
        </div>
    </nav>
</div>