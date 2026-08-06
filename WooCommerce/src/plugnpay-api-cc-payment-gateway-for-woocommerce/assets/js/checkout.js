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
    var $notices = $('.woocommerce-NoticeGroup-checkout, form.checkout .woocommerce-notices-wrapper:first');

    if (!$notices.length) {
      $notices = $('form.checkout');
    }

    $notices.html(
      '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' +
        '<div class="woocommerce-error" role="alert">' + message + '</div>' +
      '</div>'
    );

    $('html, body').animate({
      scrollTop: $notices.offset().top - 100
    }, 300);

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
        showCheckoutError(plugnpayApiCcCheckout.i18n[rules.i18nKey]);
      }
      return false;
    }

    if (!isDigitsOnly(value)) {
      setFieldError($field);
      if (showNotice) {
        showCheckoutError(plugnpayApiCcCheckout.i18n[rules.i18nKey]);
      }
      return false;
    }

    if (value.length < rules.minLength || value.length > rules.maxLength) {
      setFieldError($field);
      if (showNotice) {
        showCheckoutError(plugnpayApiCcCheckout.i18n[rules.i18nKey]);
      }
      return false;
    }

    if (rules.luhn && !isLuhn10(value)) {
      setFieldError($field);
      if (showNotice) {
        showCheckoutError(plugnpayApiCcCheckout.i18n[rules.i18nKey]);
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

  function validateCheckoutFields(showNotice) {
    var isValid = true;

    $.each(fieldRules, function (fieldId, rules) {
      var $field = $('#' + fieldId);

      if (!$field.length) {
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

    $(document.body).on('checkout_place_order_plugnpay_api_cc', function () {
      return validateCheckoutFields(true);
    });
  });
}(jQuery));
