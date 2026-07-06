<?php if (!isset($isAjax) || !$isAjax): ?>
    <!-- Page Header -->
    <div class="vtx-page-head">
      <div>
        <h1 class="vtx-page-title"><i class="pi pi-languages me-2 text-primary"></i>Translations</h1>
        <p class="vtx-page-desc">
          Edit interface strings per locale. English is the reference locale.
          Content (posts, pages) is translated per item via its Language field.
        </p>
      </div>
      <button type="button" class="btn btn-primary"
              data-form-url="{{baseUrl}}/admin/translations/add-locale"
              data-form-title="Add Locale">
        <i class="pi pi-plus me-1"></i> Add Locale
      </button>
    </div>
<?php endif; ?>

<!-- Locale / group picker -->
<div class="vtx-panel mb-3">
  <div class="vtx-panel-body" style="padding:.75rem 1rem;display:flex;gap:1rem;flex-wrap:wrap;align-items:center;">
    <form method="GET" action="{{baseUrl}}/admin/translations" style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
      <label style="font-size:.8125rem;font-weight:600;">Locale</label>
      <select class="form-select form-select-sm" name="locale" style="width:auto;" onchange="this.form.submit()">
        <?php foreach ($locales as $loc): ?>
        <option value="<?php echo htmlspecialchars($loc); ?>" <?php echo $loc === $locale ? 'selected' : ''; ?>>
          <?php echo strtoupper(htmlspecialchars($loc)); ?><?php echo $loc === 'en' ? ' (reference)' : ''; ?>
        </option>
        <?php endforeach; ?>
      </select>
      <label style="font-size:.8125rem;font-weight:600;">Group</label>
      <select class="form-select form-select-sm" name="group" style="width:auto;" onchange="this.form.submit()">
        <?php foreach ($groups as $grp): ?>
        <option value="<?php echo htmlspecialchars($grp); ?>" <?php echo $grp === $group ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($grp); ?>.php
        </option>
        <?php endforeach; ?>
      </select>
    </form>
    <span style="margin-left:auto;font-size:.8125rem;color:var(--ps-text-muted);">
      <?php echo count($keys); ?> string(s)
      <?php if (($missing ?? 0) > 0 && $locale !== 'en'): ?>
      &middot; <span class="vtx-tag warning"><?php echo (int) $missing; ?> untranslated</span>
      <?php endif; ?>
    </span>
  </div>
</div>

<form id="tr-form" data-csrf="{{csrf_token}}" data-locale="<?php echo htmlspecialchars($locale); ?>" data-group="<?php echo htmlspecialchars($group); ?>">
  <div class="vtx-panel">
    <?php if (empty($keys)): ?>
    <div class="vtx-empty">
      <div class="vtx-empty-ico"><i class="pi pi-languages"></i></div>
      <div class="vtx-empty-title">No strings in this group</div>
    </div>
    <?php else: ?>
    <div class="vtx-table-wrap">
      <table class="vtx-table">
        <thead>
          <tr>
            <th style="width:220px;">Key</th>
            <th>English (reference)</th>
            <th><?php echo strtoupper(htmlspecialchars($locale)); ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($keys as $key): ?>
          <tr>
            <td style="font-family:monospace;font-size:.75rem;color:var(--ps-text-muted);">
              <?php echo htmlspecialchars($key); ?>
              <input type="hidden" name="t_key[]" value="<?php echo htmlspecialchars($key); ?>">
            </td>
            <td style="font-size:.8125rem;"><?php echo htmlspecialchars($reference[$key] ?? ''); ?></td>
            <td>
              <input class="form-control form-control-sm" type="text" name="t_value[]"
                     value="<?php echo htmlspecialchars($target[$key] ?? ''); ?>"
                     <?php echo trim((string) ($target[$key] ?? '')) === '' && $locale !== 'en' ? 'style="border-color:var(--ps-warning,#d97706);"' : ''; ?>>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <div class="vtx-panel-body" style="display:flex;justify-content:flex-end;border-top:1px solid var(--ps-border);">
      <button type="submit" class="btn btn-primary">
        <i class="pi pi-save me-1"></i> Save Translations
      </button>
    </div>
    <?php endif; ?>
  </div>
</form>
