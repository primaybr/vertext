<div class="vtx-auth-box">

  <div class="vtx-auth-top">
    <div class="vtx-auth-mark">V</div>
    <h1 class="vtx-auth-h1">Vertext CMS</h1>
    <p class="vtx-auth-sub">Sign in to the admin panel</p>
  </div>

  <div class="vtx-auth-form">

    {% if flash.message %}
    <div class="vtx-flash {{flash.type}} vtx-flash-auto mb-3">
      {% if flash.type == 'error' %}<i class="pi pi-x-circle"></i>{% else %}<i class="pi pi-info"></i>{% endif %}
      {{flash.message}}
    </div>
    {% endif %}

    <form method="POST" action="{{baseUrl}}/admin/login" autocomplete="on">
      <input type="hidden" name="csrf_token" value="{{csrf_token}}">

      <div class="vtx-field">
        <label class="vtx-label" for="email">Email Address</label>
        <input class="form-control" type="email" id="email" name="email"
               placeholder="admin@example.com" required autocomplete="email" autofocus>
      </div>

      <div class="vtx-field">
        <label class="vtx-label" for="password">Password</label>
        <div style="position:relative;">
          <input class="form-control" type="password" id="password" name="password"
                 placeholder="Your password" required autocomplete="current-password">
          <button type="button" class="vtx-icon-btn"
                  data-pw-toggle="password"
                  style="position:absolute;right:6px;top:50%;transform:translateY(-50%);border:none;background:none;">
            <i class="pi pi-eye-off"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 mt-2">
        <i class="pi pi-arrow-right me-1"></i> Sign In
      </button>
    </form>

    <div style="text-align:center;margin-top:1.25rem;">
      <button id="theme-toggle" class="vtx-icon-btn" type="button" title="Toggle theme" style="margin:0 auto;">
        <i id="theme-icon" class="pi pi-moon"></i>
      </button>
    </div>

  </div>
</div>
