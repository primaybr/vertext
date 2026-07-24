// Google Analytics (gtag.js) bootstrap. Loaded only when an admin has set a
// Measurement ID (Admin > Settings > Analytics). The ID is read from this
// script tag's own data-ga-id attribute rather than inlined into a <script>
// body, so the page's CSP script-src can stay hash/self-only with no
// per-value hash to keep in sync.
(function () {
  var id = document.currentScript && document.currentScript.getAttribute('data-ga-id');
  if (!id) { return; }

  window.dataLayer = window.dataLayer || [];
  function gtag() { dataLayer.push(arguments); }
  window.gtag = gtag;
  gtag('js', new Date());
  gtag('config', id);

  var loader = document.createElement('script');
  loader.async = true;
  loader.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(id);
  document.head.appendChild(loader);
})();
