{{--
    Budget and period pickers, shared by the book and the summary so the two
    always agree about what "this month" means.

    Expects: $budget, $budgets, $period, $periods, $route
--}}
<form method="get" action="{{ route($route) }}" class="db-toolbar">
    <div class="db-field">
        <label for="db_budget">{{ __('Budget') }}</label>
        <select name="budget_id" id="db_budget" class="form-control" onchange="this.form.submit()">
            @foreach($budgets as $b)
                <option value="{{ $b->id }}" {{ $b->id === $budget->id ? 'selected' : '' }}>{{ $b->title }}</option>
            @endforeach
        </select>
    </div>

    <div class="db-field">
        <label for="db_period">{{ __('Month') }}</label>
        <select name="period_id" id="db_period" class="form-control" onchange="this.form.submit()">
            @foreach($periods as $p)
                <option value="{{ $p->id }}" {{ $p->id === $period->id ? 'selected' : '' }}>{{ $p->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="db-actions">
        <a href="{{ route('admin.daybook.index', ['budget_id' => $budget->id, 'period_id' => $period->id]) }}"
           class="btn btn-sm {{ $route === 'admin.daybook.index' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-book me-1"></i>{{ __('The book') }}
        </a>
        <a href="{{ route('admin.daybook.summary', ['budget_id' => $budget->id, 'period_id' => $period->id]) }}"
           class="btn btn-sm {{ $route === 'admin.daybook.summary' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-list-ol me-1"></i>{{ __('Monthly summary') }}
        </a>
        <a href="{{ route('admin.daybook.analysis', ['budget_id' => $budget->id, 'period_id' => $period->id]) }}"
           class="btn btn-sm {{ $route === 'admin.daybook.analysis' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-chart-line me-1"></i>{{ __('Trends') }}
        </a>
        <a href="{{ route('admin.daybook.pdf', ['budget_id' => $budget->id, 'period_id' => $period->id]) }}"
           class="btn btn-sm btn-light"><i class="fas fa-file-pdf me-1"></i>{{ __('PDF') }}</a>
        <a href="{{ route('admin.daybook.excel', ['budget_id' => $budget->id, 'period_id' => $period->id]) }}"
           class="btn btn-sm btn-light"><i class="fas fa-file-excel me-1"></i>{{ __('Excel') }}</a>
    </div>
</form>
