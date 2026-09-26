<?php
/**
 * @var string   $content
 * @var string   $title
 * @var string   $active
 * @var callable $e
 */
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($title) ?> · CDP</title>
    <style>
        :root {
            --bg: #f6f7f9; --panel: #fff; --text: #1d2330; --muted: #6b7280; --line: #e5e7eb;
            --accent: #4f46e5; --accent-soft: #eef2ff; --ok: #047857; --err: #b91c1c; --err-soft: #fef2f2;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0f1115; --panel: #171a21; --text: #e5e7eb; --muted: #9ca3af; --line: #2a2f3a;
                --accent: #818cf8; --accent-soft: #1e1b4b; --ok: #34d399; --err: #f87171; --err-soft: #2a1414;
            }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font: 14px/1.5 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); }
        header { background: var(--panel); border-bottom: 1px solid var(--line); }
        header .wrap { display: flex; align-items: center; gap: 24px; height: 56px; }
        header strong { font-size: 16px; }
        nav a { color: var(--muted); text-decoration: none; padding: 17px 2px; margin-right: 16px; border-bottom: 2px solid transparent; }
        nav a.active, nav a:hover { color: var(--text); border-bottom-color: var(--accent); }
        .wrap { max-width: 1100px; margin: 0 auto; padding: 0 16px; }
        main { padding: 24px 0 48px; }
        h1 { font-size: 22px; margin: 0 0 16px; }
        h2 { font-size: 16px; margin: 24px 0 8px; }
        a { color: var(--accent); }
        .panel { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 16px; }
        .table-wrap { overflow-x: auto; background: var(--panel); border: 1px solid var(--line); border-radius: 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px 12px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: .03em; color: var(--muted); font-weight: 600; }
        tr:last-child td { border-bottom: 0; }
        td.num, th.num { text-align: right; font-variant-numeric: tabular-nums; }
        .muted { color: var(--muted); }
        .badge { display: inline-block; padding: 1px 8px; border-radius: 999px; background: var(--accent-soft); color: var(--accent); font-size: 12px; }
        code, pre { font: 12px/1.5 ui-monospace, SFMono-Regular, Consolas, monospace; }
        pre { background: var(--bg); border: 1px solid var(--line); border-radius: 6px; padding: 12px; overflow-x: auto; margin: 0; }
        input, select, button { font: inherit; color: inherit; background: var(--panel); border: 1px solid var(--line); border-radius: 6px; padding: 6px 10px; }
        button, .button { cursor: pointer; background: var(--accent); color: #fff; border-color: var(--accent); text-decoration: none; display: inline-block; padding: 6px 14px; border-radius: 6px; }
        button.secondary { background: transparent; color: var(--text); border-color: var(--line); }
        .row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; }
        .stat { background: var(--panel); border: 1px solid var(--line); border-radius: 8px; padding: 12px 16px; }
        .stat b { display: block; font-size: 22px; font-variant-numeric: tabular-nums; }
        .pager { display: flex; justify-content: space-between; margin-top: 12px; }
        .errors { background: var(--err-soft); color: var(--err); border: 1px solid var(--err); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; }
        .errors ul { margin: 4px 0 0; padding-left: 18px; }
        .props { color: var(--muted); font-size: 12px; }
    </style>
</head>
<body>
<header>
    <div class="wrap">
        <strong>CDP</strong>
        <nav>
            <a href="/customers" class="<?= $active === 'customers' ? 'active' : '' ?>">Customers</a>
            <a href="/events" class="<?= $active === 'events' ? 'active' : '' ?>">Events</a>
            <a href="/segments" class="<?= $active === 'segments' ? 'active' : '' ?>">Segments</a>
        </nav>
    </div>
</header>
<main class="wrap">
    <?= $content ?>
</main>
</body>
</html>
