@extends('layouts.blank')

@section('content')
    <div class="text-center text-white mb-4">
        <h2>Mili Software Installation</h2>
        <h6 class="fw-normal">Please proceed step by step with proper data according to instructions</h6>
    </div>

    <div class="pb-2">
        <div class="progress" role="progressbar"
             aria-valuenow="40" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: 40%"></div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="p-4 mb-md-3 mx-xl-4 px-md-5">

            <div class="d-flex align-items-center column-gap-3 flex-wrap">
                <h5 class="fw-bold text-uppercase">Step 2.</h5>
                <h5 class="fw-normal">Mili Configuration</h5>
            </div>

            <p class="mb-4">
                Configure this Mili installation.
            </p>

            <form method="POST" action="{{ route('mili.setup',['token'=>bcrypt('step_3')]) }}">
                @csrf

                <div class="bg-light p-4 rounded mb-4">
                    <div class="form-group">
                        <label class="mb-2">Application Name</label>

                        <input type="text"
                               class="form-control"
                               name="app_name"
                               value="Mili"
                               required>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-dark px-sm-5">
                        Continue
                    </button>
                </div>
            </form>

        </div>
    </div>
@endsection
