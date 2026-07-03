<style>
/* Members module - front-end account pages
   Flat, simple, professional. Uses theme --clr-* variables; no gradients. */

.mb-auth-wrap {
  max-width: 420px;
  margin: 0 auto;
  padding: 3rem 1rem 4rem;
}

.mb-card {
  background: var(--clr-surface, #fff);
  border: 1px solid var(--clr-border, #e5e7eb);
  border-radius: 8px;
  padding: 1.75rem;
}

.mb-title {
  font-size: 1.5rem;
  font-weight: 700;
  margin: 0 0 .25rem;
  color: var(--clr-text, #111827);
}

.mb-sub {
  font-size: .9rem;
  color: var(--clr-text-muted, var(--clr-muted, #6b7280));
  margin: 0 0 1.5rem;
}

.mb-field { margin-bottom: 1rem; }

.mb-label {
  display: block;
  font-size: .8125rem;
  font-weight: 600;
  margin-bottom: .35rem;
  color: var(--clr-text, #111827);
}

.mb-input {
  width: 100%;
  padding: .55rem .75rem;
  font-size: .9rem;
  border: 1px solid var(--clr-border, #d1d5db);
  border-radius: 6px;
  background: var(--clr-bg, #fff);
  color: var(--clr-text, #111827);
  box-sizing: border-box;
}
.mb-input:focus {
  outline: 2px solid var(--clr-accent, #4f46e5);
  outline-offset: 1px;
  border-color: var(--clr-accent, #4f46e5);
}

.mb-btn {
  display: inline-block;
  width: 100%;
  padding: .6rem 1rem;
  font-size: .9rem;
  font-weight: 600;
  color: #fff;
  background: var(--clr-accent, #4f46e5);
  border: none;
  border-radius: 6px;
  cursor: pointer;
  text-align: center;
}
.mb-btn:hover { opacity: .92; }

.mb-btn-outline {
  display: inline-block;
  padding: .5rem 1rem;
  font-size: .85rem;
  font-weight: 600;
  color: var(--clr-accent, #4f46e5);
  background: transparent;
  border: 1px solid var(--clr-accent, #4f46e5);
  border-radius: 6px;
  cursor: pointer;
  text-decoration: none;
}

.mb-alt {
  text-align: center;
  font-size: .85rem;
  margin-top: 1.25rem;
  color: var(--clr-text-muted, var(--clr-muted, #6b7280));
}
.mb-alt a { color: var(--clr-accent, #4f46e5); }

.mb-alert {
  padding: .65rem .9rem;
  border-radius: 6px;
  font-size: .85rem;
  margin-bottom: 1.1rem;
  border: 1px solid transparent;
}
.mb-alert-success { background: #ecfdf5; color: #065f46; border-color: #a7f3d0; }
.mb-alert-error   { background: #fef2f2; color: #991b1b; border-color: #fecaca; }
.mb-alert-warning { background: #fffbeb; color: #92400e; border-color: #fde68a; }
.mb-alert-info    { background: #eff6ff; color: #1e40af; border-color: #bfdbfe; }

[data-theme="dark"] .mb-alert-success { background: #064e3b; color: #a7f3d0; border-color: #065f46; }
[data-theme="dark"] .mb-alert-error   { background: #7f1d1d; color: #fecaca; border-color: #991b1b; }
[data-theme="dark"] .mb-alert-warning { background: #78350f; color: #fde68a; border-color: #92400e; }
[data-theme="dark"] .mb-alert-info    { background: #1e3a8a; color: #bfdbfe; border-color: #1e40af; }

@media (prefers-color-scheme: dark) {
  :root:not([data-theme="light"]) .mb-alert-success { background: #064e3b; color: #a7f3d0; border-color: #065f46; }
  :root:not([data-theme="light"]) .mb-alert-error   { background: #7f1d1d; color: #fecaca; border-color: #991b1b; }
  :root:not([data-theme="light"]) .mb-alert-warning { background: #78350f; color: #fde68a; border-color: #92400e; }
  :root:not([data-theme="light"]) .mb-alert-info    { background: #1e3a8a; color: #bfdbfe; border-color: #1e40af; }
}

/* Account page */
.mb-account-wrap {
  max-width: 640px;
  margin: 0 auto;
  padding: 3rem 1rem 4rem;
}
.mb-account-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  flex-wrap: wrap;
  margin-bottom: 1.5rem;
}
.mb-meta {
  font-size: .8125rem;
  color: var(--clr-text-muted, var(--clr-muted, #6b7280));
  margin-top: .35rem;
}
.mb-divider {
  border: none;
  border-top: 1px solid var(--clr-border, #e5e7eb);
  margin: 1.5rem 0;
}
.mb-section-title {
  font-size: 1rem;
  font-weight: 700;
  margin: 0 0 .75rem;
  color: var(--clr-text, #111827);
}
</style>
