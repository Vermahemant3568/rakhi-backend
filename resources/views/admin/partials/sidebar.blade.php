<div class="bg-dark text-white" style="width: 250px; min-height: 100vh;">
    <div class="p-3">
        <h4><i class="fas fa-cog"></i> Admin Panel</h4>
    </div>
    
    <nav class="nav flex-column">
        <a class="nav-link text-white" href="{{ route('admin.dashboard') }}">
            <i class="fas fa-tachometer-alt"></i> Dashboard
        </a>
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
    </nav>
</div>