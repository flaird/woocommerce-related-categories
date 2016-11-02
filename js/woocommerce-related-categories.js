jQuery(function($) {

  $(document).ready(function() {
    $("#ralcp-select").select2({
      minimumResultsForSearch: -1,
    });

    $('#ralcp-select').on('select2-selecting', function(e) {
      selectedColor = $(this).find(":selected").data('color');
      alert(selectedColor);
      $('.select2-container').css('background-color', selectedColor);

    });
  });
});
