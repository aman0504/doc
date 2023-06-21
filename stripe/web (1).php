<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\Customer;
use App\Http\Livewire\Customer as CustomerComponent;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Driver;
use App\Http\Livewire\Driver as DriverComponent;

Route::get('/', function () {
    return redirect()->route('login');
})->name('home');

Route::get('/signup-customer', [AuthController::class, 'signUpCustomer'])->name('signup-customer');
Route::get('/signup-driver', [AuthController::class, 'signUpDriver'])->name('signup-driver');


//------------------------------------------------------
//------------------------After Login-------------------
//------------------------------------------------------
Route::middleware(['auth', 'verified'])->group(function () {
    // Customer
    Route::group(['middleware' => ['role:customer']], function () {
        Route::prefix('customer')->group(function () {
            Route::controller(Customer\CustomerController::class)->group(function () {
                Route::get('/deliveries/schedule', 'dashboard')->name('customer.dashboard');
                Route::get('/deliveries/{id}', 'viewDelivery')->name('customer.view_delivery');
            });
            Route::get('/deliveries', CustomerComponent\Deliveries::class)->name('customer.delivery_list');

            Route::get('/review/{id}', CustomerComponent\Review::class)->name('customer.review');
        });
    });


    // Driver
    Route::group(['middleware' => ['role:driver']], function () {
        Route::prefix('driver')->group(function () {
            Route::controller(Driver\DriverController::class)->group(function () {
                Route::get('/deliveries/board', 'dashboard')->name('driver.dashboard');
                Route::get('/deliveries/approved', 'approvedDelivery')->name('driver.approved_delivery');
            });

            Route::get('/deliveries/view/{id}', DriverComponent\ViewDelivery::class)->name('driver.view_delivery');
            Route::get('/deliveries/summary/{id}', DriverComponent\DeliverySummary::class)->name('driver.delivery-summary');

            //bank info..
            Route::controller(Driver\BankInfoController::class)->group(function () {
                Route::get('/banking-info', 'index')->name('driver.bank-info');
                Route::get('/banking-connect', 'connectedAccountCreate')->name('driver.banking-setup');
                Route::get('/banking-edit', 'connectedAccountUpdate')->name('driver.banking-update');
                Route::get('/banking-info-error', 'bankingInfoError')->name('driver.bank-info-error');
                Route::get('/banking-info-success', 'bankingInfoSuccess')->name('driver.bank-info-success');
                Route::post('/save-bank', 'saveBank')->name('driver.save-bank');
                Route::get('/delete-bank', 'connectedAccountDelete')->name('driver.delete-bank');
            });
        });
    });


    // Admin
    Route::group(['middleware' => ['role:admin']], function () {
        Route::prefix('admin')->group(function () {
            Route::controller(Admin\AdminController::class)->group(function () {
                Route::get('/dashboard', 'dashboard')->name('admin.dashboard');
                Route::get('/customers', 'customer')->name('admin.customers');
                Route::get('/drivers', 'driver')->name('admin.drivers');
                Route::get('/deliveries', 'delivery')->name('admin.deliveries');
            });

            Route::controller(Admin\StoreController::class)->group(function () {
                Route::get('/stores', 'index')->name('admin.store.index');
                Route::get('/stores/create', 'create')->name('admin.store.create');
                Route::post('/stores', 'store')->name('admin.store.add');
                Route::get('/stores/{id}/edit', 'edit')->name('admin.store.edit');
                Route::post('/stores/{id}', 'update')->name('admin.store.update');
                Route::get('/stores/{id}', 'destroy')->name('admin.store.destroy');
            });
        });
    });



});
