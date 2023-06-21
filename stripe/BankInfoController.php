<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Models\BankInfo;
use Illuminate\Http\Request;
use Stripe;
use Exception;
use Jantinnerezo\LivewireAlert\LivewireAlert;

class BankInfoController extends Controller
{
    use LivewireAlert;
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
    }

    /**
     * show bank info page.
     *
     * @return void
     */
    public function index()
    {
        $user = auth()->user();
        $bankInfo = BankInfo::where(['user_id' => $user->id])->first();

        $message = "";

        if ($bankInfo) {

            $stripe = new \Stripe\StripeClient(
                config('services.stripe.secret')
            );
            try {

                $account = @$stripe->accounts->retrieve($bankInfo->account_id);

                $status = '';
                if ($account && $account->payouts_enabled) {
                    $status = 'active';
                } else {
                    $status = 'inactive';
                }

                BankInfo::where('id', $bankInfo->id)->update(["payouts_enabled" => $status]);
                $bankInfo = $bankInfo->refresh();
            } catch (\Stripe\Exception\RateLimitException $e) {
                $message = $e->getMessage();
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                $message = $e->getMessage();
            } catch (\Stripe\Exception\AuthenticationException $e) {
                $message = $e->getMessage();
            } catch (\Stripe\Exception\ApiConnectionException $e) {
                $message = $e->getMessage();
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $message = $e->getMessage();
            } catch (Exception $e) {
                $message = $e->getMessage();
            }
        }

        return view('driver.bank.bank-info', compact('bankInfo', 'message'));
    }


    /**
     * Stripe Connect Account add..
     */

    public function connectedAccountCreate()
    {
        $error = "";
        $user = auth()->user();
        $bankInfo = BankInfo::where(['user_id' =>  $user->id])->first();

        try {
            //stripe client..
            $stripe = new \Stripe\StripeClient(
                config('services.stripe.secret')
            );


            if (!$bankInfo) {
                $account = $stripe->accounts->create([
                    'type' => 'custom',
                    'country' => 'US',
                    'email' => $user->email,
                    'capabilities' => [
                        'card_payments' => ['requested' => true],
                        'transfers' => ['requested' => true],
                    ],
                ]);

                if (isset($account->id)) {
                    //save bank account..
                    $info = new BankInfo();
                    $info->user_id = $user->id;
                    $info->account_id = $account->id;
                    $info->status = "pending";
                    $info->payouts_enabled = "pending";
                    $info->save();

                    // create link for account update and send
                    $link = $stripe->accountLinks->create([
                        'account' => $account->id,
                        'refresh_url' => 'http://127.0.0.1:8000/driver/banking-info-error',
                        'return_url' => 'http://127.0.0.1:8000/driver/banking-info-success',
                        'type' => 'account_onboarding',
                    ]);
                    return redirect()->to($link->url);
                } else {
                    $error = "Error in account create.";
                }
            } else {
                $error = "Account has already connected with stripe.";
            }

            return redirect()->back()->with('error', $error);
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            return redirect()->back()->with('error', $e->getMessage());
            // (maybe you changed API keys recently)
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Network communication with Stripe failed
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Display a very generic error to the user, and maybe send
            return redirect()->back()->with('error', $e->getMessage());
            // yourself an email
        } catch (Exception $e) {
            // Something else happened, completely unrelated to Stripe
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('error', $error);
    }

    /**
     * Stripe Connect Account update..
     */
    public function connectedAccountUpdate()
    {
        $error = "";
        try {
            //stripe client..
            $stripe = new \Stripe\StripeClient(
                config('services.stripe.secret')
            );

            $user = auth()->user();
            $bankInfo = BankInfo::where(['user_id' => $user->id])->first();

            if ($bankInfo) {
                $account = $stripe->accounts->retrieve(
                    $bankInfo->account_id
                );

                if ($account) {
                    if (!$account->payouts_enabled) {
                        $link = $stripe->accountLinks->create([
                            'account' => $bankInfo->account_id,
                            'refresh_url' => 'http://127.0.0.1:8000/driver/banking-info-error',
                            'return_url' => 'http://127.0.0.1:8000/driver/banking-info-success',
                            'type' => 'account_update',
                        ]);
                        return redirect()->to($link->url);
                    } else {
                        BankInfo::where('id', $bankInfo->id)->update(["payouts_enabled" => 'active']);
                        $error = "Payouts are enabled for your account.";
                    }
                }
            } else {
                $error = "No bankInfo found.";
            }

            return redirect()->back()->with('error', $error);
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            return redirect()->back()->with('error', $e->getMessage());
            // (maybe you changed API keys recently)
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Network communication with Stripe failed
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Display a very generic error to the user, and maybe send
            return redirect()->back()->with('error', $e->getMessage());
            // yourself an email
        } catch (Exception $e) {
            // Something else happened, completely unrelated to Stripe
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('error', $error);
    }


    /**
     * return successfully connected bank.
     *
     * @return void
     */
    public function bankingInfoSuccess()
    {
        $user = auth()->user();
        $bankInfo = BankInfo::where(['user_id' => $user->id])->first();
        try {
            if ($bankInfo) {
                $stripe = new \Stripe\StripeClient(
                    env('STRIPE_SECRET')
                );
                $account = $stripe->accounts->retrieve($bankInfo->account_id);
                $status = '';
                if ($account && $account->payouts_enabled) {
                    $status = 'active';
                } else {
                    $status = 'inactive';
                }

                BankInfo::where('id', $bankInfo->id)->update(["payouts_enabled" => $status]);
                $bankInfo = $bankInfo->refresh();
            }
        } catch (\Exception $e) {
            return redirect('/driver/banking-info')->with('error', $e->getMessage());
        }
        return redirect('/driver/banking-info');
    }

    /**
     * check return banking any error.
     *
     * @return void
     */
    public function bankingInfoError()
    {
        redirect('/driver/banking-info')->with('error', 'Try again.');
    }

    /**
     * save bank detail delete.
     *
     * @return void
     */
    public function connectedAccountDelete()
    {
        $user = auth()->user();
        BankInfo::where(['user_id' => $user->id])->delete();
        // $user->stripe_customer = null;
        // $user->save();
        $this->flash('success', 'Your bank details deleted successfully');
        return redirect()->back();
    }

    /**
     * save bank detail in stripe.
     *
     * @return void
     */
    public function saveBank(Request $request)
    {
        $request->validate([
            'account_number' => 'required',
            'routing_number' => 'required|min:9',
            'account_holder_name' => 'required',
        ]);

        $user = auth()->user();
        $bankInfo = BankInfo::where(['user_id' => $user->id])->first();

        try {

            $stripe = new \Stripe\StripeClient(
                config('services.stripe.secret')
            );

            $bank = $stripe->accounts->createExternalAccount(
                $bankInfo->account_id,
                [
                    'external_account' => [
                        "currency" => "usd",
                        "country" => "us",
                        "object" => "bank_account",
                        "account_holder_name" => $request->account_holder_name,
                        "routing_number" => $request->routing_number,
                        "account_number" => $request->account_number,
                    ],
                ]
            );


            if ($bank) {

                // save account details..
                BankInfo::where('id', $bankInfo->id)->update([
                    "account_holder_name" => $request->account_holder_name,
                    "routing_number" => $request->routing_number,
                    "account_number" => $request->account_number
                ]);
            }

            $this->flash('success', 'Your bank details are added successfully');
            return redirect()->back();
        } catch (\Stripe\Exception\RateLimitException $e) {
            // Too many requests made to the API too quickly
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Stripe\Exception\InvalidRequestException $e) {
            // Invalid parameters were supplied to Stripe's API
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Stripe\Exception\AuthenticationException $e) {
            // Authentication with Stripe's API failed
            return redirect()->back()->with('error', $e->getMessage());
            // (maybe you changed API keys recently)
        } catch (\Stripe\Exception\ApiConnectionException $e) {
            // Network communication with Stripe failed
            return redirect()->back()->with('error', $e->getMessage());
        } catch (\Stripe\Exception\ApiErrorException $e) {
            // Display a very generic error to the user, and maybe send
            return redirect()->back()->with('error', $e->getMessage());
            // yourself an email
        } catch (Exception $e) {
            // Something else happened, completely unrelated to Stripe
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
