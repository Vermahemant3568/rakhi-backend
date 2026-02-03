@extends('admin.layouts.app')

@section('title', 'Users Management')
@section('page-title', 'Users Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-users"></i> Users</h5>
                    <div>
                        <input type="text" id="searchInput" class="form-control d-inline-block" style="width: 250px;" placeholder="Search users...">
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped" id="usersTable">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Phone</th>
                                    <th>Gender</th>
                                    <th>Age</th>
                                    <th>Goals</th>
                                    <th>Status</th>
                                    <th>Onboarded</th>
                                    <th>Subscription</th>
                                    <th>Joined</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Users will be loaded via AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Activity Logs Modal -->
<div class="modal fade" id="activityLogsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-history"></i> User Activity Logs</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="activityLogsContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadUsers();
    
    document.getElementById('searchInput').addEventListener('input', function() {
        loadUsers(this.value);
    });
    
    function loadUsers(search = '') {
        const tbody = document.querySelector('#usersTable tbody');
        tbody.innerHTML = '<tr><td colspan="10" class="text-center">Loading...</td></tr>';
        
        fetch(`{{ route('admin.users.data') }}?search=${encodeURIComponent(search)}`)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" class="text-center">No users found</td></tr>';
                return;
            }
            
            data.forEach(user => {
                const goalsDisplay = user.goals && user.goals.length > 0 
                    ? user.goals.map(goal => `<span class="badge bg-info me-1" title="Goal ID: ${goal.id}">${goal.title}</span>`).join('')
                    : '<span class="text-muted">No goals</span>';
                
                const row = `
                    <tr>
                        <td><strong>${user.name}</strong></td>
                        <td><span class="text-muted">${user.phone}</span></td>
                        <td><span class="badge bg-${getGenderColor(user.gender)}">${capitalizeFirst(user.gender)}</span></td>
                        <td>${user.age !== null ? Math.abs(Math.floor(user.age)) + ' years' : 'N/A'}</td>
                        <td>${goalsDisplay}</td>
                        <td>
                            <span class="badge bg-${user.is_active ? 'success' : 'danger'}">
                                <i class="fas fa-${user.is_active ? 'check' : 'times'}"></i> ${user.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-${user.is_onboarded ? 'success' : 'secondary'}">
                                <i class="fas fa-${user.is_onboarded ? 'user-check' : 'user-clock'}"></i> ${user.is_onboarded ? 'Complete' : 'Pending'}
                            </span>
                        </td>
                        <td>
                            <span class="badge bg-${getSubscriptionColor(user.subscription_status)}" title="${user.subscription_period}">
                                <i class="fas fa-${getSubscriptionIcon(user.subscription_status)}"></i> ${capitalizeFirst(user.subscription_status)}
                            </span>
                        </td>
                        <td><small class="text-muted">${formatDate(user.created_at)}</small></td>
                        <td>
                            <div class="btn-group" role="group">
                                <button class="btn btn-sm btn-outline-info" onclick="viewUser(${user.id})" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="viewActivityLogs(${user.id})" title="Activity Logs">
                                    <i class="fas fa-history"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-${user.is_active ? 'warning' : 'success'}" onclick="toggleUser(${user.id})" title="${user.is_active ? 'Deactivate' : 'Activate'} User">
                                    <i class="fas fa-${user.is_active ? 'ban' : 'check'}"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
                tbody.innerHTML += row;
            });
        })
        .catch(error => {
            tbody.innerHTML = '<tr><td colspan="10" class="text-center text-danger">Error loading users</td></tr>';
        });
    }
    
    function capitalizeFirst(str) {
        if (!str || str === 'N/A') return str;
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
    
    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString('en-US', { 
            year: 'numeric', 
            month: 'short', 
            day: 'numeric' 
        });
    }
    
    function getGenderColor(gender) {
        switch(gender) {
            case 'male': return 'primary';
            case 'female': return 'danger';
            case 'other': return 'secondary';
            default: return 'light';
        }
    }
    
    function getSubscriptionIcon(status) {
        switch(status) {
            case 'active': return 'crown';
            case 'trial': return 'clock';
            case 'expired': return 'exclamation-triangle';
            case 'cancelled': return 'times-circle';
            default: return 'user';
        }
    }
    
    function getSubscriptionColor(status) {
        switch(status) {
            case 'active': return 'success';
            case 'trial': return 'info';
            case 'expired': return 'warning';
            default: return 'secondary';
        }
    }
    
    window.viewUser = function(id) {
        // Implement user details modal or redirect
        alert('View user details for ID: ' + id);
    };
    
    window.viewActivityLogs = function(id) {
        const modal = new bootstrap.Modal(document.getElementById('activityLogsModal'));
        const content = document.getElementById('activityLogsContent');
        
        content.innerHTML = `
            <div class="text-center">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
            </div>
        `;
        
        modal.show();
        
        fetch(`/admin/users/${id}/activity-logs`)
        .then(response => response.json())
        .then(logs => {
            if (logs.length === 0) {
                content.innerHTML = '<div class="text-center text-muted">No activity logs found</div>';
                return;
            }
            
            let html = '<div class="list-group">';
            logs.forEach(log => {
                const eventBadge = getEventBadge(log.event);
                const metaInfo = log.meta ? JSON.stringify(log.meta) : '';
                
                html += `
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <span class="badge ${eventBadge.class} me-2">
                                    <i class="fas ${eventBadge.icon}"></i> ${log.event}
                                </span>
                                ${metaInfo ? `<small class="text-muted d-block mt-1">${metaInfo}</small>` : ''}
                            </div>
                            <small class="text-muted">${log.created_at}</small>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div class="text-center text-danger">Error loading activity logs</div>';
        });
    };
    
    function getEventBadge(event) {
        switch(event) {
            case 'login': return { class: 'bg-success', icon: 'fa-sign-in-alt' };
            case 'chat_message': return { class: 'bg-primary', icon: 'fa-comment' };
            case 'voice_call': return { class: 'bg-info', icon: 'fa-phone' };
            case 'onboarding_complete': return { class: 'bg-warning', icon: 'fa-user-check' };
            case 'payment_success': return { class: 'bg-success', icon: 'fa-credit-card' };
            default: return { class: 'bg-secondary', icon: 'fa-circle' };
        }
    };
    
    window.toggleUser = function(id) {
        if (confirm('Are you sure you want to toggle this user status?')) {
            fetch(`/admin/users/${id}/toggle`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadUsers();
                } else {
                    alert('Error updating user status');
                }
            });
        }
    };
});
</script>
@endsection