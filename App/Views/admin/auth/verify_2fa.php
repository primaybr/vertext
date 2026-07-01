<div class="vtx-auth-box">

  <div class="vtx-auth-top">
    <div class="vtx-auth-mark" style="background:var(--ps-primary);color:#fff;font-size:1.25rem;">
      <i class="pi pi-shield"></i>
    </div>
    <h1 class="vtx-auth-h1">Two-Factor Authentication</h1>
    <p class="vtx-auth-sub">Enter the 6-digit code from your authenticator app.</p>
  </div>

  <div class="vtx-auth-form">

    {% if flash.message %}
    <div class="vtx-flash {{flash.type}} vtx-flash-auto mb-3">
      <i class="pi pi-x-circle"></i> {{flash.message}}
    </div>
    {% endif %}

    <form method="POST" action="{{baseUrl}}/admin/login/2fa" autocomplete="off">
      <input type="hidden" name="csrf_token" value="{{csrf_token}}">

      <div class="vtx-field mb-3">
        <label class="vtx-label" for="code">Authentication Code</label>
        <input class="form-control" type="text" id="code" name="code"
               inputmode="numeric" pattern="\d{5,6}|\d{5}-\d{5}"
               maxlength="11" placeholder="000000"
               autocomplete="one-time-code" autofocus
               style="font-size:1.25rem;letter-spacing:.2em;text-align:center;">
        <div class="vtx-field-hint">Enter the 6-digit code or a backup code (XXXXX-XXXXX).</div>
      </div>

      <button type="submit" class="btn btn-primary w-100 mt-1">
        <i class="pi pi-arrow-right me-1"></i> Verify
      </button>
    </form>

    <div style="text-align:center;margin-top:1.25rem;">
      <a href="{{baseUrl}}/admin/login"
         style="font-size:.875rem;color:var(--ps-text-muted);">
        &larr; Back to login
      </a>
    </div>

  </div>
</div>
