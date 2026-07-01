<style>
.vf-page { padding: 3rem 0; }
.vf-card { background: var(--clr-surface); border: 1px solid var(--clr-border); border-radius: 8px; padding: 2rem; }
.vf-card h1 { margin-bottom: .5rem; font-size: 1.625rem; }
.vf-card p.desc { color: var(--clr-text-muted, #6b7280); margin-bottom: 1.5rem; }
.vf-field { margin-bottom: 1.25rem; }
.vf-field label { display: block; font-size: .875rem; font-weight: 600; margin-bottom: .35rem; }
.vf-field input[type="text"],
.vf-field input[type="email"],
.vf-field input[type="number"],
.vf-field input[type="date"],
.vf-field input[type="file"],
.vf-field textarea,
.vf-field select {
  width: 100%; padding: .6rem .85rem; border: 1px solid var(--clr-border); border-radius: 6px;
  font-size: 1rem; font-family: inherit; background: var(--clr-bg); color: var(--clr-text);
  box-sizing: border-box;
}
.vf-field input:focus, .vf-field textarea:focus, .vf-field select:focus {
  outline: none; border-color: var(--clr-accent); box-shadow: 0 0 0 3px rgba(79,70,229,.15);
}
.vf-field .vf-options { display: flex; flex-direction: column; gap: .4rem; }
.vf-field .vf-options label { display: flex; gap: .5rem; align-items: center; font-weight: 400; cursor: pointer; }
.vf-row-half { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.vf-required { color: #e53e3e; }
.vf-submit { background: var(--clr-accent); color: #fff; border: none; border-radius: 6px; padding: .65rem 1.75rem; font-size: 1rem; font-weight: 600; cursor: pointer; }
.vf-submit:hover { opacity: .88; }
.vf-alert { padding: .9rem 1.25rem; border-radius: 6px; margin-bottom: 1.5rem; font-size: .95rem; }
.vf-alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
.vf-alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
[data-theme="dark"] .vf-alert-success { background: rgba(16,185,129,.15); color: #6ee7b7; border-color: rgba(110,231,183,.3); }
[data-theme="dark"] .vf-alert-error   { background: rgba(239,68,68,.15);  color: #fca5a5; border-color: rgba(252,165,165,.3); }
@media (max-width: 540px) { .vf-row-half { grid-template-columns: 1fr; } }
</style>

<div class="container vf-page">
  <div style="max-width:680px;margin:0 auto;">
    <div class="vf-card">
      <h1><?php echo htmlspecialchars($form['name']); ?></h1>
      <?php if (!empty($form['description'])): ?>
      <p class="desc"><?php echo htmlspecialchars($form['description']); ?></p>
      <?php endif; ?>

      <?php if (!empty($flash['message'])): ?>
      <div class="vf-alert vf-alert-<?php echo $flash['type'] === 'success' ? 'success' : 'error'; ?>">
        <?php echo htmlspecialchars($flash['message']); ?>
      </div>
      <?php endif; ?>

      <?php if (empty($flash) || $flash['type'] !== 'success'): ?>
      <form method="POST" action="<?php echo $baseUrl; ?>/forms/<?php echo htmlspecialchars($form['slug']); ?>/submit"
            enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
        <!-- Honeypot -->
        <div style="position:absolute;left:-9999px;visibility:hidden;" aria-hidden="true">
          <input type="text" name="website" tabindex="-1" autocomplete="off">
        </div>

        <?php
        $i = 0;
        $fieldCount = count($fields);
        while ($i < $fieldCount):
          $field = $fields[$i];
          $type  = $field['type'] ?? 'text';
          $id    = 'vf_' . htmlspecialchars($field['id']);
          $name  = htmlspecialchars($field['id']);
          $label = htmlspecialchars($field['label'] ?? $field['id']);
          $req   = !empty($field['required']);
          $ph    = htmlspecialchars($field['placeholder'] ?? '');
          $opts  = $field['options'] ?? [];
          $width = $field['width'] ?? 'full';

          // Check if next field can pair as half-width
          $pairWithNext = ($width === 'half' && isset($fields[$i + 1]) && ($fields[$i + 1]['width'] ?? 'full') === 'half');
          if ($pairWithNext) echo '<div class="vf-row-half">';
        ?>
        <div class="vf-field">
          <?php if (!in_array($type, ['checkbox'])): ?>
          <label for="<?php echo $id; ?>"><?php echo $label; ?><?php if ($req) echo ' <span class="vf-required">*</span>'; ?></label>
          <?php endif; ?>

          <?php if ($type === 'textarea'): ?>
          <textarea id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                    rows="4" placeholder="<?php echo $ph; ?>"
                    <?php echo $req ? 'required' : ''; ?>></textarea>

          <?php elseif ($type === 'select'): ?>
          <select id="<?php echo $id; ?>" name="<?php echo $name; ?>" <?php echo $req ? 'required' : ''; ?>>
            <option value="">-- Select --</option>
            <?php foreach ($opts as $opt): ?>
            <option value="<?php echo htmlspecialchars($opt); ?>"><?php echo htmlspecialchars($opt); ?></option>
            <?php endforeach; ?>
          </select>

          <?php elseif ($type === 'radio'): ?>
          <div class="vf-options">
            <?php foreach ($opts as $oi => $opt): ?>
            <label>
              <input type="radio" name="<?php echo $name; ?>"
                     value="<?php echo htmlspecialchars($opt); ?>"
                     <?php echo ($req && $oi === 0) ? 'required' : ''; ?>>
              <?php echo htmlspecialchars($opt); ?>
            </label>
            <?php endforeach; ?>
          </div>

          <?php elseif ($type === 'checkbox'): ?>
          <div class="vf-options">
            <?php foreach ($opts as $opt): ?>
            <label>
              <input type="checkbox" name="<?php echo $name; ?>[]"
                     value="<?php echo htmlspecialchars($opt); ?>">
              <?php echo htmlspecialchars($opt); ?>
            </label>
            <?php endforeach; ?>
            <?php if (empty($opts)): ?>
            <label>
              <input type="checkbox" name="<?php echo $name; ?>" value="1"
                     <?php echo $req ? 'required' : ''; ?>>
              <?php echo $label; ?>
            </label>
            <?php endif; ?>
          </div>

          <?php elseif ($type === 'file'): ?>
          <input type="file" id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                 <?php echo $req ? 'required' : ''; ?>>

          <?php else: ?>
          <input type="<?php echo in_array($type, ['email','number','date']) ? $type : 'text'; ?>"
                 id="<?php echo $id; ?>" name="<?php echo $name; ?>"
                 placeholder="<?php echo $ph; ?>"
                 <?php echo $req ? 'required' : ''; ?>>
          <?php endif; ?>
        </div>

        <?php
          if ($pairWithNext) {
            $i++;
            $field2 = $fields[$i];
            $type2  = $field2['type'] ?? 'text';
            $id2    = 'vf_' . htmlspecialchars($field2['id']);
            $name2  = htmlspecialchars($field2['id']);
            $label2 = htmlspecialchars($field2['label'] ?? $field2['id']);
            $req2   = !empty($field2['required']);
            $ph2    = htmlspecialchars($field2['placeholder'] ?? '');
        ?>
        <div class="vf-field">
          <label for="<?php echo $id2; ?>"><?php echo $label2; ?><?php if ($req2) echo ' <span class="vf-required">*</span>'; ?></label>
          <input type="<?php echo in_array($type2, ['email','number','date']) ? $type2 : 'text'; ?>"
                 id="<?php echo $id2; ?>" name="<?php echo $name2; ?>"
                 placeholder="<?php echo $ph2; ?>"
                 <?php echo $req2 ? 'required' : ''; ?>>
        </div>
        <?php
          }
          if ($pairWithNext) echo '</div>';
          $i++;
        endwhile;
        ?>

        <div style="margin-top:1.5rem;">
          <button type="submit" class="vf-submit">Submit</button>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
