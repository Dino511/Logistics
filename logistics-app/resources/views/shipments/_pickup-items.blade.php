{{--
  The items collected at one pickup stop. $n is the stop's number on the form (1 is the
  origin) and $nameInput the id of that stop's Name / warehouse field: when the name is
  an Inventory location, only the stock held there is offered.
--}}
<div class="pickup-items-wrap">
  <h3 class="items-title">Items to collect here</h3>
  @if ($inventoryDown)
    <p class="items-empty">The inventory system can't be reached right now, so items can't be added. Try again shortly.</p>
  @elseif (! count($stockOptions))
    {{-- Without this, the product list would simply be missing and look like a fault. --}}
    <p class="items-empty">There is no free stock to add right now. Every product in Inventory is either out of stock or
      already reserved by a shipment that isn't finished yet. Add stock in Inventory, or finish or cancel that shipment,
      and the products will appear here.</p>
  @else
    <div class="pickup-items" data-pickup="{{ $n }}" data-name-input="{{ $nameInput }}"></div>
    <div class="items-foot">
      <button type="button" class="btn sm pickup-add-item">+ Add item</button>
      <small class="items-note" aria-live="polite"></small>
    </div>
  @endif
</div>
