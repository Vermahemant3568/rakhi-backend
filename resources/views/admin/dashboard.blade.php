@extends('admin.layouts.app')

@section('title', 'Admin Dashboard')
@section('page-title', 'Dashboard')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center">
                    <h1 class="display-4 text-primary">
                        <i class="fas fa-home"></i> Welcome to Admin Dashboard
                    </h1>
                    <p class="lead">Manage your application from this central control panel.</p>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <h5><i class="fas fa-users"></i> Total Users</h5>
                    <h3>0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <h5><i class="fas fa-language"></i> Languages</h5>
                    <h3>0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <h5><i class="fas fa-bullseye"></i> Goals</h5>
                    <h3>0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <h5><i class="fas fa-chart-line"></i> Active</h5>
                    <h3>0</h3>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection