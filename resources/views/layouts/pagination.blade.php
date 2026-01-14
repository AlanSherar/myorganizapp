@if(count($data) >= 10)
    <div class="d-flex justify-content-between align-items-center m-2">
        <form method="GET" action="{{ url()->current() }}" class="form-inline">
            @foreach(request()->except('per_page') as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <label for="per_page" class="mr-2">Show</label>
            <select name="per_page" id="per_page" class="form-control form-control-sm" onchange="this.form.submit()">
                @foreach ([10, 25, 50, 100] as $size)
                    <option value="{{ $size }}" {{ request('per_page', 10) == $size ? 'selected' : '' }}>
                        {{ $size }}
                    </option>
                @endforeach
            </select>
            <span class="ml-2">rows per page</span>
        </form>
    </div>
@endif

<div class="d-flex justify-content-center mt-3">
    {{ $data->links('pagination::bootstrap-4') }}
</div>
