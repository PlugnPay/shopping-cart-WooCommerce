(function ($) {
  'use strict';

  var fieldRules = {
    pnp_cardnumber: {
      required: true,
      minLength: 13,
      maxLength: 20,
      luhn: true,
      i18nKey: 'cardNumberInvalid'
    },
    pnp_cardcvv: {
      required: true,
      minLength: 3,
      maxLength: 4,
      i18nKey: 'securityCodeInvalid'
    },
    pnp_token_cvv: {
      required: true,
      minLength: 3,
      maxLength: 4,
      i18nKey: 'securityCodeInvalid'
    },
    pnp_mpgiftcard: {
      required: false,
      minLength: 1,
      maxLength: 20,
      i18nKey: 'giftCardNumberInvalid'
    },
    pnp_mpcvv: {
      required: false,
      minLength: 3,
      maxLength: 4,
      i18nKey: 'giftCardSecurityCodeInvalid'
    }
  };

  function digitsOnly(value) {
    return (value || '').replace(/\D/g, '');
  }

  function isLuhn10(value) {
    var number = digitsOnly(value);
    var length = number.length;
    var sum = 0;
    var i;
    var digit;
    var subTotal;

    if (length < 13) {
      return false;
    }

    for (i = 0; i < length; i++) {
      digit = parseInt(number.charAt(length - i - 1), 10);
      if (i % 2 === 1) {
        subTotal = digit * 2;
        if (subTotal > 9) {
          subTotal = subTotal - 9;
        }
      } else {
        subTotal = digit;
      }
      sum += subTotal;
    }

    return sum > 0 && sum % 10 === 0;
  }

  function showCheckoutError(message) {
    var $notices = $('.woocommerce-NoticeGroup-checkout, form.checkout .woocommerce-notices-wrapper:first, form#add_payment_method .woocommerce-notices-wrapper:first, .woocommerce-NoticesWrapper:first');

    if (!$notices.length) {
      $notices = $('form.checkout, form#add_payment_method').first();
    }

    if (!$notices.length) {
      window.alert(message);
      return;
    }

    $notices.html(
      '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
        '<div class="woocommerce-error" role="alert">' + message + '</div>' +
      '</div>'
    );

    var offset = $notices.offset();
    if (offset) {
      $('html, body').animate({
        scrollTop: offset.top - 100
      }, 300);
    }

    $(document.body).trigger('checkout_error');
  }

  function clearFieldError($field) {
    $field.removeClass('plugnpay-cc-field-invalid');
    $field.removeAttr('aria-invalid');
  }

  function setFieldError($field) {
    $field.addClass('plugnpay-cc-field-invalid');
    $field.attr('aria-invalid', 'true');
  }

  function isDigitsOnly(value) {
    return /^\d+$/.test(value);
  }

  function validateField($field, rules, showNotice) {
    var value = $field.val();

    if (!rules.required && value === '') {
      clearFieldError($field);
      return true;
    }

    if (rules.required && value === '') {
      setFieldError($field);
      if (showNotice) {
        showCheckoutError(plugnpayApiCcTokenizationCheckout.i18n[rules.i18nKey]);
      }
      return false;
    }

    if (!isDigitsOnly(value)) {
      setFieldError($field);
      if (showNotice) {
        showCheckoutError(plugnpayApiCcTokenizationCheckout.i18n[rules.i18nKey]);
      }
      return false;
    }

    if (value.length < rules.minLength || value.length > rules.maxLength) {
      setFieldError($field);
      if (showNotice) {
        showCheckoutError(plugnpayApiCcTokenizationCheckout.i18n[rules.i18nKey]);
      }
      return false;
    }

    if (rules.luhn && !isLuhn10(value)) {
      setFieldError($field);
      if (showNotice) {
        showCheckoutError(plugnpayApiCcTokenizationCheckout.i18n[rules.i18nKey]);
      }
      return false;
    }

    clearFieldError($field);
    return true;
  }

  function bindNumericField(fieldId, rules) {
    var $field = $('#' + fieldId);

    if (!$field.length) {
      return;
    }

    $field.on('input', function () {
      var sanitized = digitsOnly(this.value);

      if (rules.maxLength) {
        sanitized = sanitized.slice(0, rules.maxLength);
      }

      this.value = sanitized;
      clearFieldError($(this));
    });

    $field.on('blur', function () {
      if ($(this).val() !== '') {
        validateField($(this), rules, false);
      }
    });
  }

  function isUsingNewCard() {
    var gatewayId = plugnpayApiCcTokenizationCheckout.gatewayId;
    var $selected = $('input[name="wc-' + gatewayId + '-payment-token"]:checked');

    if (!$selected.length) {
      return true;
    }

    return $selected.val() === 'new';
  }

  function isTokenCvvRequired() {
    return !!plugnpayApiCcTokenizationCheckout.requireTokenCvv;
  }

  function toggleNewCardFields() {
    var $fields = $('.plugnpay-cc-new-card-fields--collapsible');

    if (!$fields.length) {
      return;
    }

    if (isUsingNewCard()) {
      $fields.removeClass('plugnpay-cc-new-card-fields--hidden');
      $fields.find('input, select').prop('disabled', false);
    } else {
      $fields.addClass('plugnpay-cc-new-card-fields--hidden');
      $fields.find('input, select').prop('disabled', true);
    }
  }

  function toggleTokenCvvFields() {
    var $fields = $('.plugnpay-cc-token-cvv-fields');

    if (!$fields.length || !isTokenCvvRequired()) {
      return;
    }

    if (isUsingNewCard()) {
      $fields.addClass('plugnpay-cc-token-cvv-fields--hidden');
      $fields.find('input').prop('disabled', true);
    } else {
      $fields.removeClass('plugnpay-cc-token-cvv-fields--hidden');
      $fields.find('input').prop('disabled', false);
    }
  }

  function togglePaymentFields() {
    toggleNewCardFields();
    toggleTokenCvvFields();
  }

  function validateCheckoutFields(showNotice) {
    var isValid = true;

    if (!isUsingNewCard()) {
      if (isTokenCvvRequired()) {
        var $tokenCvv = $('#pnp_token_cvv');
        if ($tokenCvv.length && !$tokenCvv.prop('disabled')) {
          if (!validateField($tokenCvv, fieldRules.pnp_token_cvv, showNotice)) {
            isValid = false;
          }
        }
      }
      return isValid;
    }

    $.each(fieldRules, function (fieldId, rules) {
      var $field = $('#' + fieldId);

      if (!$field.length || $field.prop('disabled')) {
        return;
      }

      // Token CVV is only for saved-card path.
      if (fieldId === 'pnp_token_cvv') {
        return;
      }

      if (!validateField($field, rules, showNotice)) {
        isValid = false;
      }
    });

    return isValid;
  }

  $(function () {
    $.each(fieldRules, function (fieldId, rules) {
      bindNumericField(fieldId, rules);
    });

    togglePaymentFields();

    $(document.body).on(
      'change',
      'input[name="wc-' + plugnpayApiCcTokenizationCheckout.gatewayId + '-payment-token"]',
      togglePaymentFields
    );

    $(document.body).on(
      'checkout_place_order_' + plugnpayApiCcTokenizationCheckout.gatewayId,
      function () {
        return validateCheckoutFields(true);
      }
    );

    $('form#add_payment_method').on('submit', function () {
      return validateCheckoutFields(true);
    });
  });
}(jQuery));
