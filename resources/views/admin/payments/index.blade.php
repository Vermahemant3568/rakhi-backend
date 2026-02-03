@extends('admin.layouts.app')

@section('title', 'Payments & Revenue')
@section('page-title', 'Payments & Revenue Dashboard')

@section('content')
<div class="container-fluid">
    <!-- Revenue Summary Cards -->
    <div class="row mb-4">
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <i class="fas fa-rupee-sign fa-2x mb-2"></i>
                    <h4 id="totalRevenue">₹0</h4>
                    <small>Total Revenue</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-day fa-2x mb-2"></i>
                    <h4 id="todayRevenue">₹0</h4>
                    <small>Today's Revenue</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-info text-white">
                <div class="card-body text-center">
                    <i class="fas fa-calendar-alt fa-2x mb-2"></i>
                    <h4 id="monthlyRevenue">₹0</h4>
                    <small>This Month</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <i class="fas fa-users fa-2x mb-2"></i>
                    <h4 id="activeSubscribers">0</h4>
                    <small>Active Subscribers</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-secondary text-white">
                <div class="card-body text-center">
                    <i class="fas fa-user-clock fa-2x mb-2"></i>
                    <h4 id="trialUsers">0</h4>
                    <small>Active Trials</small>
                </div>
            </div>
        </div>
        <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
            <div class="card bg-dark text-white">
                <div class="card-body text-center">
                    <i class="fas fa-chart-line fa-2x mb-2"></i>
                    <h4 id="totalTransactions">0</h4>
                    <small>Total Transactions</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Analytics -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-chart-pie"></i> Revenue Breakdown</h6>
                </div>
                <div class="card-body" id="revenueBreakdown">
                    <div class="text-center">Loading...</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-users"></i> User Statistics</h6>
                </div>
                <div class="card-body" id="userStats">
                    <div class="text-center">Loading...</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3">
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-clock"></i> Trial Analytics</h6>
                </div>
                <div class="card-body" id="trialStats">
                    <div class="text-center">Loading...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Transactions Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5><i class="fas fa-list"></i> Payment Transactions</h5>
            <input type="text" id="searchInput" class="form-control" style="width: 250px;" placeholder="Search transactions...">
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="transactionsTable">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Mobile</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Provider</th>
                            <th>Payment ID</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="9" class="text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Transaction Details Modal -->
<div class="modal fade" id="transactionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-receipt"></i> Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="transactionDetails">
                <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>
    </div>
</div>

<script>
const PaymentUtils = {
    formatCurrency: (amount) => '₹' + parseFloat(amount).toLocaleString('en-IN', {minimumFractionDigits: 2}),
    formatDate: (dateStr) => new Date(dateStr).toLocaleDateString('en-US', { 
        year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'
    }),
    capitalizeFirst: (str) => str ? str.charAt(0).toUpperCase() + str.slice(1) : str,
    getTypeColor: (type) => ({trial: 'info', subscription: 'primary'})[type] || 'secondary',
    getStatusColor: (status) => ({completed: 'success', success: 'success', pending: 'warning', failed: 'danger', cancelled: 'secondary'})[status] || 'secondary',
    getStatusIcon: (status) => ({completed: 'check-circle', pending: 'clock', failed: 'times-circle', cancelled: 'ban'})[status] || 'question-circle',
    updateElement: (id, value, fallback = '₹0.00') => {
        const el = document.getElementById(id);
        if (el) el.textContent = value || fallback;
    }
};

