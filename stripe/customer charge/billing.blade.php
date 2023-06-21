<div class="">

    @if (@$userCard)

        <table class="table ">
            <thead>
                <tr>
                    <th>Card Name</th>
                    <th>Card Number</th>
                    <th>Exp. Month</th>
                    <th>Exp. Year</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <tr class="row_acount_r">
                    {{-- <th>{{ $subscription->firstItem() + $i }}</th> --}}
                    <td>{{ @$userCard->card_name }}</td>
                    <td>************{{ @$userCard->card_number }}</td>
                    <td>{{ date('F', strtotime('01-' . $userCard->exp_month . '-' . $userCard->exp_year)) }}</td>
                    <td>{{ @$userCard->exp_year }}</td>
                    <td>
                        <a href="javascript:voide(0)" wire:click="delete({{ @$userCard->id }})" class="btn_edit">
                            Delete
                        </a>
                    </td>
                </tr>
            </tbody>

        </table>
    @else
        @if ($user->subscription->status == '0')
            <div class="payment_form">
                <div class="row text-center mt-5">
                    <div class="thank_you_message newthnks">
                        <h2>Thankyou for Subscription</h2>
                        <p>Your request is in-process.<br> One of our team members will contact you soon.</p>
                    </div>
                </div>
            </div>
        @endif

        @if ($user->subscription->status == '1' && $user->subscription->payment_mode == '1')
            <div class="col-md-12 mt-4 " style="text-align: center; ">
                <button class="btn_s showModalCard">Add Card</button>
            </div>
        @endif

    @endif



    <!-- Accept Modal Start Here-->
    <div wire:ignore.self class="modal fade" id="addCardForm" tabindex="-1" aria-labelledby="addCardForm"
        aria-hidden="true">
        <div class="modal-dialog modal_style">
            <button type="button" class="btn btn-default closeModal" wire:click="hideModel">
                <span aria-hidden="true">&times;</span>
            </button>
            <div class="modal-content">
                <form action="#">
                    <div class="modal-header modal_h">
                        <h3>Make Payment</h3>
                    </div>
                    <div class="modal-body">

                        @if ($cart_error)
                            <div class="alert alert-dismissible alert-warning">
                                <button class="close cross-btn" type="button" data-dismiss="alert">×</button>
                                {!! $cart_error !!}
                            </div>
                        @endif
                        <div class="cstm_form_modal">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Card Name</label>
                                        <input type="text" wire:model="card_name" placeholder="Card Name"
                                            class="form-control">
                                        {!! $errors->first('card_name', '<span class="help-block">:message</span>') !!}
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Card Number</label>
                                        <input type="text" wire:model="card_number" placeholder="Card Number"
                                            class="form-control">
                                        {!! $errors->first('card_number', '<span class="help-block">:message</span>') !!}
                                    </div>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Exp. Month</label>
                                        <select wire:model="exp_month" class="form-control">
                                            <option value="">Select Type</option>
                                            @foreach (\App\Models\User::getMonths() as $i => $month)
                                                <option value="{{ $i }}">{{ $month }}</option>
                                            @endforeach
                                        </select>
                                        {!! $errors->first('exp_month', '<span class="help-block">:message</span>') !!}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Exp. Year</label>
                                        <select wire:model="exp_year" class="form-control">
                                            <option value="">Select Year</option>
                                            @foreach (\App\Models\User::getCrdYears() as $i => $year)
                                                <option value="{{ $year }}">{{ $year }}</option>
                                            @endforeach
                                        </select>
                                        {!! $errors->first('exp_year', '<span class="help-block">:message</span>') !!}
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-group">
                                        <label>Cvv</label>
                                        <input type="text" wire:model="cvv" placeholder="Cvv" class="form-control">
                                        {!! $errors->first('cvv', '<span class="help-block">:message</span>') !!}
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>
                    <div class="text-center mb-3">
                        <button type="button" class="btn_s b-0" wire:click="saveCard" wire:loading.attr="disabled">
                            <i wire:loading wire:target="saveCard" class="fa fa-spin fa-spinner"></i> Save
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
    <!-- Accept Modal Close Here-->

</div>

@push('scripts')
    <script>
        $(document).ready(function() {

            $(document).on('click', '.showModalCard', function(e) {
                $('#addCardForm').modal('show');
            });


            window.livewire.on('showModal', () => {
                $('#addCardForm').modal('show');
            })

            window.livewire.on('hideModal', () => {
                $('#addCardForm').modal('hide');
            })
        });
    </script>
@endpush
