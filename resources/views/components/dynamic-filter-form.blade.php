{{-- resources/views/components/dynamic-filter-form.blade.php --}}
@props(['action', 'filters'])

<form method="GET" action="{{ $action }}" class="form-container p-4 mb-4">
    <div class="row g-3">
        @foreach ($filters as $filter)
            <div class="col-md-{{ $filter['width'] ?? 3 }}">
                <label for="{{ $filter['name'] }}" class="form-label fw-medium text-secondary">{{ $filter['label'] }}</label>
                @if ($filter['type'] === 'text')
                    <input type="text" id="{{ $filter['name'] }}" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" placeholder="{{ $filter['placeholder'] }}" class="form-control">
                @elseif ($filter['type'] === 'number')
                    <input type="number" id="{{ $filter['name'] }}" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" placeholder="{{ $filter['placeholder'] }}" class="form-control">
                @elseif ($filter['type'] === 'date')
                    <input type="date" id="{{ $filter['name'] }}" name="{{ $filter['name'] }}" value="{{ request($filter['name']) }}" class="form-control">
                @elseif ($filter['type'] === 'select')
                    <select id="{{ $filter['name'] }}" name="{{ $filter['name'] }}" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ $filter['placeholder'] }}</option>
                        @foreach ($filter['options'] as $value => $label)
                            <option value="{{ $value }}" {{ request($filter['name']) == $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
            </div>
        @endforeach
        <div class="col-md-12 d-flex justify-content-end">
            <button type="submit" class="btn btn-filter px-4">Filter</button>
        </div>
    </div>
</form>
