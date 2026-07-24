<script>(function(){try{var t=localStorage.getItem('vtx-theme');if(!t){var l=localStorage.getItem('phuse-theme');if(l){localStorage.setItem('vtx-theme',l);localStorage.removeItem('phuse-theme');t=l;}}if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})()</script>
<?php if (!empty($site['ga_measurement_id'])): ?>
<script src="<?php echo $baseUrl ?? ''; ?>/assets/js/ga.js" data-ga-id="<?php echo htmlspecialchars($site['ga_measurement_id']); ?>"></script>
<?php endif; ?>
