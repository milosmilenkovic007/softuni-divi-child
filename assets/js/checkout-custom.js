(function($){
  function toggleCompany(){
    var isCompany = $('#cc_type_company').is(':checked');
    $('#cc-company-block').toggleClass('cc-hidden', !isCompany);
    var $participants = $('#cc-participants');
    $participants.toggleClass('cc-hidden', !isCompany);
    // Enable/disable all participant inputs and buttons so they don't submit when hidden
    $participants.find('input, button').prop('disabled', !isCompany);

    // Adjust available payment methods
    configureGatewaysForCustomerType(isCompany);
  }

  function selectGatewayById(id){
    var $radio = $("input[name='cc_payment_method'][value='"+id+"']");
    if($radio.length){
      $radio.prop('checked', true).trigger('change');
      return true;
    }
    return false;
  }

  function configureGatewaysForCustomerType(isCompany){
    var $options = $('.cc-pay-option');
    if(!$options.length){ return; }

    if(isCompany){
      // Show only BACS (uplata na račun)
      $options.each(function(){
        var $opt = $(this); var id = $opt.data('gateway');
        var show = (id === 'bacs');
        $opt.toggleClass('cc-hidden', !show);
        $opt.find('input').prop('disabled', !show);
      });
      // Force select BACS if exists, else first visible
      if(!selectGatewayById('bacs')){
        var $firstVisible = $options.filter(':not(.cc-hidden)').first().find('input');
        if($firstVisible.length){ $firstVisible.prop('checked', true).trigger('change'); }
      }
    } else {
      // Show all options and enable them
      $options.removeClass('cc-hidden').find('input').prop('disabled', false);

      // Reorder so that card gateway is on top and bacs at bottom
      var $ul = $('.cc-payments__ul');
      var $card = $options.filter('[data-gateway="nestpay"], [data-gateway="stripe"], [data-gateway="woocommerce_payments"], [data-gateway="wcpay"]');
      var $bacs = $options.filter('[data-gateway="bacs"]');
      var $others = $options.not($card).not($bacs);
      if($ul.length){
        // Append in preferred order: card, others, bacs
        if($card.length){ $card.appendTo($ul); }
        if($others.length){ $others.appendTo($ul); }
        if($bacs.length){ $bacs.appendTo($ul); }
      }

      // Prefer a card gateway as default (even if something else was selected before)
      if(!selectGatewayById('nestpay') && !selectGatewayById('stripe') && !selectGatewayById('woocommerce_payments') && !selectGatewayById('wcpay')){
        var $first = $('.cc-pay-option:not(.cc-hidden)').first().find('input');
        if($first.length){ $first.prop('checked', true).trigger('change'); }
      }
    }
  }
  function initCustomCheckout(){
    // Initialize customer type toggle
    $(document).on('change', 'input[name="customer_type"]', toggleCompany);
    toggleCompany();

    // Removed old continue button behavior; order button lives under payments now

  // Compose billing_address_2 from Broj and Stan
    var $number = $('#cc_entrance');
    var $apartment = $('#cc_apartment');
    var $addr2 = $('#cc_billing_address_2');
    function updateAddr2(){
      var parts = [];
      var num = ($number.val() || '').trim();
      var apt = ($apartment.val() || '').trim();
      if(num){ parts.push('Broj ' + num); }
      if(apt){ parts.push('Stan ' + apt); }
      $addr2.val(parts.join(', '));
    }
    $number.on('input change', updateAddr2);
    $apartment.on('input change', updateAddr2);
    updateAddr2();

    // Participants dynamic add/remove
  var $list = $('#cc-participants-list');
  var $addBtn = $('#cc-add-participant');
  var nextIndex = $list.find('.cc-participant').length;

    function participantTemplate(i){
      return [
        '<div class="cc-participant" data-index="'+i+'">',
          '<div class="cc-row cc-row-wide">',
            '<div class="cc-field cc-row-wide">',
              '<label for="participant_full_name_'+i+'">Ime i prezime polaznika</label>',
              '<input type="text" id="participant_full_name_'+i+'" name="participants['+i+'][full_name]" />',
            '</div>',
          '</div>',
          '<div class="cc-row cc-row-wide">',
            '<div class="cc-field cc-row-wide">',
              '<label for="participant_email_'+i+'">E-mail</label>',
              '<input type="email" id="participant_email_'+i+'" name="participants['+i+'][email]" />',
            '</div>',
          '</div>',
          '<div class="cc-row cc-row-wide">',
            '<div class="cc-field cc-row-wide">',
              '<label for="participant_phone_'+i+'">Telefon</label>',
              '<input type="text" id="participant_phone_'+i+'" name="participants['+i+'][phone]" />',
            '</div>',
          '</div>',
          '<div class="cc-actions cc-actions-inline">',
            '<button type="button" class="cc-btn cc-btn-link cc-remove-participant">Ukloni polaznika</button>',
          '</div>',
        '</div>'
      ].join('');
    }

    $addBtn.on('click', function(){
      var html = participantTemplate(nextIndex++);
      $list.append(html);
      // Change button label after the first participant is added
      if(nextIndex > 1){ $addBtn.text('Dodaj još jednog polaznika'); }
      // Ensure the list is visible
      $('#cc-participants').removeClass('collapsed');
    });

    $list.on('click', '.cc-remove-participant', function(){
      var $item = $(this).closest('.cc-participant');
      $item.remove();
    });

    // Payments: sync radio selection to hidden input in main form
    function syncPaymentHidden(){
      var val = $('input[name="cc_payment_method"]:checked').val() || '';
      $('#cc_payment_method').val(val);
    }
    $(document).on('change', 'input[name="cc_payment_method"]', syncPaymentHidden);
    // Initialize default from first radio if exists
    syncPaymentHidden();
    // Also configure gateways on load according to the initial selection
    configureGatewaysForCustomerType($('#cc_type_company').is(':checked'));

    // Validation on submit
    $('#cc-form').on('submit', function(e){
      var isValid = validateCheckoutForm();
      if(!isValid){ e.preventDefault(); }
    });
  }

  function addFieldError($field, message){
    $field.addClass('cc-error');
    // Remove old message
    $field.find('.cc-error__msg').remove();
    // Append message
    $field.append('<div class="cc-error__msg">'+ (message || '*Polje je obavezno') +'</div>');
  }

  function clearFieldError($field){
    $field.removeClass('cc-error');
    $field.find('.cc-error__msg').remove();
  }

  function validateCheckoutForm(){
    var ok = true;
    // Clear previous errors
    $('.cc-field').each(function(){ clearFieldError($(this)); });

    var isCompany = $('#cc_type_company').is(':checked');

    function requireById(id){
      var $input = $('#'+id);
      var $field = $input.closest('.cc-field');
      if(!$input.length){ return true; }
      var val = ($input.val() || '').trim();
      if(!val){ addFieldError($field); ok = false; }
      return !!val;
    }

    // Common required
    requireById('cc_first_name');
    requireById('cc_last_name');
    requireById('cc_street');
    requireById('cc_entrance');
    requireById('cc_city');
    requireById('cc_postcode');
    requireById('cc_phone');
    requireById('cc_email');

    // Basic format checks
    var email = ($('#cc_email').val()||'').trim();
    if(email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)){
      addFieldError($('#cc_email').closest('.cc-field'), 'Unesite ispravan e‑mail'); ok=false;
    }
    // Postcode: exactly 5 digits (Serbia)
    var postcode = ($('#cc_postcode').val()||'').trim();
    if(postcode && !/^\d{5}$/.test(postcode)){
      addFieldError($('#cc_postcode').closest('.cc-field'), 'Poštanski broj mora imati 5 cifara'); ok=false;
    }
    // Phone: accept +381 or 0 prefix, allow digits and separators
    var phoneRaw = ($('#cc_phone').val()||'').trim();
    if(phoneRaw && !/^(\+381|0)[0-9\s\-()]{6,}$/.test(phoneRaw)){
      addFieldError($('#cc_phone').closest('.cc-field'), 'Unesite ispravan telefon'); ok=false;
    }

    if(isCompany){
      requireById('cc_company_name');
      requireById('cc_company_mb');
      requireById('cc_company_pib');
      // At least one participant if visible
      var $plist = $('#cc-participants-list');
      if($plist.is(':visible')){
        var $items = $plist.find('.cc-participant');
        if(!$items.length){
          ok=false;
          // Show a general message at the card level
          var $card = $('#cc-participants');
          if($card.find('.cc-error__msg').length===0){
            $card.append('<div class="cc-error__msg">Dodajte bar jednog polaznika</div>');
          }
        } else {
          // Validate each participant: full_name and email
          $items.each(function(){
            var $item = $(this);
            var idx = $item.data('index');
            var $fnField = $item.find('#participant_full_name_'+idx).closest('.cc-field');
            var $emField = $item.find('#participant_email_'+idx).closest('.cc-field');
            var fn = ($item.find('#participant_full_name_'+idx).val()||'').trim();
            var em = ($item.find('#participant_email_'+idx).val()||'').trim();
            if(!fn){ addFieldError($fnField); ok=false; }
            if(!em || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(em)){ addFieldError($emField, 'Unesite ispravan e‑mail'); ok=false; }
          });
        }
      }
    }

    // Scroll to first error
    if(!ok){
      var $firstErr = $('.cc-error').first();
      if($firstErr.length){
        var el = $firstErr.get(0);
        el.scrollIntoView({behavior:'smooth', block:'center'});
      }
    }
    return ok;
  }
  if(document.readyState === 'loading'){
    document.addEventListener('DOMContentLoaded', initCustomCheckout);
  } else {
    initCustomCheckout();
  }
})(jQuery);
