(function ($) {
  function fieldSelector(gatewayId, key) {
    return '#woocommerce_' + gatewayId + '_' + key;
  }

  function syncDependentFields(gatewayId, checkboxKey, fieldKeys) {
    var $checkbox = $(fieldSelector(gatewayId, checkboxKey));
    if (!$checkbox.length || !fieldKeys || !fieldKeys.length) {
      return;
    }

    var selectors = $.map(fieldKeys, function (key) {
      return fieldSelector(gatewayId, key);
    }).join(',');
    var $rows = $(selectors).closest('tr');

    function sync() {
      $rows.toggle($checkbox.is(':checked'));
    }

    $checkbox.on('change', sync);
    sync();
  }

  $(function () {
    var config = window.plugnpaySs2Admin;
    if (!config || !config.gatewayId || !config.dependentFields) {
      return;
    }

    $.each(config.dependentFields, function (checkboxKey, fieldKeys) {
      syncDependentFields(config.gatewayId, checkboxKey, fieldKeys);
    });
  });
})(jQuery);
