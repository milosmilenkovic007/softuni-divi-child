<?php
/*
Template Name: Checkout (Custom)
Description: Blank canvas for a custom checkout built in Divi Child. Does not use Woo default checkout.
*/
if ( ! defined( 'ABSPATH' ) ) { exit; }
// Handle coupon application and order placement BEFORE any output
if ( function_exists( 'WC' ) ) {
  // Apply coupon
  if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['cc_apply_coupon'] ) ) {
    $coupon = isset( $_POST['cc_coupon_code'] ) ? sanitize_text_field( wp_unslash( $_POST['cc_coupon_code'] ) ) : '';
    if ( $coupon !== '' ) {
      WC()->cart->apply_coupon( $coupon );
      WC()->cart->calculate_totals();
    }
  }
  // Place order
  if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['cc_place_order'] ) ) {
    error_log("CC Checkout: POST request received, starting order processing");
    error_log("CC Checkout: Payment method from POST: " . (isset($_POST['payment_method']) ? $_POST['payment_method'] : 'NOT SET'));
    
    $nonce = isset($_POST['cc_checkout_nonce']) ? sanitize_text_field( wp_unslash($_POST['cc_checkout_nonce']) ) : '';
    if ( ! wp_verify_nonce( $nonce, 'cc_checkout' ) ) {
      error_log("CC Checkout: Nonce verification FAILED");
      echo '<div style="background: red; color: white; padding: 20px;">NONCE FAILED!</div>';
      wc_add_notice( __( 'Bezbednosna greška. Molimo pokušajte ponovo.', 'divi-child' ), 'error' );
    } else {
      error_log("CC Checkout: Nonce verified successfully");
      echo '<div style="background: green; color: white; padding: 20px;">NONCE OK! Processing...</div>';
      // Server-side validation of required fields
      $errors = array();
      $ctype = isset($_POST['customer_type']) ? sanitize_text_field( wp_unslash($_POST['customer_type']) ) : 'individual';
      $req = array(
        'billing_first_name' => 'Ime',
        'billing_last_name'  => 'Prezime',
        'billing_address_1'  => 'Ulica',
        'billing_city'       => 'Grad',
        'billing_postcode'   => 'Poštanski broj',
        'billing_email'      => 'E‑mail'
      );
      // Additional address number - NOT REQUIRED
      // if ( empty( $_POST['address_number'] ) ) { $errors[] = 'Unesite broj ulice.'; }
      foreach( $req as $key => $label ){
        if ( empty( $_POST[$key] ) ) { $errors[] = sprintf( 'Polje %s je obavezno.', $label ); }
      }
      if ( ! empty( $_POST['billing_email'] ) && ! is_email( wp_unslash( $_POST['billing_email'] ) ) ) {
        $errors[] = 'Unesite ispravan e‑mail.';
      }
      // Serbian postcode: exactly 5 digits
      if ( ! empty( $_POST['billing_postcode'] ) ) {
        $pc = trim( wp_unslash( $_POST['billing_postcode'] ) );
        if ( ! preg_match( '/^\d{5}$/', $pc ) ) {
          $errors[] = 'Poštanski broj mora imati 5 cifara.';
        }
      }
      // Phone: OPTIONAL - validate only if provided
      if ( ! empty( $_POST['billing_phone'] ) ) {
        $ph = trim( wp_unslash( $_POST['billing_phone'] ) );
        if ( ! preg_match( '/^(\+381|0)[0-9\s\-()]{6,}$/', $ph ) ) {
          $errors[] = 'Unesite ispravan telefon.';
        }
      }
      if ( $ctype === 'company' ){
        $creq = array(
          'billing_company' => 'Pravno lice',
          'billing_mb'      => 'Matični broj',
          'billing_pib'     => 'PIB',
        );
        foreach( $creq as $key => $label ){
          if ( empty( $_POST[$key] ) ) { $errors[] = sprintf( 'Polje %s je obavezno.', $label ); }
        }
        // Validate participants only if they are provided
        if ( ! empty( $_POST['participants'] ) && is_array( $_POST['participants'] ) ) {
          $has_any_participant = false;
          foreach( $_POST['participants'] as $p ){
            $fn = isset($p['full_name']) ? trim( wp_unslash( $p['full_name'] ) ) : '';
            $em = isset($p['email']) ? trim( wp_unslash( $p['email'] ) ) : '';
            // Check if this participant has any data entered
            if ( $fn || $em ) {
              $has_any_participant = true;
              // If data is entered, both name and valid email are required
              if ( ! $fn || ! is_email( $em ) ) {
                $errors[] = 'Polaznik mora imati ime i ispravan e‑mail.';
                break;
              }
            }
          }
        }
        // No error if no participants provided - company representative is the only participant
      }
      if ( ! empty( $errors ) ){
        echo '<div style="background: orange; color: white; padding: 20px; font-weight: bold;">VALIDATION ERRORS FOUND!</div>';
        echo '<div style="background: darkorange; color: white; padding: 10px;"><strong>Errors:</strong><ul>';
        foreach( $errors as $msg ){ 
          echo '<li>' . esc_html($msg) . '</li>';
          wc_add_notice( $msg, 'error' ); 
        }
        echo '</ul></div>';
        // Skip order creation; let template render and show notices
        return;
      }

      $checkout = WC()->checkout();
      $keys = array(
        'billing_first_name','billing_last_name','billing_address_1','billing_address_2',
        'billing_city','billing_postcode','billing_phone','billing_email','billing_country',
        'order_comments','billing_company','billing_mb','billing_pib','customer_type','payment_method',
        'participants' // Add participants to data passed to order creation
      );
      $data = array();
      foreach ( $keys as $k ) {
        if ( isset( $_POST[$k] ) ) {
          $v = wp_unslash( $_POST[$k] );
          // Special handling for participants array to preserve structure
          if ( $k === 'participants' && is_array($v) ) {
            $data[$k] = $v; // Keep as-is, will be sanitized in the hook
          } else {
            $data[$k] = is_array($v) ? wc_clean($v) : sanitize_text_field( $v );
          }
        }
      }

      try {
        $order_id = $checkout->create_order( $data );
        if ( is_wp_error( $order_id ) ) {
          throw new Exception( $order_id->get_error_message() );
        }
        
        $order = wc_get_order( $order_id );

        // Save custom company meta data explicitly
        if ( isset($data['customer_type']) ) {
          $order->update_meta_data( 'customer_type', $data['customer_type'] );
        }
        if ( isset($data['billing_company']) && !empty($data['billing_company']) ) {
          $order->update_meta_data( 'billing_company', $data['billing_company'] );
        }
        if ( isset($data['billing_mb']) && !empty($data['billing_mb']) ) {
          $order->update_meta_data( 'billing_mb', $data['billing_mb'] );
        }
        if ( isset($data['billing_pib']) && !empty($data['billing_pib']) ) {
          $order->update_meta_data( 'billing_pib', $data['billing_pib'] );
        }

        // Note: participants are saved via woocommerce_checkout_create_order hook in order-meta.php

        // Ensure the order has the selected payment method set
        $payment_method = isset( $data['payment_method'] ) ? $data['payment_method'] : '';
        $available = WC()->payment_gateways()->get_available_payment_gateways();
        
        error_log("CC Checkout: Payment method selected: " . $payment_method);
        error_log("CC Checkout: Available gateways: " . implode(', ', array_keys($available)));
        
        if ( $payment_method && isset( $available[ $payment_method ] ) ) {
          $order->set_payment_method( $available[ $payment_method ] );
        }
        $order->save();

        // Process payment and redirect
        if ( $payment_method && isset( $available[ $payment_method ] ) ) {
          $gateway = $available[ $payment_method ];
          
          error_log("CC Checkout: Processing payment with gateway: " . get_class($gateway));
          
          // For gateways like BACS (bank transfer) that don't require immediate processing
          if ( in_array( $payment_method, ['bacs', 'cheque', 'cod'], true ) ) {
            
            error_log("CC Checkout: Manual payment method detected, setting on-hold");
            
            // Set order status to on-hold for manual payment methods
            $order->update_status( 'on-hold', __('Ceka se uplata.', 'divi-child') );
            
            // Empty cart
            WC()->cart->empty_cart();
            
            $redirect_url = $order->get_checkout_order_received_url();
            
            error_log("CC Checkout: Redirecting to: " . $redirect_url);
            
            // Redirect to thank you page
            wp_safe_redirect( $redirect_url );
            exit;
          }
          
          error_log("CC Checkout: Calling process_payment for gateway");
          
          // For other gateways (like credit card), process payment normally
          $result = $gateway->process_payment( $order_id );
          
          error_log("CC Checkout: process_payment result: " . print_r($result, true));
          
          if ( isset($result['result']) && 'success' === $result['result'] && ! empty( $result['redirect'] ) ) {
            wp_safe_redirect( $result['redirect'] );
            exit;
          }
        }
        
        error_log("CC Checkout: Fallback redirect");
        
        // Fallback - redirect to thank you page
        WC()->cart->empty_cart();
        wp_safe_redirect( $order->get_checkout_order_received_url() );
        exit;
      } catch ( Exception $e ) {
        echo '<div style="background: red; color: white; padding: 20px; font-weight: bold;">EXCEPTION CAUGHT: ' . esc_html($e->getMessage()) . '</div>';
        echo '<div style="background: darkred; color: white; padding: 10px;">Stack trace: <pre>' . esc_html($e->getTraceAsString()) . '</pre></div>';
        error_log("CC Checkout Exception: " . $e->getMessage());
        error_log("CC Checkout Stack trace: " . $e->getTraceAsString());
        wc_add_notice( $e->getMessage(), 'error' );
      }
    }
  }
}

