<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\MerchantController;
use App\Http\Controllers\Api\V1\NfcSessionController;
use App\Http\Controllers\Api\V1\OpenPaymentController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\StatementController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\TransferController;
use App\Http\Controllers\Api\V1\WalletController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| AUTH
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('register');
    Route::post('/login', [AuthController::class, 'login'])->name('login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me'])->name('me');
        Route::put('/profile', [AuthController::class, 'updateProfile'])->name('profile.update');
        Route::put('/password', [AuthController::class, 'changePassword'])->name('password.change');
        Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('/logout-all', [AuthController::class, 'logoutAll'])->name('logout-all');
    });
});

/*
|--------------------------------------------------------------------------
| AUTHENTICATED API
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // PROFILE & SECURITY
    Route::prefix('profile')->name('profile.')->group(function () {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::patch('/', [ProfileController::class, 'update'])->name('update');
        Route::post('/change-password', [ProfileController::class, 'changePassword'])->name('change-password');
        Route::post('/set-pin', [ProfileController::class, 'setPin'])->name('set-pin');
        Route::get('/devices', [ProfileController::class, 'devices'])->name('devices');
        Route::post('/logout-all-devices', [ProfileController::class, 'logoutAllDevices'])->name('logout-all-devices');
    });

    // WALLET
    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::get('/', [WalletController::class, 'index'])->name('index');
        Route::get('/balance', [WalletController::class, 'balance'])->name('balance');
        Route::post('/topup', [WalletController::class, 'topup'])->name('topup');
        Route::post('/topup/merchant-code', [WalletController::class, 'merchantTopupCode'])->name('topup.merchant-code');
        Route::get('/topup/merchant-code/{id}', [WalletController::class, 'merchantTopupCodeStatus'])->whereNumber('id')->name('topup.merchant-code.status');
        Route::post('/withdraw', [WalletController::class, 'withdraw'])->name('withdraw');
        Route::get('/history', [WalletController::class, 'history'])->name('history');
        Route::get('/ledger', [WalletController::class, 'ledger'])->name('ledger');
    });

    // NFC SESSIONS
    Route::prefix('nfc/sessions')->name('nfc.sessions.')->group(function () {
        Route::post('/', [NfcSessionController::class, 'store'])->name('store');
        Route::post('/{id}/refresh', [NfcSessionController::class, 'refresh'])->name('refresh');
        Route::post('/{id}/cancel', [NfcSessionController::class, 'cancel'])->name('cancel');
        Route::get('/{token}', [NfcSessionController::class, 'show'])->name('show');
    });

    // TRANSFERS (Customer to Customer via NFC + approval)
    Route::prefix('transfers')->name('transfers.')->group(function () {
        Route::post('/nfc', [TransferController::class, 'nfc'])->name('nfc');
        Route::get('/pending-incoming', [TransferController::class, 'pendingIncoming'])->name('pending-incoming');
        Route::get('/{id}', [TransferController::class, 'show'])->whereNumber('id')->name('show');
        Route::post('/{id}/approve', [TransferController::class, 'approve'])->whereNumber('id')->name('approve');
        Route::post('/{id}/reject', [TransferController::class, 'reject'])->whereNumber('id')->name('reject');
        Route::post('/{id}/cancel', [TransferController::class, 'cancel'])->whereNumber('id')->name('cancel');
    });

    // MERCHANT (khusus role merchant)
    Route::prefix('merchant')->name('merchant.')->middleware('role:merchant')->group(function () {
        Route::get('/summary', [MerchantController::class, 'summary'])->name('summary');
        Route::get('/limits', [MerchantController::class, 'limits'])->name('limits');
        Route::post('/topups', [MerchantController::class, 'topUp'])->name('topups.store');
        Route::post('/topups/lookup-code', [MerchantController::class, 'lookupTopUpByCode'])->name('topups.lookup-code');
        Route::post('/topups/confirm-code', [MerchantController::class, 'confirmTopUpByCode'])->name('topups.confirm-code');
        Route::get('/topups', [MerchantController::class, 'topUps'])->name('topups.index');
        Route::get('/topups/recent', [MerchantController::class, 'recentTopUps'])->name('topups.recent');
    });

    // CUSTOMERS (pencarian oleh merchant)
    Route::get('/customers/search', [CustomerController::class, 'search'])
        ->middleware('role:merchant')->name('customers.search');

    // TRANSACTIONS
    Route::prefix('transactions')->name('transactions.')->group(function () {
        Route::get('/', [TransactionController::class, 'index'])->name('index');
        Route::get('/recent', [TransactionController::class, 'recent'])->name('recent');
        Route::get('/{id}', [TransactionController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('/{id}/receipt', [TransactionController::class, 'receipt'])->whereNumber('id')->name('receipt');
        Route::get('/{id}/receipt-image', [TransactionController::class, 'receiptImage'])->whereNumber('id')->name('receipt-image');
        Route::get('/{id}/print', [TransactionController::class, 'print'])->whereNumber('id')->name('print');
        Route::get('/{id}/verify', [TransactionController::class, 'verify'])->whereNumber('id')->name('verify');
    });

    // STATEMENTS
    Route::prefix('statements')->name('statements.')->group(function () {
        Route::post('/', [StatementController::class, 'store'])->name('store');
        Route::get('/', [StatementController::class, 'index'])->name('index');
        Route::get('/{id}', [StatementController::class, 'show'])->whereNumber('id')->name('show');
        Route::get('/{id}/download', [StatementController::class, 'download'])->whereNumber('id')->name('download');
    });

    // OPEN PAYMENT API
    Route::prefix('open-payment')->name('open-payment.')->group(function () {
        Route::post('/initiate', [OpenPaymentController::class, 'initiate'])->name('initiate');
        Route::post('/approve', [OpenPaymentController::class, 'approve'])->name('approve');
        Route::post('/reject', [OpenPaymentController::class, 'reject'])->name('reject');
        Route::get('/balance', [OpenPaymentController::class, 'balance'])->name('balance');
        Route::get('/status/{transaction_code}', [OpenPaymentController::class, 'status'])->name('status');
        Route::get('/receipt/{transaction_code}', [OpenPaymentController::class, 'receipt'])->name('receipt');
        Route::post('/statements', [StatementController::class, 'store'])->name('statements');
    });

    // NOTIFICATIONS (placeholder untuk dashboard)
    Route::get('/notifications', function () {
        return response()->json([
            'success' => true,
            'message' => 'Notifikasi.',
            'data' => ['notifications' => []],
        ]);
    })->name('notifications.index');
});

Route::fallback(function () {
    return response()->json([
        'success' => false,
        'message' => 'Endpoint tidak ditemukan.',
        'data' => null,
    ], 404);
});