document.addEventListener('DOMContentLoaded', function() {
    loadRevenue();
    loadTransactions();
    
    document.getElementById('searchInput').addEventListener('input', function() {
        loadTransactions(this.value);
    });
    
    function loadRevenue() {
        Promise.all([
            fetch('{{ route("admin.revenue.data") }}').then(r => r.json()),
            fetch('{{ route("admin.payments.stats") }}').then(r => r.json())
        ])
        .then(([revenueData, statsData]) => {
            // Update revenue stats
            PaymentUtils.updateElement('totalRevenue', PaymentUtils.formatCurrency(revenueData.total_revenue));
            PaymentUtils.updateElement('todayRevenue', PaymentUtils.formatCurrency(revenueData.today_revenue));
            PaymentUtils.updateElement('monthlyRevenue', PaymentUtils.formatCurrency(revenueData.monthly_revenue));
            PaymentUtils.updateElement('activeSubscribers', revenueData.active_subscribers, '0');
            PaymentUtils.updateElement('trialUsers', revenueData.trial_users, '0');
            PaymentUtils.updateElement('totalTransactions', statsData.total_transactions, '0');
            
            const subscriptionRate = revenueData.total_revenue > 0 ? ((revenueData.subscription_revenue / revenueData.total_revenue) * 100).toFixed(1) : 0;
            PaymentUtils.updateElement('subscriptionRate', subscriptionRate + '%');
            
            const trialPct = revenueData.total_revenue > 0 ? ((revenueData.trial_revenue / revenueData.total_revenue) * 100).toFixed(1) : 0;
            const subPct = revenueData.total_revenue > 0 ? ((revenueData.subscription_revenue / revenueData.total_revenue) * 100).toFixed(1) : 0;
            
            document.getElementById('revenueBreakdown').innerHTML = `
                <div class="mb-3">
                    <div class="d-flex justify-content-between"><span>Trial Revenue</span><span>${PaymentUtils.formatCurrency(revenueData.trial_revenue)} (${trialPct}%)</span></div>
                    <div class="progress mb-2"><div class="progress-bar bg-primary" style="width: ${trialPct}%"></div></div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between"><span>Subscription Revenue</span><span>${PaymentUtils.formatCurrency(revenueData.subscription_revenue)} (${subPct}%)</span></div>
                    <div class="progress"><div class="progress-bar bg-success" style="width: ${subPct}%"></div></div>
                </div>`;
            
            console.log('Subscription price from API:', revenueData.subscription_price);
            console.log('Trial price from API:', revenueData.trial_price);
            document.getElementById('userStats').innerHTML = `
                <div class="row text-center">
                    <div class="col-6"><div class="border-end"><h3 class="text-success">${revenueData.active_subscribers}</h3><p class="text-muted">Active Subscribers</p><small class="text-success">${PaymentUtils.formatCurrency(revenueData.subscription_price || 299)}/month</small></div></div>
                    <div class="col-6"><div><h3 class="text-warning">${revenueData.trial_users}</h3><p class="text-muted">Trial Users</p><small class="text-warning">${PaymentUtils.formatCurrency(revenueData.trial_price || 8)} per trial</small></div></div>
                </div>`;
            
            const conversionRate = revenueData.trial_users > 0 ? ((revenueData.active_subscribers / revenueData.trial_users) * 100).toFixed(1) : 0;
            document.getElementById('trialStats').innerHTML = `
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Conversion Rate</span>
                        <span class="fw-bold text-success">${conversionRate}%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" style="width: ${conversionRate}%"></div>
                    </div>
                </div>
                <div class="row text-center">
                    <div class="col-6 border-end">
                        <h5 class="text-info">${PaymentUtils.formatCurrency(revenueData.trial_revenue)}</h5>
                        <small class="text-muted">Trial Revenue</small>
                    </div>
                    <div class="col-6">
                        <h5 class="text-primary">${revenueData.trial_users}</h5>
                        <small class="text-muted">Active Trials</small>
                    </div>
                </div>`;
        })
        .catch(() => {
            ['totalRevenue', 'todayRevenue', 'monthlyRevenue'].forEach(id => PaymentUtils.updateElement(id, '₹0'));
            ['activeSubscribers', 'trialUsers', 'totalTransactions'].forEach(id => PaymentUtils.updateElement(id, '0'));
            document.getElementById('revenueBreakdown').innerHTML = '<div class="text-center text-muted">No data available</div>';
            document.getElementById('userStats').innerHTML = '<div class="text-center text-muted">No data available</div>';
            document.getElementById('trialStats').innerHTML = '<div class="text-center text-muted">No data available</div>';
        });
    }
    
    function loadTransactions(search = '') {
        const tbody = document.querySelector('#transactionsTable tbody');
        tbody.innerHTML = '<tr><td colspan="9" class="text-center">Loading...</td></tr>';
        
        fetch(`{{ route('admin.payments.data') }}?search=${encodeURIComponent(search)}`)
        .then(response => response.json())
        .then(data => {
            tbody.innerHTML = data.length ? data.map(t => `
                <tr>
                    <td><strong>${t.user_name}</strong></td>
                    <td><span class="text-muted">${t.user_mobile}</span></td>
                    <td><span class="badge bg-${PaymentUtils.getTypeColor(t.type)}">${PaymentUtils.capitalizeFirst(t.type)}</span></td>
                    <td><strong>₹${parseFloat(t.amount).toFixed(2)}</strong></td>
                    <td><span class="badge bg-${PaymentUtils.getStatusColor(t.status)}"><i class="fas fa-${PaymentUtils.getStatusIcon(t.status)}"></i> ${PaymentUtils.capitalizeFirst(t.status)}</span></td>
                    <td><span class="badge bg-secondary">${t.payment_provider || 'N/A'}</span></td>
                    <td><small class="text-muted">${t.provider_payment_id || 'N/A'}</small></td>
                    <td><small class="text-muted">${PaymentUtils.formatDate(t.created_at)}</small></td>
                    <td><button class="btn btn-sm btn-outline-primary" onclick="viewTransaction(${t.id})"><i class="fas fa-eye"></i> View</button></td>
                </tr>
            `).join('') : '<tr><td colspan="9" class="text-center">No transactions found</td></tr>';
        })
        .catch(() => tbody.innerHTML = '<tr><td colspan="9" class="text-center text-danger">Error loading transactions</td></tr>');
    }
});

