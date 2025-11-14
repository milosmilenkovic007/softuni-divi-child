(function($){
  // Global references to participants
  var $participantsList, $addParticipantBtn, participantNextIndex;
  
  function toggleCompany(){
    var isCompany = $('#cc_type_company').is(':checked');
    $('#cc-company-block').toggleClass('cc-hidden', !isCompany);
    var $participants = $('#cc-participants-wrapper');
    $participants.toggleClass('cc-hidden', !isCompany);
    
    // DON'T disable inputs - they won't submit when disabled!
    // Just hide them visually - they'll only submit if visible & filled
    // $participants.find('input, button').prop('disabled', !isCompany);

    // Reset participants when switching to individual
    if(!isCompany){
      if($participantsList && $participantsList.length){
        $participantsList.empty();
        participantNextIndex = 0;
      }
      if($addParticipantBtn && $addParticipantBtn.length){
        $addParticipantBtn.find('span').text('Dodaj polaznika');
      }
      $('#cc-save-participants').hide();
      $('.cc-participant-multiplier').remove();
    }

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
      // Expanded list of common credit-card gateway IDs
      var cardIds = [
        'nestpay','stripe','woocommerce_payments','wcpay',
        'mollie_wc_gateway_creditcard','braintree_cc','payu','payu_card',
        'vivawallet','mypos','mypos_virtual','authorize_net_cim_credit_card',
        'square_credit_card','paytabs_cc'
      ];
      var cardSel = cardIds.map(function(id){ return '[data-gateway="'+id+'"]'; }).join(', ');
      var $card = cardSel ? $options.filter(cardSel) : $();
      var $bacs = $options.filter('[data-gateway="bacs"]');
      var $others = $options.not($card).not($bacs);
      if($ul.length){
        // Append in preferred order: card, others, bacs
        if($card.length){ $card.appendTo($ul); }
        if($others.length){ $others.appendTo($ul); }
        if($bacs.length){ $bacs.appendTo($ul); }
      }

      // Prefer a card gateway as default (even if something else was selected before)
      var picked = false;
      for(var i=0;i<cardIds.length;i++){
        if(selectGatewayById(cardIds[i])){ picked = true; break; }
      }
    }
  }
  function initCustomCheckout(){
    // Initialize participants references FIRST (before toggleCompany is called)
    $participantsList = $('#cc-participants-list');
    $addParticipantBtn = $('#cc-add-participant');
    participantNextIndex = $participantsList.find('.cc-participant').length;
    
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

    // Participants - use global references already initialized
    var $list = $participantsList;
    var $addBtn = $addParticipantBtn;

    function participantTemplate(i){
      return [
        '<div class="cc-participant" data-index="'+i+'">',
          '<div class="cc-row cc-row-wide">',
            '<div class="cc-field cc-row-wide">',
              '<label for="participant_full_name_'+i+'">Ime i prezime polaznika</label>',
              '<input type="text" id="participant_full_name_'+i+'" name="participants['+i+'][full_name]" form="cc-form" />',
            '</div>',
          '</div>',
          '<div class="cc-row cc-row-wide">',
            '<div class="cc-field cc-row-wide">',
              '<label for="participant_email_'+i+'">E-mail</label>',
              '<input type="email" id="participant_email_'+i+'" name="participants['+i+'][email]" form="cc-form" />',
            '</div>',
          '</div>',
          '<div class="cc-row cc-row-wide">',
            '<div class="cc-field cc-row-wide">',
              '<label for="participant_phone_'+i+'">Telefon</label>',
              '<input type="text" id="participant_phone_'+i+'" name="participants['+i+'][phone]" form="cc-form" />',
            '</div>',
          '</div>',
          '<div class="cc-actions cc-actions-inline">',
            '<button type="button" class="cc-btn cc-btn-link cc-remove-participant">Ukloni polaznika</button>',
          '</div>',
        '</div>'
      ].join('');
    }

    $addBtn.on('click', function(){
      var html = participantTemplate(participantNextIndex++);
      $list.append(html);
      // Change button to icon-only after first participant
      if(participantNextIndex === 1){ 
        $addBtn.find('span').text('Dodaj još');
      }
      // Ensure the list is visible
      $('#cc-participants-wrapper').removeClass('collapsed');
      // Show save button
      $('#cc-save-participants').show();
    });

    $list.on('click', '.cc-remove-participant', function(){
      var $item = $(this).closest('.cc-participant');
      $item.remove();
      
      // Update add button text
      var count = $list.find('.cc-participant').length;
      if(count === 0){
        $addBtn.find('span').text('Dodaj polaznika');
        $('#cc-save-participants').hide();
        // Immediately update pricing when all participants removed
        updateParticipantPricing();
      } else {
        // Show save button when there are still participants
        $('#cc-save-participants').show();
      }
    });

    // Save participants button handler
    $('#cc-save-participants').on('click', function(){
      var $btn = $(this);
      $btn.prop('disabled', true).text('Ažuriranje...');
      
      updateParticipantPricing(function(){
        $btn.prop('disabled', false).text('Sačuvaj izmene').hide();
      });
    });

    // Calculate and update total based on number of participants
    function updateParticipantPricing(callback){
      var isCompany = $('#cc_type_company').is(':checked');
      
      // Count total participants: 1 (buyer) + added participants
      var addedParticipants = $list.find('.cc-participant').length;
      var totalParticipants = isCompany ? (1 + addedParticipants) : 1;

      // Update session via AJAX
      $.ajax({
        url: wc_checkout_params.ajax_url,
        type: 'POST',
        data: {
          action: 'update_participant_count',
          count: totalParticipants,
          customer_type: isCompany ? 'company' : 'individual',
          nonce: wc_checkout_params.update_order_review_nonce
        },
        success: function(response){
          // Update prices from fragments
          if(response.success && response.data.fragments){
            $.each(response.data.fragments, function(selector, html){
              $(selector).html(html);
            });
          }
          
          // Also trigger WooCommerce checkout update for other fragments
          $(document.body).trigger('update_checkout');
          
          // Show participant multiplier info
          if(isCompany && addedParticipants > 0){
            var $participantInfo = $('.cc-participant-multiplier');
            var infoHtml = '<div class="cc-participant-multiplier" style="font-size: 13px; color: #666; margin: 10px 0; padding: 10px; background: #f8f9fa; border-radius: 4px;">Broj polaznika: ' + totalParticipants + '</div>';
            
            if(!$participantInfo.length){
              $('.cc-summary__total').before(infoHtml);
            } else {
              $participantInfo.replaceWith(infoHtml);
            }
          } else {
            $('.cc-participant-multiplier').remove();
          }
          
          // Execute callback if provided
          if(typeof callback === 'function'){
            callback();
          }
        },
        error: function(xhr, status, error){
          if(typeof callback === 'function'){
            callback();
          }
        }
      });
    }

    // Store customer type in session on change
    $(document).on('change', 'input[name="customer_type"]', function(){
      var isCompany = $('#cc_type_company').is(':checked');
      
      // Update session and trigger price recalculation
      $.ajax({
        url: wc_checkout_params.ajax_url,
        type: 'POST',
        data: {
          action: 'update_customer_type',
          customer_type: isCompany ? 'company' : 'individual',
          nonce: wc_checkout_params.update_order_review_nonce
        },
        success: function(response){
          // Update prices from fragments if available
          if(response.success && response.data && response.data.fragments){
            $.each(response.data.fragments, function(selector, html){
              $(selector).html(html);
            });
          }
          
          // Also trigger checkout update for other fragments
          $(document.body).trigger('update_checkout');
        }
      });
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
    // cc_entrance (Broj) is NOT required
    requireById('cc_city');
    requireById('cc_postcode');
    // cc_phone (Telefon) is NOT required
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
    // Phone: OPTIONAL - validate format only if provided
    var phoneRaw = ($('#cc_phone').val()||'').trim();
    if(phoneRaw && !/^(\+381|0)[0-9\s\-()]{6,}$/.test(phoneRaw)){
      addFieldError($('#cc_phone').closest('.cc-field'), 'Unesite ispravan telefon'); ok=false;
    }

    if(isCompany){
      requireById('cc_company_name');
      requireById('cc_company_mb');
      requireById('cc_company_pib');
      // Participants are OPTIONAL - validate only if any are added
      var $plist = $('#cc-participants-list');
      if($plist.is(':visible')){
        var $items = $plist.find('.cc-participant');
        // If participants exist, validate each one
        if($items.length > 0){
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
