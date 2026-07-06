<?php
/**
 * Embeddable form partial - used by the /forms/{slug} page AND the
 * [form slug="..."] shortcode. Expects: $form, $fields, $flash, $baseUrl,
 * $csrf_token, optional $member (logged-in site user), $mathQuestion.
 */
$vfSettings = json_decode($form['settings'] ?: '{}', true) ?: [];
$vfHasSteps = false;
foreach ($fields as $vfF) {
    if (($vfF['type'] ?? '') === 'step') { $vfHasSteps = true; break; }
}
$vfUid = 'vf-' . htmlspecialchars($form['slug']);
$vfRecaptchaKey = htmlspecialchars($vfSettings['recaptcha_site_key'] ?? '');
?>
<div class="vf-card" id="<?php echo $vfUid; ?>" data-form-uid="<?php echo $vfUid; ?>" data-recaptcha-key="<?php echo $vfRecaptchaKey; ?>">
  <h1><?php echo htmlspecialchars($form['name']); ?></h1>
  <?php if (!empty($form['description'])): ?>
  <p class="desc"><?php echo htmlspecialchars($form['description']); ?></p>
  <?php endif; ?>

  <?php if (!empty($flash['message'])): ?>
  <div class="vf-alert vf-alert-<?php echo ($flash['type'] ?? '') === 'success' ? 'success' : 'error'; ?>">
    <?php echo htmlspecialchars($flash['message']); ?>
  </div>
  <?php endif; ?>

  <?php if (empty($flash) || ($flash['type'] ?? '') !== 'success'): ?>
  <form method="POST" action="<?php echo $baseUrl; ?>/forms/<?php echo htmlspecialchars($form['slug']); ?>/submit"
        enctype="multipart/form-data" data-vf-form>
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token ?? ''); ?>">
    <!-- Honeypot -->
    <div class="vf-honeypot" aria-hidden="true">
      <input type="text" name="website" tabindex="-1" autocomplete="off">
    </div>

    <?php if ($vfHasSteps): ?>
    <div class="vf-progress" data-vf-progress></div>
    <?php endif; ?>

    <?php
    // Group fields into steps at 'step' markers (single step when none)
    $vfSteps = [['title' => '', 'fields' => []]];
    foreach ($fields as $vfField) {
        if (($vfField['type'] ?? '') === 'step') {
            $vfSteps[] = ['title' => $vfField['label'] ?? '', 'fields' => []];
            continue;
        }
        $vfSteps[count($vfSteps) - 1]['fields'][] = $vfField;
    }
    // Drop a leading empty step (form starts with a step break)
    if (count($vfSteps) > 1 && empty($vfSteps[0]['fields'])) {
        array_shift($vfSteps);
    }

    foreach ($vfSteps as $stepIdx => $step):
    ?>
    <div class="vf-step<?php echo $stepIdx === 0 ? ' active' : ''; ?>" data-vf-step="<?php echo $stepIdx; ?>"
         data-vf-step-title="<?php echo htmlspecialchars($step['title'] ?: 'Step ' . ($stepIdx + 1)); ?>">
      <div class="vf-grid">
      <?php foreach ($step['fields'] as $field):
        $type  = $field['type'] ?? 'text';
        $id    = 'vf_' . htmlspecialchars($field['id']);
        $name  = htmlspecialchars($field['id']);
        $label = htmlspecialchars($field['label'] ?? $field['id']);
        $req   = !empty($field['required']);
        $ph    = htmlspecialchars($field['placeholder'] ?? '');
        $opts  = $field['options'] ?? [];
        $width = ($field['width'] ?? 'full') === 'half' ? 'half' : 'full';

        // Member prefill: email fields get the member email, a field with id
        // "name" gets the member name (only when no sticky old input exists)
        $prefill = '';
        if (!empty($member)) {
            if ($type === 'email')            $prefill = (string) ($member['email'] ?? '');
            elseif ($field['id'] === 'name')  $prefill = (string) ($member['name'] ?? '');
        }
      ?>
      <div class="vf-field vf-col-<?php echo $width; ?>" data-vf-field="<?php echo $name; ?>"
           <?php if (!empty($field['conditions'])): ?>data-vf-conditions='<?php echo htmlspecialchars(json_encode($field['conditions']), ENT_QUOTES); ?>'<?php endif; ?>>
        <?php if ($type !== 'checkbox'): ?>
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
               <?php if ($prefill !== ''): ?>value="<?php echo htmlspecialchars($prefill); ?>"<?php endif; ?>
               <?php echo $req ? 'required' : ''; ?>>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <?php if (!empty($mathQuestion)): ?>
    <div class="vf-field">
      <label for="<?php echo $vfUid; ?>-math">
        Spam check: what is <?php echo htmlspecialchars($mathQuestion); ?>? <span class="vf-required">*</span>
      </label>
      <input type="number" id="<?php echo $vfUid; ?>-math" name="math_answer" required
             class="vf-math-input" inputmode="numeric">
    </div>
    <?php endif; ?>

    <?php if (!empty($vfSettings['recaptcha_site_key']) && !empty($vfSettings['recaptcha_secret_key'])): ?>
    <input type="hidden" name="recaptcha_token" data-vf-recaptcha>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($vfSettings['recaptcha_site_key']); ?>" async defer></script>
    <?php endif; ?>

    <?php if ($vfHasSteps): ?>
    <div class="vf-nav">
      <button type="button" class="vf-nav-btn vf-back" data-vf-back style="visibility:hidden;">Back</button>
      <button type="button" class="vf-nav-btn" data-vf-next>Next</button>
      <button type="submit" class="vf-submit" data-vf-submit style="display:none;">Submit</button>
    </div>
    <?php else: ?>
    <div class="vf-submit-wrap">
      <button type="submit" class="vf-submit">Submit</button>
    </div>
    <?php endif; ?>
  </form>
  <?php endif; ?>
</div>
