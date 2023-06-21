<?php

namespace App\Http\Livewire\Customer;

use App\Enums\DeliveryStatus;
use App\Models\DriverTip;
use App\Models\Question;
use App\Models\ReviewQuestion;
use Livewire\Component;
use App\Models\Review as ReviewModel;
use Jantinnerezo\LivewireAlert\LivewireAlert;
use Exception;
use \Stripe\Stripe;
use \Stripe\StripeClient;
use \Stripe\Customer;
use App\Models\Delivery;
use App\Models\DriverPayout;

class Review extends Component
{

    use LivewireAlert;

    //...
    public $deliveryId, $delivery, $driverId;

    public $questions, $question = [];
    public $review, $rating, $tip, $from_user_id, $to_user_id;

    //..
    public $isTip = false, $cart_error;

    // card....
    public $card_name, $card_number, $exp_month, $exp_year, $cvv;

    public function mount($id)
    {
        // delivery...
        $this->delivery = Delivery::findOrFail(decrypt($id));

        // delivery Id...
        $this->deliveryId = $this->delivery->id;

        // driver Id...
        $this->driverId = $this->delivery->assignDriver->user->id;

        // Question list..
        $this->questions = Question::get();
    }

    public function resetFields()
    {
        $this->question = [];
        $this->review = '';
        $this->rating = '';
        $this->tip = '';
        $this->card_name = '';
        $this->card_number = '';
        $this->exp_month = '';
        $this->exp_year = '';
        $this->cvv = '';
    }

    public function updatedTip()
    {
        if ($this->tip) {
            $this->isTip = true;
        } else {
            $this->isTip = false;
        }
    }

    public function reviewValidation()
    {
        $this->validate([
            'question' => 'required|array',
            'review' => 'required',
            'rating' => 'required',
        ]);
    }

    public function reviewWithCardValidation()
    {
        $this->validate([
            'question.*' => 'required',
            'review' => 'required',
            'rating' => 'required',
            'card_name' => 'required',
            'card_number' => 'required',
            'exp_month' => 'required',
            'exp_year' => 'required',
            'cvv' => 'required|numeric|digits_between:3,4',
        ]);
    }

    public function store()
    {
        if ($this->tip) {
            // review with tip..
            $this->reviewWithCardValidation();
            $this->storeTip();
        } else {

            // review..
            $this->reviewValidation();

            $review = $this->storeReview($this->reviewData());

            $this->storeReviewQuestion($review->id, $this->questions);

            $this->updateDeliveryStatus();

            if ($this->delivery->is_payout_driver == '0') {
                if ($this->delivery->assignDriver->user->bankInfo && $this->delivery->assignDriver->user->bankInfo->payouts_enabled == 'active') {
                    try {

                        \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                        $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

                        $connectedAccountId   = $this->delivery->assignDriver->user->bankInfo->account_id;
                        $deliveryTotalAmount  = $this->delivery->payment->amount;
                        $deliveryDriverAmount = ($deliveryTotalAmount) ? ($deliveryTotalAmount / 100) * 40 : 0;
                        if ($deliveryDriverAmount > 0) {
                            //..
                            $this->deliveryAmountTransferFromDriver($deliveryDriverAmount, $connectedAccountId);
                        }

                    } catch (
                        \Stripe\Exception\RateLimitException |
                        \Stripe\Exception\InvalidRequestException |
                        \Stripe\Exception\AuthenticationException |
                        \Stripe\Exception\ApiConnectionException |
                        \Stripe\Exception\ApiErrorException |
                        Exception $e
                    ) {
                        $error = $e->getMessage();
                    }

                    if (isset($error)) {
                        $this->cart_error = $error;
                    }
                }
            }

            $this->resetFields();
            $this->alert('success', 'Thanks for review');
            return redirect()->route('customer.delivery_list');
        }
    }

    public function reviewData()
    {
        return array('from_user_id' => auth()->user()->id, 'to_user_id' => $this->driverId,  'review' => $this->review, 'rating' => $this->rating);
    }

    public function storeTip()
    {
        try {

            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));
            $token = $this->createStripeToken($stripe);

            $customer = $this->createStripeCustomer($stripe, $token);

            $charge = $this->createStripeCharge($stripe, $customer);

            $driverTip = $this->createDriverTip($charge);

            $review = $this->storeReview($this->reviewData());

            $this->storeReviewQuestion($review->id, $this->questions);

            $this->updateDeliveryStatus();


            if ($this->delivery->assignDriver->user->bankInfo && $this->delivery->assignDriver->user->bankInfo->payouts_enabled == 'active') {
                $connectedAccountId   = $this->delivery->assignDriver->user->bankInfo->account_id;

                $this->deliveryTipAmountTransferFromDriver($driverTip, $connectedAccountId);

                if ($this->delivery->is_payout_driver == '0') {

                    $deliveryTotalAmount  = $this->delivery->payment->amount;
                    $deliveryDriverAmount = ($deliveryTotalAmount) ? ($deliveryTotalAmount / 100) * 40 : 0;
                    if ($deliveryDriverAmount > 0) {
                        $this->deliveryAmountTransferFromDriver($deliveryDriverAmount, $connectedAccountId);
                    }
                }
            }

