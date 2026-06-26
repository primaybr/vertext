<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Under Maintenance</title>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:     #f8fafc;
      --card:   #ffffff;
      --text:   #0f172a;
      --muted:  #64748b;
      --border: #e2e8f0;
      --accent: #4f46e5;
    }

    @media (prefers-color-scheme: dark) {
      :root {
        --bg:     #0f172a;
        --card:   #1e293b;
        --text:   #f1f5f9;
        --muted:  #94a3b8;
        --border: #334155;
        --accent: #818cf8;
      }
    }

    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: var(--bg);
      font-family: system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
      color: var(--text);
      padding: 1.5rem;
    }

    .card {
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 3rem 2.5rem;
      max-width: 440px;
      width: 100%;
      text-align: center;
    }

    .icon {
      width: 56px;
      height: 56px;
      background: color-mix(in srgb, var(--accent) 12%, transparent);
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
    }

    .icon svg {
      width: 28px;
      height: 28px;
      stroke: var(--accent);
    }

    h1 {
      font-size: 1.375rem;
      font-weight: 700;
      letter-spacing: -.02em;
      margin-bottom: .625rem;
    }

    p {
      font-size: .9375rem;
      color: var(--muted);
      line-height: 1.6;
    }

    .divider {
      border: none;
      border-top: 1px solid var(--border);
      margin: 1.75rem 0;
    }

    .status {
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      font-size: .8125rem;
      color: var(--muted);
    }

    .status-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #f59e0b;
      flex-shrink: 0;
      animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50%       { opacity: .35; }
    }
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">
      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z"/>
      </svg>
    </div>

    <h1>Under Maintenance</h1>
    <p>We're performing scheduled maintenance and will be back shortly. Thank you for your patience.</p>

    <hr class="divider">

    <span class="status">
      <span class="status-dot"></span>
      Work in progress
    </span>
  </div>
</body>
</html>
