<form class="article-submit" method="post" action="{{ route('driver.save-bank') }}">
    @csrf
    <div class="row driver-form  p-sm-5">

        <div class="col-md-6 {!! $errors->has('account_number') ? 'has-error' : '' !!}">
            <label class="form-label">Account Number </label>
            <input type="text" class="form-control" value="{{ $bankInfo->account_number }}" name="account_number" placeholder="Account Number">
            {!! $errors->first('account_number', '<span class="help-block">:message</span>') !!}
        </div>
        <div class="col-md-6 {!! $errors->has('routing_number') ? 'has-error' : '' !!}">
            <label class="form-label">Routing Number</label>
            <input type="text" class="form-control" value="{{ $bankInfo->routing_number }}" name="routing_number" placeholder="Routing Number">
            {!! $errors->first('routing_number', '<span class="help-block">:message</span>') !!}
        </div>

        <div class="col-md-12 {!! $errors->has('account_holder_name') ? 'has-error' : '' !!}">
            <label class="form-label">Account Holder Name</label>
            <input type="text" class="form-control" value="{{ $bankInfo->account_holder_name }}" name="account_holder_name"
                placeholder="Account Holder Name">
            {!! $errors->first('account_holder_name', '<span class="help-block">:message</span>') !!}
        </div>

        <div class="col-md-6">
            <input type="submit" value="Submit" class="delivery-complete-btn g-btn" />
        </div>

    </div>
</form>
