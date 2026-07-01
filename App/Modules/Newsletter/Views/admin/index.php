<?php
// Redirect /admin/newsletter to subscribers list
header('Location: ' . ($baseUrl ?? '') . '/admin/newsletter/subscribers');
exit;
