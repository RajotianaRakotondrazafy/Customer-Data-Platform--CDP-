<?php
/**
 * @var array<string, mixed> $profile CustomerProfile::jsonSerialize() — same data as GET /api/customers/{id}
 * @var callable             $e
 */
$customer = $profile['customer']->jsonSerialize();
$stats = $profile['stats'];
?>
<p><a href="/customers">« Customers</a></p>
<h1><?= $e($customer['name'] ?? $customer['email']) ?></h1>
<p class="muted">
    #<?= $e($customer['id']) ?> · <?= $e($customer['email']) ?> · created <?= $e(substr($customer['created_at'], 0, 10)) ?>
    · <a href="/api/customers/<?= $e($customer['id']) ?>">JSON</a>
</p>

<div class="stats">
    <div class="stat"><span class="muted">Total events</span><b><?= $e(number_format($stats['total_events'])) ?></b></div>
    <div class="stat"><span class="muted">Purchases</span><b><?= $e(number_format($stats['total_purchases'])) ?></b></div>
    <div class="stat"><span class="muted">Total spend</span><b><?= $e(number_format((float) $stats['total_spend'], 2)) ?> €</b></div>
    <div class="stat"><span class="muted">Last seen</span><b style="font-size: 16px"><?= $e($stats['last_seen_at'] ? substr($stats['last_seen_at'], 0, 10) : '—') ?></b></div>
</div>

<h2>Last <?= $e(count($profile['recent_events'])) ?> events</h2>
<div class="table-wrap">
    <table>
        <thead><tr><th>Occurred at (UTC)</th><th>Event</th><th>Properties</th></tr></thead>
        <tbody>
        <?php foreach ($profile['recent_events'] as $event): ?>
            <?php $ev = $event->jsonSerialize(); ?>
            <tr>
                <td class="muted"><?= $e(str_replace(['T', 'Z'], [' ', ''], $ev['occurred_at'])) ?></td>
                <td><span class="badge"><?= $e($ev['event']) ?></span></td>
                <td class="props"><code><?= $e(json_encode($ev['properties'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></td>
            </tr>
        <?php endforeach ?>
        <?php if ($profile['recent_events'] === []): ?>
            <tr><td colspan="3" class="muted">No events.</td></tr>
        <?php endif ?>
        </tbody>
    </table>
</div>

<h2>Statistics by event</h2>
<div class="table-wrap">
    <table>
        <thead>
        <tr><th>Event</th><th class="num">Count</th><th class="num">Total amount</th><th>First</th><th>Last</th></tr>
        </thead>
        <tbody>
        <?php foreach ($stats['by_event'] as $stat): ?>
            <?php $s = $stat->jsonSerialize(); ?>
            <tr>
                <td><span class="badge"><?= $e($s['event']) ?></span></td>
                <td class="num"><?= $e(number_format($s['count'])) ?></td>
                <td class="num"><?= $e(number_format((float) $s['total_amount'], 2)) ?></td>
                <td class="muted"><?= $e(substr($s['first_at'], 0, 10)) ?></td>
                <td class="muted"><?= $e(substr($s['last_at'], 0, 10)) ?></td>
            </tr>
        <?php endforeach ?>
        </tbody>
    </table>
</div>