get_header();
?>

<style>
/* Custom Footer */
.softuni-custom-footer {
  background: #234465;
  color: white;
  padding: 25px 0;
  margin-top: 60px;
}

.footer-content {
  max-width: 1100px;
  margin: 0 auto;
  padding: 0 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 15px;
}

.footer-left {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.9);
}

.footer-right {
  display: flex;
  gap: 25px;
}

.footer-right a {
  color: rgba(255, 255, 255, 0.9);
  text-decoration: none;
  font-size: 14px;
  transition: color 0.3s ease;
}

.footer-right a:hover {
  color: #00ba96;
}

@media (max-width: 768px) {
  .footer-content {
    flex-direction: column;
    text-align: center;
  }
  
  .footer-right {
    flex-direction: column;
    gap: 12px;
  }
}
</style>

<main id="primary" class="site-main custom-checkout">
  <div class="container custom-checkout__container">
    <?php 
    // Render page content (Divi builder, etc.) but suppress the WooCommerce checkout shortcode
    global $shortcode_tags;
    $wc_checkout_cb = isset($shortcode_tags['woocommerce_checkout']) ? $shortcode_tags['woocommerce_checkout'] : null;
    if ($wc_checkout_cb) { remove_shortcode('woocommerce_checkout'); }
    if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
      <div class="custom-checkout__content">
        <?php the_content(); ?>
      </div>
    <?php endwhile; endif; 
    if ($wc_checkout_cb) { add_shortcode('woocommerce_checkout', $wc_checkout_cb); }
    ?>

    <div class="cc-layout">
      <div class="cc-left">
        <form class="cc-form" id="cc-form" method="post" novalidate>
          <input type="hidden" id="cc_payment_method" name="payment_method" value="" />
          <input type="hidden" name="cc_place_order" value="1" />
          <?php wp_nonce_field( 'cc_checkout', 'cc_checkout_nonce' ); ?>
          <div class="cc-section">
            <h2 class="cc-section__title">Način dostave</h2>
            <div class="cc-card">
              <div class="cc-row">
                <div class="cc-field cc-field-select">
                  <label for="cc_country">Država</label>
                  <select id="cc_country" name="country_display" disabled>
                    <option value="RS" selected>Srbija</option>
                  </select>
                  <input type="hidden" name="billing_country" value="RS" />
                </div>
              </div>

              <div class="cc-row">
                <div class="cc-field cc-half">
                  <label for="cc_first_name">Ime <span style="color: red;">*</span></label>
                  <input type="text" id="cc_first_name" name="billing_first_name" placeholder="Ime" />
                </div>
                <div class="cc-field cc-half">
                  <label for="cc_last_name">Prezime <span style="color: red;">*</span></label>
                  <input type="text" id="cc_last_name" name="billing_last_name" placeholder="Prezime" />
                </div>
              </div>

              <div class="cc-row">
                <div class="cc-field cc-segment">
                  <input type="radio" id="cc_type_individual" name="customer_type" value="individual" checked />
                  <label for="cc_type_individual">Fizičko lice</label>
                  <input type="radio" id="cc_type_company" name="customer_type" value="company" />
                  <label for="cc_type_company">Pravno lice</label>
                </div>
              </div>

              <div class="cc-row cc-company cc-hidden" id="cc-company-block">
                <div class="cc-field cc-row-wide">
                  <label for="cc_company_name">Pravno lice <span style="color: red;">*</span></label>
                  <input type="text" id="cc_company_name" name="billing_company" />
                </div>
                <div class="cc-field cc-half">
                  <label for="cc_company_mb">Matični broj <span style="color: red;">*</span></label>
                  <input type="text" id="cc_company_mb" name="billing_mb" />
                </div>
                <div class="cc-field cc-half">
                  <label for="cc_company_pib">PIB <span style="color: red;">*</span></label>
                  <input type="text" id="cc_company_pib" name="billing_pib" />
                </div>
              </div>

              <div class="cc-row cc-address">
                <div class="cc-field cc-street">
                  <label for="cc_street">Ulica <span style="color: red;">*</span></label>
                  <input type="text" id="cc_street" name="billing_address_1" />
                </div>
                <div class="cc-field cc-third">
                  <label for="cc_entrance">Broj</label>
                  <input type="text" id="cc_entrance" name="address_number" />
                </div>
                <div class="cc-field cc-third">
                  <label for="cc_apartment">Stan</label>
                  <input type="text" id="cc_apartment" name="apartment" />
                </div>
                <input type="hidden" id="cc_billing_address_2" name="billing_address_2" />
              </div>

              <div class="cc-row">
                <div class="cc-field cc-half">
                  <label for="cc_city">Grad <span style="color: red;">*</span></label>
                  <input type="text" id="cc_city" name="billing_city" />
                </div>
                <div class="cc-field cc-half">
                  <label for="cc_postcode">Poštanski broj <span style="color: red;">*</span></label>
                  <input type="text" id="cc_postcode" name="billing_postcode" />
                </div>
              </div>

              <div class="cc-row">
                <div class="cc-field cc-half">
                  <label for="cc_phone">Broj mobilnog telefona</label>
                  <input type="text" id="cc_phone" name="billing_phone" placeholder="+381" />
                </div>
                <div class="cc-field cc-half">
                  <label for="cc_email">E-mail <span style="color: red;">*</span></label>
                  <input type="email" id="cc_email" name="billing_email" />
                </div>
              </div>

              <div class="cc-row cc-row-wide">
                <div class="cc-field cc-row-wide">
                  <label for="cc_notes">Dodatne napomene kuriru (ili kurirskoj službi) u vezi sa isporukom</label>
                  <textarea id="cc_notes" name="order_comments" placeholder="Ukoliko imate dodatne napomene radi lakše isporuke molimo Vas da ih ovde unesete"></textarea>
                </div>
              </div>

              
            </div>
          </div>
        </form>
      </div>

      <aside class="cc-summary">
        <div class="cc-summary__card">
          <h3>Pregled narudžbine</h3>
          <div class="cc-summary__content" id="cc-order-review">
            <div class="cc-summary__row">
              <span>
                <?php 
                // Display product name(s) instead of generic "Narudžbina"
                if ( function_exists( 'WC' ) && WC()->cart ) {
                  $cart_items = WC()->cart->get_cart();
                  $product_names = array();
                  foreach ( $cart_items as $cart_item ) {
                    $product = $cart_item['data'];
                    if ( $product ) {
                      $product_names[] = $product->get_name();
                    }
                  }
                  if ( !empty($product_names) ) {
                    echo esc_html( implode(', ', $product_names) );
                  } else {
                    echo 'Narudžbina';
                  }
                } else {
                  echo 'Narudžbina';
                }
                ?>
              </span>
              <span class="cc-subtotal-amount"><?php echo function_exists( 'WC' ) ? wp_kses_post( WC()->cart->get_cart_subtotal() ) : '—'; ?></span>
            </div>
            <div class="cc-summary__total">
              <span>Ukupno za plaćanje</span>
              <span class="cc-total-amount"><?php echo function_exists( 'WC' ) ? wp_kses_post( WC()->cart->get_total() ) : '—'; ?></span>
            </div>

            <form method="post" class="cc-coupon-form">
              <label for="cc_coupon_code">Kupon</label>
              <div class="cc-coupon-row">
                <input type="text" name="cc_coupon_code" id="cc_coupon_code" placeholder="Unesite kupon" />
                <button type="submit" name="cc_apply_coupon" value="1" class="cc-btn cc-btn-primary">Primeni</button>
              </div>
            </form>
            <?php if ( function_exists( 'wc_print_notices' ) ) { wc_print_notices(); } ?>
          </div>
        </div>

        <!-- Participants section - uses form attribute to link inputs to main form -->
        <div class="cc-summary__card cc-participants cc-hidden" id="cc-participants-wrapper">
          <h3>Polaznici</h3>
          <div id="cc-participants-list"></div>
          <div class="cc-actions cc-actions-participants">
            <button type="button" class="cc-btn cc-btn-add" id="cc-add-participant" title="Dodaj polaznika">
              <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M8 1V15M1 8H15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
              </svg>
              <span>Dodaj polaznika</span>
            </button>
          </div>
          <button type="button" class="cc-btn cc-btn-success cc-btn-save-participants-bottom" id="cc-save-participants" style="display: none;">Sačuvaj izmene</button>
        </div>

        <div class="cc-summary__card cc-payments" id="cc-payments">
          <h3>Način plaćanja</h3>
          <div class="cc-payments__list">
            <?php
            $gateways = [];
            if ( function_exists( 'WC' ) ) {
              $pg = WC()->payment_gateways();
              if ( $pg ) {
                $gateways = $pg->get_available_payment_gateways();
              }
            }
            if ( ! empty( $gateways ) ) :
            ?>
              <ul class="cc-payments__ul">
                <?php $first = true; foreach ( $gateways as $gateway_id => $gateway ) : ?>
                  <li class="cc-pay-option" data-gateway="<?php echo esc_attr( $gateway_id ); ?>">
                    <input type="radio" id="cc_pm_<?php echo esc_attr( $gateway_id ); ?>" name="cc_payment_method" value="<?php echo esc_attr( $gateway_id ); ?>" <?php checked( $first ); $first = false; ?> />
                    <label for="cc_pm_<?php echo esc_attr( $gateway_id ); ?>">
                      <span class="cc-pay-option__title"><?php echo esc_html( $gateway->get_title() ); ?></span>
                      <?php $desc = $gateway->get_description(); if ( $desc ) : ?>
                        <span class="cc-pay-option__desc"><?php echo wp_kses_post( $desc ); ?></span>
                      <?php endif; ?>
                    </label>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php else : ?>
              <p>Nema dostupnih metoda plaćanja.</p>
            <?php endif; ?>
          </div>
          <div class="cc-actions cc-order-actions">
            <button type="submit" class="cc-btn cc-btn-primary" id="cc-place-order" form="cc-form">Naruči</button>
          </div>
        </div>
      </aside>
    </div>

    <!-- Mount point (optional, for future dynamic UI) -->
    <div id="custom-checkout-app" class="custom-checkout__app" hidden></div>
  </div>

  <?php
  // Contact info section
  $terms_url   = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('terms') : '';
  $privacy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
  ?>

  <div class="container custom-checkout__container cc-legal">
    <div class="cc-card cc-legal__card">
      <div class="cc-legal__header">
        <h3>Kontakt podaci</h3>
        <div class="cc-legal__links">
          <?php if ( $terms_url ) : ?>
            <a href="<?php echo esc_url( $terms_url ); ?>">Opšta pravila pružanja usluga</a>
          <?php endif; ?>
          <?php if ( $privacy_url ) : ?>
            <a href="<?php echo esc_url( $privacy_url ); ?>">Politika privatnosti i politika kolačića</a>
          <?php endif; ?>
        </div>
      </div>
      <ul class="cc-legal__list">
        <li><strong>Softuni doo Beograd</strong></li>
        <li>Adresa: Pivljanina Baja 1, 11000 Beograd,(Savski venac), Srbija</li>
        <li>Telefon: +381602823118</li>
        <li>E-mail: <a href="mailto:office@softuni.rs">office@softuni.rs</a>, <a href="mailto:studentskasluzba@softuni.rs">studentskasluzba@softuni.rs</a></li>
        <li>delatnost i šifra delatnosti: 8559 – Ostalo obrazovanje</li>
        <li>matični broj: 21848891</li>
        <li>poreski broj: 113341376</li>
        <li>web adresa: <a href="https://www.softuni.rs" target="_blank" rel="noopener">www.softuni.rs</a></li>
      </ul>
    </div>
  </div>

  <!-- Custom Footer -->
  <footer class="softuni-custom-footer">
    <div class="footer-content">
      <div class="footer-left">
        Copyright © 2025 SOFTUNI
      </div>
      <div class="footer-right">
        <a href="<?php echo esc_url( home_url('/politika-privatnosti/') ); ?>">Politika privatnosti</a>
        <a href="<?php echo esc_url( home_url('/opsta-pravila/') ); ?>">Opšta pravila</a>
      </div>
    </div>
  </footer>

</main>

<?php wp_footer(); ?>
</body>
</html>
