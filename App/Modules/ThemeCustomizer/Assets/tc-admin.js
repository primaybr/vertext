/* -- admin/theme-customizer/index.php: live-preview debounced update -- */
(function() {
  var colorPicker  = document.getElementById('primary_color_picker');
  var colorText    = document.getElementById('primary_color');
  var colorPreview = document.getElementById('primary_color_preview');
  var fontSelect   = document.getElementById('font_family');
  var cornerInputs = document.querySelectorAll('.tc-corner-input');
  var frame        = document.getElementById('tc-preview-frame');
  var status       = document.getElementById('tc-preview-status');
  if (!colorPicker || !colorText || !colorPreview || !fontSelect || !frame || !status) return; // Only present on admin/theme-customizer
  var previewUrl   = frame.dataset.previewUrl;

  function syncColorPicker(val) {
    if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
      colorPicker.value = val;
      colorPreview.style.background = val;
    }
  }

  var debounceTimer = null;
  function updatePreview() {
    status.textContent = 'Updating...';
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(function() {
      var params = new URLSearchParams({
        primary_color: colorText.value,
        font_family: fontSelect.value === 'system' ? '' : fontSelect.value,
        corner_style: document.querySelector('.tc-corner-input:checked').value
      });
      frame.src = previewUrl + '?' + params.toString();
    }, 350);
  }

  frame.addEventListener('load', function() { status.textContent = ''; });

  colorPicker.addEventListener('input', function() {
    colorText.value = this.value;
    syncColorPicker(this.value);
    updatePreview();
  });
  colorText.addEventListener('input', function() {
    syncColorPicker(this.value);
    updatePreview();
  });
  fontSelect.addEventListener('change', updatePreview);
  cornerInputs.forEach(function(el) { el.addEventListener('change', updatePreview); });
})();
