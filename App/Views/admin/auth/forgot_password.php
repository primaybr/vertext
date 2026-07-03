<div class="vtx-auth-box">

  <div class="vtx-auth-top">
    <div class="vtx-auth-mark">V</div>
    <h1 class="vtx-auth-h1">Forgot Password</h1>
    <p class="vtx-auth-sub">Enter your email and we will send you a reset link</p>
  </div>

  <div class="vtx-auth-form">

    {% if flash.message %}
    <div class="vtx-flash {{flash.type}} vtx-flash-auto mb-3">
      {% if flash.type == 'error' %}<i class="pi pi-x-circle"></i>{% else %}<i class="pi pi-info"></i>{% endif %}
      {{flash.message}}
    </div>
    {% endif %}

    <form method="POST" action="{{baseUrl}}/admin/forgot-password" autocomplete="on">
      <input type="hidden" name="csrf_token" value="{{csrf_token}}">

      <div class="vtx-field">
        <label class="vtx-label" for="email">Email Address</label>
        <input class="form-control" type="email" id="email" name="email"
               placeholder="admin@example.com" required autocomplete="email" autofocus>
      </div>

      <button type="submit" class="btn btn-primary w-100 mt-2">
        <i class="pi pi-send me-1"></i> Send Reset Link
      </button>
    </form>

    <div style="text-align:center;margin-top:1.25rem;">
      <a href="{{baseUrl}}/admin/login" style="font-size:.85rem;">
        <i class="pi pi-arrow-left me-1"></i> Back to sign in
      </a>
    </div>

  </div>
</div>
