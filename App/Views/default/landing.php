<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{siteName}}</title>
  <link rel="stylesheet" href="{{assetsUrl}}css/styles.css">
  <link rel="stylesheet" href="{{assetsUrl}}css/landing.css">
  <?php include ROOT . 'App' . DS . 'Views' . DS . '_shared' . DS . 'theme-init.php'; ?>
</head>
<body>

<div class="vtx-landing">
  <div class="vtx-landing-card">

    <div class="vtx-landing-badge">
      <i class="pi pi-verified me-1"></i>Running
    </div>

    <h1 class="display-5 fw-bold mb-2">{{siteName}}</h1>

    {% if siteDescription %}
    <p class="lead text-secondary mb-4">{{siteDescription}}</p>
    {% else %}
    <p class="lead text-secondary mb-4">Welcome to your new site.</p>
    {% endif %}

    <div class="d-flex flex-wrap justify-content-center gap-2">
      <a href="{{baseUrl}}/admin" class="btn btn-primary px-4">
        <i class="pi pi-lock me-2"></i>Admin Panel
      </a>
    </div>

    <div class="vtx-landing-footer">
      Powered by <a href="https://github.com/primaybr/phuse" target="_blank" rel="noopener">Vertext CMS</a>
    </div>

  </div>
</div>

</body>
</html>