function viewTransaction(id) {
    const modal = new bootstrap.Modal(document.getElementById('transactionModal'));
    document.getElementById('transactionDetails').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    modal.show();
    
    fetch(`{{ route('admin.payments.data') }}?id=${id}`)
        .then(response => response.json())
        .then(data => {
            const transaction = data.find(t => t.id == id);
            if (transaction) {
                document.getElementById('transactionDetails').innerHTML = `
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="fas fa-user"></i> User Information</h6>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <p><strong>Full Name:</strong> ${transaction.user_name}</p>
                                    <p><strong>Mobile Number:</strong> ${transaction.user_mobile}</p>
                                    <p><strong>User ID:</strong> #${transaction.id}</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="fas fa-credit-card"></i> Payment Information</h6>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <p><strong>Transaction Type:</strong> <span class="badge bg-${PaymentUtils.getTypeColor(transaction.type)}">${PaymentUtils.capitalizeFirst(transaction.type)}</span></p>
                                    <p><strong>Amount Paid:</strong> <span class="h5 text-success">${PaymentUtils.formatCurrency(transaction.amount)}</span></p>
                                    <p><strong>Currency:</strong> ${transaction.currency}</p>
                                    <p><strong>Payment Status:</strong> <span class="badge bg-${PaymentUtils.getStatusColor(transaction.status)}"><i class="fas fa-${PaymentUtils.getStatusIcon(transaction.status)}"></i> ${PaymentUtils.capitalizeFirst(transaction.status)}</span></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="text-primary"><i class="fas fa-building"></i> Payment Gateway Details</h6>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <p><strong>Payment Provider:</strong><br><span class="badge bg-info">${transaction.payment_provider || 'Not Available'}</span></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p><strong>Payment Gateway ID:</strong><br><code class="bg-white p-1 rounded">${transaction.provider_payment_id || 'Not Available'}</code></p>
                                        </div>
                                        <div class="col-md-4">
                                            <p><strong>Transaction Date:</strong><br>${PaymentUtils.formatDate(transaction.created_at)}</p>
                                        </div>
                                    </div>
                                    ${transaction.provider_subscription_id ? `
                                        <div class="row mt-2">
                                            <div class="col-12">
                                                <p><strong>Subscription ID:</strong><br><code class="bg-white p-1 rounded">${transaction.provider_subscription_id}</code></p>
                                            </div>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                    ${transaction.status === 'failed' ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-danger">
                                    <h6><i class="fas fa-exclamation-triangle"></i> Payment Failed</h6>
                                    <p class="mb-2">This payment transaction has failed. Common reasons include:</p>
                                    <ul class="mb-2">
                                        <li>Insufficient funds in user's account</li>
                                        <li>Payment gateway timeout</li>
                                        <li>Invalid payment method</li>
                                        <li>Network connectivity issues</li>
                                    </ul>
                                    <p class="mb-0"><strong>Action Required:</strong> Contact the payment provider or ask user to retry payment.</p>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                    ${transaction.status === 'pending' ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-warning">
                                    <h6><i class="fas fa-clock"></i> Payment Pending</h6>
                                    <p class="mb-0">This payment is still being processed. It may take a few minutes to complete.</p>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                    ${transaction.status === 'success' ? `
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-success">
                                    <h6><i class="fas fa-check-circle"></i> Payment Successful</h6>
                                    <p class="mb-0">This payment has been successfully processed and the user's ${transaction.type} has been activated.</p>
                                </div>
                            </div>
                        </div>
                    ` : ''}
                `;
            }
        })
        .catch(() => {
            document.getElementById('transactionDetails').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Error loading transaction details. Please try again.</div>';
        });
}
</script>
@endsection