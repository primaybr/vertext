<form method="POST" action="{{baseUrl}}/admin/translations/add-locale" data-crud-form>
  <input type="hidden" name="csrf_token" value="{{csrf_token}}">

  <div class="vtx-field">
    <label class="vtx-label" for="alc-code">Locale Code <span class="req">*</span></label>
    <input class="form-control" type="text" id="alc-code" name="code"
           placeholder="e.g. fr, de, pt-br" required pattern="[a-z]{2}(-[a-z0-9]{2,8})?">
    <div class="form-text">Use lowercase letters, hyphen, and numbers only (e.g. "fr" for French, "pt-br" for Portuguese/Brazil).</div>
  </div>

  <div style="display:flex;justify-content:flex-end;gap:.5rem;
              padding-top:.875rem;margin-top:.875rem;border-top:1px solid var(--ps-border);">
    <button type="button" class="btn btn-outline-secondary btn-sm"
            onclick="window.vtxFormModalClose()">Cancel</button>
    <button type="submit" class="btn btn-primary btn-sm">
      <i class="pi pi-plus me-1"></i> Add Locale
    </button>
  </div>
</form>