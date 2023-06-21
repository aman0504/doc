@extends('layouts.app')
@section('content')
    <div class="container p-5">
        <p class="delivery-text">Bank Info</p>
        <div class="row driver-form  ps-sm-5">

            @if (isset($stripeerror))
                <div class="alert alert-danger" role="alert">
                    Please try again later.
                </div>
                <a href="{{ route('driver.bank-info') }}" class="btn btn-primary">Reload</a>
            @else
                @if (!$bankInfo)
                    <a href="{{ route('driver.banking-setup') }}" class="apply-btn">Connect Bank Account</a>
                @endif

                @if (session()->has('error'))
                    <div class="alert alert-warning">
                        {{ session()->get('error') }}
                    </div>
                @endif

                @if ($bankInfo)
                    @if ($bankInfo->payouts_enabled != 'active')
                        <div class="alert alert-warning"> Payouts are not enabled for your account. Please check all the
                            details are verified or not. </div>
                        <div class="mb-4">
                            <a href="{{ route('driver.banking-update') }}" class="apply-btn">Update Account Details</a>
                        </div>
                    @endif

                    @if ($bankInfo)
                        @include('driver.bank.form')
                        <a href="{{ route('driver.delete-bank') }}" class="apply-btn dlete-btn">Delete Bank Account</a>

                    @endif
                @endif
            @endif
        </div>
    </div>
@endsection
