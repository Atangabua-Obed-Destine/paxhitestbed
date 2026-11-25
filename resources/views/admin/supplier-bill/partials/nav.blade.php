{{-- The payables screens, one row of links, so each is a click from the others. --}}
<div class="ap-toolbar">
    @can('supplier-bill-view')
    <a href="{{ route('admin.supplier-bill.index') }}" class="btn btn-sm {{ Request::is('admin/account/supplier-bills') ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-file-invoice"></i> {{ __('Supplier Bills') }}</a>
    @endcan
    @can('supplier-payment-view')
    <a href="{{ route('admin.supplier-payment.index') }}" class="btn btn-sm {{ Request::is('admin/account/supplier-payments') ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-money-check-alt"></i> {{ __('Supplier Payments') }}</a>
    @endcan
    @can('supplier-bill-view')
    <a href="{{ route('admin.supplier-bill.aging') }}" class="btn btn-sm {{ Request::is('admin/account/supplier-bills/aging') ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-hourglass-half"></i> {{ __('Payables Aging') }}</a>
    <a href="{{ route('admin.supplier-bill.statement') }}" class="btn btn-sm {{ Request::is('admin/account/supplier-bills/statement') ? 'btn-primary' : 'btn-outline-primary' }}"><i class="fas fa-file-alt"></i> {{ __('Supplier Statement') }}</a>
    @endcan
    @can('item-supplier-view')
    <a href="{{ route('admin.item-supplier.index') }}" class="btn btn-sm btn-outline-secondary"><i class="fas fa-truck"></i> {{ __('Suppliers') }}</a>
    @endcan
</div>
