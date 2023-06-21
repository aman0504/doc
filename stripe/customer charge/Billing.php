<?php

namespace App\Http\Livewire\Payment;

use App\Models\UserCard;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Livewire\Component;
use \Stripe\Customer;
use \Stripe\Stripe;
use \Stripe\StripeClient;
use App\Models\Subscription;
use App\Models\SubscriptionAmount;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;

class Billing extends Component
{

    use LivewireAlert;

    public $card_name;
    public $card_number;
    public $exp_month;
    public $exp_year;
    public $cvv;
    public $user;
    public $cart_error;

    public $card_id;

    public $userCard;

    protected $listeners = ['confirmedDelete'];

    public function mount()
    {
        $this->user = auth()->user();
    }

    public function stripeKey()
    {
        // key
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    public function showModel()
    {
        $this->emit('showModal');
        $this->resetErrorBag();
    }

    public function resetFields()
    {
        $this->card_name = '';
        $this->card_number = '';
        $this->exp_month = '';
        $this->exp_year = '';
        $this->cvv = '';
    }

    public function hideModel()
    {
        $this->resetErrorBag();
        $this->emit('hideModal');
        $this->reset('cart_error');
        $this->resetFields();
    }

    protected $rules = [
        'card_name' => 'required',
        'card_number' => 'required|numeric|digits:16',
        'exp_month' => 'required',
        'exp_year' => 'required',
        'cvv' => 'required|numeric|digits_between:3,4',
    ];

    public function saveCard()
    {
        $userCard = UserCard::where('users_id', auth()->user()->id)->first();

        if ($userCard) {
            $this->alert('success', 'Your card already added');
            $this->hideModel();
        } else {

            $this->validate();

            $date = date('Y-m-d');

            // Subscription...
            $subscription = Subscription::where('users_id', auth()->user()->id)->where('next_due_date', '<=', $date)->first();

            $numberOfConnectAccount = User::where('connect_id', $this->user->id)
                ->where('role', 'vendor')
                ->count();

            try {

                $this->stripeKey();

                // Client..
                $stripe = new StripeClient(config('services.stripe.secret'));

                //.. create token...
                $token = $stripe->tokens->create([
                    'card' => [
                        'name' => $this->card_name,
                        'number' => $this->card_number,
                        'exp_month' => $this->exp_month,
                        'exp_year' => $this->exp_year,
                        'cvc' => $this->cvv,
                    ],
                ]);

                // create customer..
                $customer = Customer::create([
                    'source' => $token['id'],
                    'email' => $this->user->email,
                    'description' => 'My name is ',
                ]);

                // customer id..
                $customer_id = $customer['id'];

                //save user card data..
                $card = new UserCard();
                $card->users_id = $this->user->id;
                $card->customer_id = $customer_id;
                $card->card_id = $token->card->id;
                $card->card_name = $this->card_name;
                $card->card_number = $token->card->last4;
                $card->exp_month = $token->card->exp_month;
                $card->exp_year = $token->card->exp_year;
                $card->save();

                if ($subscription && $subscription->payment_mode == '1') {

                    //....
                    $subscriptionStatus = $subscription->subscriptionAmount()->whereDate('from_date', '<=', $subscription->next_due_date)
                        ->whereDate('to_date', '>=', $subscription->next_due_date)->first();

                    if ($subscriptionStatus) {

                        $amount = $subscriptionStatus->amount * $numberOfConnectAccount;

                        if ($amount > 0) {

                            $stripeTax =  number_format(($amount * 2.9) / 100, 2);

                            $stripeTax = $stripeTax + 0.30;

                            $totalAmount = number_format(($amount + $stripeTax), 2);


                            //charge payment
                            $charge = $stripe->charges->create([
                                "amount" =>  $totalAmount * 100,
                                "currency" => "usd",
                                "customer" => $customer_id,
                                "description" => "Saavorconnect id: " . $subscription->id,
                                "capture" => true,
                            ]);


                            if ($charge) {

                                //save payment transaction details
                                $payment = new Payment;
                                $payment->users_id = auth()->user()->id;
                                $payment->subscription_amounts_id = $subscriptionStatus->id;
                                $payment->user_cards_id = $card->id;

                                $payment->transaction_id = $charge->id;
                                $payment->balance_transaction = $charge->balance_transaction;
                                $payment->customer = $charge->customer;
                                $payment->currency = $charge->currency;
                                $payment->amount = $amount;
                                $payment->stripe_tax = $stripeTax;
                                $payment->payment_status = $charge->status;
                                $payment->Payment_type = 'credit_card';
                                $payment->no_of_account = $numberOfConnectAccount;
                                $payment->amount_one_account = $subscriptionStatus->amount;
                                $payment->subscription_type = $subscription->subscription_type;
                                $payment->save();

                                // change status...
                                if ($subscription->subscription_type == '1') {
                                    $nextDate = Carbon::now()->modify('first day of next month')->format('Y-m-d');
                                } elseif ($subscription->subscription_type == '2') {
                                    $nextDate = now()->addMonths(3)->modify('first day of this month')->format('Y-m-d');
                                } elseif ($subscription->subscription_type == '3') {
                                    $nextDate = now()->addYear()->modify('first day of this year')->format('Y-m-d');
                                }

                                // update next due date
                                $subscription->next_due_date = $nextDate;
                                $subscription->save();

                                $subscriptionStatus->status = '1';
                                $subscriptionStatus->save();
                            }
                        }
                    }
                }



                $this->alert('success', 'Card save successfully');
                $this->hideModel();

                return redirect()->route('kitchen.payment.index');
            } catch (\Stripe\Exception\RateLimitException $e) {
                $error = $e->getMessage();
            } catch (\Stripe\Exception\InvalidRequestException $e) {
                $error = $e->getMessage();
            } catch (\Stripe\Exception\AuthenticationException $e) {
                $error = $e->getMessage();
            } catch (\Stripe\Exception\ApiConnectionException $e) {
                $error = $e->getMessage();
            } catch (\Stripe\Exception\ApiErrorException $e) {
                $error = $e->getMessage();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            if (@$error) {
                $this->cart_error = $error;
            }
        }
    }

    public function delete($id)
    {
        $this->card_id = $id;

        $this->alert('warning', 'Are you sure you want to remove your card?', [
            'toast' => false,
            'position' => 'center',
            'showCancelButton' => true,
            'cancelButtonText' => 'Cancel',
            'showConfirmButton' => true,
            'confirmButtonText' => 'Delete it',
            'onConfirmed' => 'confirmedDelete',
            'timer' => null,
        ]);
    }

    public function confirmedDelete()
    {
        $data = UserCard::findOrFail($this->card_id);

        if ($data) {

            try {
                $this->stripeKey();
                $stripe = new StripeClient(config('services.stripe.secret'));

                $deleteData =  $stripe->customers->deleteSource(
                    $data->customer_id,
                    $data->card_id,
                    []
                );

                $data->delete();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            $this->alert('success', 'Card deleted successfully');
        }
    }

    public function render()
    {
        $this->userCard = UserCard::where('users_id', $this->user->id)->first();

        return view('livewire.payment.billing');
    }
}
