@extends('admin.layouts.app')

@section('title', 'Revenue Summary')
@section('page-title', 'Revenue Summary')

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card bg-success text-white">
                <div class="card-body text-center">
                    <h2><i class="fas fa-rupee-sign"></i></h2>
                    <h3>₹0</h3>
                    <p>Total Revenue</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-primary text-white">
                <div class="card-body text-center">
                    <h2><i class="fas fa-vial"></i></h2>
                    <h3>₹0</h3>
                    <p>Trial Revenue (₹7 each)</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-warning text-white">
                <div class="card-body text-center">
                    <h2><i class="fas fa-sync"></i></h2>
                    <h3>₹0</h3>
                    <p>Subscription Revenue (₹299 each)</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users"></i> Subscriber Stats</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6">
                            <div class="text-center">
                                <h3 class="text-success">0</h3>
                                <p>Active Subscribers</p>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="text-center">
                                <h3 class="text-warning">0</h3>
                                <p>Trial Users</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-chart-pie"></i> Revenue Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="progress mb-3">
                        <div class="progress-bar bg-primary" style="width: 0%">Trial: 0%</div>
                    </div>
                    <div class="progress">
                        <div class="progress-bar bg-warning" style="width: 0%">Subscription: 0%</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection