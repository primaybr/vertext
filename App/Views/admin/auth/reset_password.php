<div class="vtx-auth-box">

  <div class="vtx-auth-top">
    <div class="vtx-auth-mark"><img src="{{assetsUrl}}images/logo/logo-dark.svg" alt="" style="width:55%;height:auto;"></div>
    <h1 class="vtx-auth-h1">Reset Password</h1>
    <p class="vtx-auth-sub">Choose a new password for your account</p>
  </div>

  <div class="vtx-auth-form">

    {% if flash.message %}
    <div class="vtx-flash {{flash.type}} vtx-flash-auto mb-3">
      {% if flash.type == 'error' %}<i class="pi pi-x-circle"></i>{% else %}<i class="pi pi-info"></i>{% endif %}
      {{flash.message}}
    </div>
    {% endif %}

    <form method="POST" action="{{baseUrl}}/admin/reset-password" autocomplete="on">
      <input type="hidden" name="csrf_token" value="{{csrf_token}}">
      <input type="hidden" name="reset_token" value="{{reset_token}}">

      <div class="vtx-field">
        <label class="vtx-label" for="password">New Password</label>
        <div style="position:relative;">
          <input class="form-control" type="password" id="password" name="password"
                 placeholder="Minimum 8 characters" required autocomplete="new-password" autofocus>
          <button type="button" class="vtx-icon-btn"
                  data-pw-toggle="password"
                  style="position:absolute;right:6px;top:50%;transform:translateY(-50%);border:none;background:none;">
            <i class="pi pi-eye-off"></i>
          </button>
        </div>
      </div>

      <div class="vtx-field">
        <label class="vtx-label" for="password_confirm">Confirm New Password</label>
        <div style="position:relative;">
          <input class="form-control" type="password" id="password_confirm" name="password_confirm"
                 placeholder="Repeat new password" required autocomplete="new-password">
          <button type="button" class="vtx-icon-btn"
                  data-pw-toggle="password_confirm"
                  style="position:absolute;right:6px;top:50%;transform:translateY(-50%);border:none;background:none;">
            <i class="pi pi-eye-off"></i>
          </button>
        </div>
      </div>

      <button type="submit" class="btn btn-primary w-100 mt-2">
        <i class="pi pi-check me-1"></i> Update Password
      </button>
    </form>

    <div style="text-align:center;margin-top:1.25rem;">
      <a href="{{baseUrl}}/admin/login" style="font-size:.85rem;">
        <i class="pi pi-arrow-left me-1"></i> Back to sign in
      </a>
    </div>

  </div>
</div>