            $this->resetFields();
            $this->alert('success', 'Thanks for review and tip');
            return redirect()->route('customer.delivery_list');
        } catch (
            \Stripe\Exception\RateLimitException |
            \Stripe\Exception\InvalidRequestException |
            \Stripe\Exception\AuthenticationException |
            \Stripe\Exception\ApiConnectionException |
            \Stripe\Exception\ApiErrorException |
            Exception $e
        ) {
            $error = $e->getMessage();
        }

        if (isset($error)) {
            $this->cart_error = $error;
        }
    }

    private function updateDeliveryStatus()
    {
        $this->delivery->update(['status' => DeliveryStatus::AcceptedByUser]);
    }

    private function deliveryAmountTransferFromDriver($deliveryDriverAmount, $connectedAccountId)
    {
        $transfer = \Stripe\Transfer::create([
            'amount' => round($deliveryDriverAmount * 100),
            'currency' => 'usd',
            'destination' => $connectedAccountId,
        ]);

        $driverPay = new DriverPayout;
        $driverPay->users_id = $this->driverId;
        $driverPay->delivery_id = $this->deliveryId;
        $driverPay->amount = $deliveryDriverAmount;
        $driverPay->transaction_id = isset($transfer['id']) ? $transfer['id'] : null;
        $driverPay->type = 'delivery';
        $driverPay->save();

        $this->delivery->update(['is_payout_driver' => '1']);
    }

    private function deliveryTipAmountTransferFromDriver($driverTip, $connectedAccountId)
    {
        $tipAmount = $driverTip->amount;

        $transfer = \Stripe\Transfer::create([
            'amount' => round($tipAmount * 100),
            'currency' => 'usd',
            'destination' => $connectedAccountId,
        ]);

        $driverPay = new DriverPayout;
        $driverPay->users_id = $this->driverId;
        $driverPay->delivery_id = $this->deliveryId;
        $driverPay->amount = $tipAmount;
        $driverPay->transaction_id = isset($transfer['id']) ? $transfer['id'] : null;
        $driverPay->type = 'tip';
        $driverPay->save();

        $driverTip->update(['is_payout_driver' => '1']);
    }

    private function createStripeToken($stripe)
    {
        return $stripe->tokens->create([
            'card' => [
                'name' => $this->card_name,
                'number' => str_replace(' ', '', $this->card_number),
                'exp_month' => $this->exp_month,
                'exp_year' => $this->exp_year,
                'cvc' => $this->cvv,
            ],
        ]);
    }

    private function createStripeCustomer($stripe, $token)
    {
        return \Stripe\Customer::create([
            'source' => $token['id'],
            'email' => auth()->user()->email,
            'description' => 'My name is ',
        ]);
    }

    private function createStripeCharge($stripe, $customer)
    {
        return $stripe->charges->create([
            "amount" => $this->tip * 100,
            "currency" => "usd",
            "customer" => $customer['id'],
            "description" => "Delivery Tip",
            "capture" => true,
        ]);
    }

    private function createDriverTip($charge)
    {
        return DriverTip::create([
            'from_user_id' => auth()->user()->id,
            'to_user_id' => $this->driverId,
            'delivery_id' => $this->deliveryId,
            'transaction_id' => $charge->id,
            'balance_transaction' => $charge->balance_transaction,
            'customer' => $charge->customer,
            'currency' => $charge->currency,
            'amount' => $this->tip,
            'payment_status' => $charge->status,
            'is_payout_driver' => '0',
        ]);
    }

    private function storeReview($reviewData)
    {
        return ReviewModel::create($reviewData);
    }

    private function storeReviewQuestion($reviewId, $questions)
    {
        if ($questions) {
            foreach ($questions as $key => $answer) {
                ReviewQuestion::create([
                    'reviews_id' => $reviewId,
                    'questions_id' => $key,
                    'answer' => $answer,
                ]);
            }
        }
    }






















    public function storeTipOld($reviewData, $questions)
    {

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $stripe = new StripeClient(config('services.stripe.secret'));

            //.. create token...
            $token = $stripe->tokens->create([
                'card' => [
                    'name' => $this->card_name,
                    'number' => str_replace(' ', '', $this->card_number),
                    'exp_month' => $this->exp_month,
                    'exp_year' => $this->exp_year,
                    'cvc' => $this->cvv,
                ],
            ]);

            // create customer..
            $customer = Customer::create([
                'source' => $token['id'],
                'email' => auth()->user()->email,
                'description' => 'My name is ',
            ]);

            // customer id..
            $customer_id = $customer['id'];

            //charge payment
            $charge = $stripe->charges->create([
                "amount" =>  $this->tip * 100,
                "currency" => "usd",
                "customer" => $customer_id,
                "description" => "Delivery",
                "capture" => true,
            ]);

            $payment = new DriverTip;
            $payment->from_user_id = auth()->user()->id;
            $payment->to_user_id = $this->driverId;
            $payment->delivery_id = $this->deliveryId;
            $payment->transaction_id = $charge->id;
            $payment->balance_transaction = $charge->balance_transaction;
            $payment->customer = $charge->customer;
            $payment->currency = $charge->currency;
            $payment->amount = $this->tip;
            $payment->payment_status = $charge->status;
            $payment->save();

            $review = $this->storeReview($reviewData);
            $this->storeReviewQuestion($review->id, $questions);

            $this->resetFields();
            $this->alert('success', 'You added review');
            return redirect()->route('customer.delivery_list');
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

    // public function storeReview($reviewData)
    // {
    //     $review = ReviewModel::create($reviewData);
    //     return $review;
    // }

    // public function storeReviewQuestion($reviewId, $questions)
    // {
    //     if ($questions) {
    //         foreach ($questions as $key => $answer) {
    //             $reviewQuestion = new  ReviewQuestion;
    //             $reviewQuestion->reviews_id = $reviewId;
    //             $reviewQuestion->questions_id = $key;
    //             $reviewQuestion->answer = $answer;
    //             $reviewQuestion->save();
    //         }
    //         return $reviewQuestion;
    //     }
    // }

    public function render()
    {
        return view('livewire.customer.review')->extends('layouts.app');
    }
}
