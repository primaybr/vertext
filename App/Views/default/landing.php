<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>{{siteName}}</title>
  <link rel="stylesheet" href="{{assetsUrl}}css/styles.css">
  <script>(function(){try{var t=localStorage.getItem('phuse-theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})()</script>
  <style>
    .vtx-landing {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 2rem;
    }
    .vtx-landing-card {
      max-width: 520px;
      width: 100%;
      text-align: center;
    }
    .vtx-landing-badge {
      display: inline-block;
      font-size: .75rem;
      font-weight: 600;
      letter-spacing: .06em;
      text-transform: uppercase;
      padding: .25rem .75rem;
      border-radius: 999px;
      background: var(--vtx-primary-soft, rgba(99,102,241,.1));
      color: var(--vtx-primary, #6366f1);
      margin-bottom: 1.25rem;
    }
    .vtx-landing-footer {
      margin-top: 3rem;
      font-size: .8125rem;
      color: var(--bs-secondary-color, #6c757d);
    }
    .vtx-landing-footer a {
      color: inherit;
      text-decoration: none;
      opacity: .75;
    }
    .vtx-landing-footer a:hover { opacity: 1; }
  </style>
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
