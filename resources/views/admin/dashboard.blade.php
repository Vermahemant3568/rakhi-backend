@extends('admin.layouts.app')

@section('title', 'Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Cards Row -->
    <div class="row mb-4" id="dashboardCards">
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Total Users</h5>
                    <h3 class="text-primary" id="totalUsers">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Active Today</h5>
                    <h3 class="text-success" id="activeToday">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Active Subscribers</h5>
                    <h3 class="text-warning" id="activeSubscribers">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-2">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Trial Users</h5>
                    <h3 class="text-info" id="trialUsers">-</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h5 class="card-title">Monthly Revenue</h5>
                    <h3 class="text-success" id="monthlyRevenue">-</h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line"></i> Daily Active Users (7 days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="dauChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-smile"></i> Mood Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="moodChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-comments"></i> Chat vs Voice Usage</h5>
                </div>
                <div class="card-body">
                    <canvas id="usageChart" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-shield-alt"></i> Safety & Intent</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 text-center">
                            <h4 class="text-danger" id="safetyTriggers">-</h4>
                            <small>Safety Triggers</small>
                        </div>
                        <div class="col-6">
                            <canvas id="intentChart" height="150"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    loadDashboardData();
    
    function loadDashboardData() {
        fetch('{{ route("admin.analytics.dashboard-data") }}')
        .then(response => response.json())
        .then(data => {
            console.log('Dashboard data:', data); // Debug log
            
            // Update cards
            document.getElementById('totalUsers').textContent = data.cards.total_users || 0;
            document.getElementById('activeToday').textContent = data.cards.active_today || 0;
            document.getElementById('activeSubscribers').textContent = data.cards.active_subscribers || 0;
            document.getElementById('trialUsers').textContent = data.cards.trial_users || 0;
            document.getElementById('monthlyRevenue').textContent = '₹' + (data.cards.monthly_revenue || 0);
            document.getElementById('safetyTriggers').textContent = data.charts.safety_triggers || 0;
            
            // Create charts with fallbacks
            if (data.charts.dau_wau && data.charts.dau_wau.dau && data.charts.dau_wau.dau.length > 0) {
                createDAUChart(data.charts.dau_wau.dau);
            } else {
                showEmptyChart('dauChart', 'No activity data available');
            }
            
            if (data.charts.mood_trend && data.charts.mood_trend.length > 0) {
                createMoodChart(data.charts.mood_trend);
            } else {
                showEmptyChart('moodChart', 'No mood data available');
            }
            
            if (data.charts.chat_voice_usage && data.charts.chat_voice_usage.length > 0) {
                createUsageChart(data.charts.chat_voice_usage);
            } else {
                showEmptyChart('usageChart', 'No usage data available');
            }
            
            if (data.charts.intent_distribution && data.charts.intent_distribution.length > 0) {
                createIntentChart(data.charts.intent_distribution);
            } else {
                showEmptyChart('intentChart', 'No intent data available');
            }
        })
        .catch(error => {
            console.error('Error loading dashboard data:', error);
            // Set default values on error
            document.getElementById('totalUsers').textContent = '0';
            document.getElementById('activeToday').textContent = '0';
            document.getElementById('activeSubscribers').textContent = '0';
            document.getElementById('trialUsers').textContent = '0';
            document.getElementById('monthlyRevenue').textContent = '₹0';
            document.getElementById('safetyTriggers').textContent = '0';
        });
    }
    
    function createDAUChart(dauData) {
        if (!dauData || dauData.length === 0) return;
        
        new Chart(document.getElementById('dauChart'), {
            type: 'line',
            data: {
                labels: dauData.map(d => d.date),
                datasets: [{
                    label: 'Daily Active Users',
                    data: dauData.map(d => d.count),
                    borderColor: '#007bff',
                    fill: false
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
    
    function createMoodChart(moodData) {
        if (!moodData || moodData.length === 0) return;
        
        new Chart(document.getElementById('moodChart'), {
            type: 'doughnut',
            data: {
                labels: moodData.map(m => m.mood || 'Unknown'),
                datasets: [{
                    data: moodData.map(m => m.total),
                    backgroundColor: ['#dc3545', '#6c757d', '#28a745', '#ffc107']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
    
    function createUsageChart(usageData) {
        if (!usageData || usageData.length === 0) return;
        
        new Chart(document.getElementById('usageChart'), {
            type: 'bar',
            data: {
                labels: usageData.map(u => u.event),
                datasets: [{
                    data: usageData.map(u => u.total),
                    backgroundColor: ['#007bff', '#28a745']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
    
    function createIntentChart(intentData) {
        if (!intentData || intentData.length === 0) return;
        
        new Chart(document.getElementById('intentChart'), {
            type: 'pie',
            data: {
                labels: intentData.map(i => i.intent),
                datasets: [{
                    data: intentData.map(i => i.total),
                    backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6c757d']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false }
        });
    }
    
    function showEmptyChart(canvasId, message) {
        const canvas = document.getElementById(canvasId);
        const ctx = canvas.getContext('2d');
        
        // Clear canvas
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        
        // Set text properties
        ctx.fillStyle = '#6c757d';
        ctx.font = '14px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        
        // Draw message
        ctx.fillText(message, canvas.width / 2, canvas.height / 2);
    }
});
</script>
@endsection