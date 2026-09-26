<?php
/**
 * @var list<App\Model\Event>  $events
 * @var array<int, string>     $emails customer id => email
 * @var int                    $before
 * @var int|null               $nextBefore
 * @var callable               $e
 * @var callable               $url
 */
?>
<h1>Events <span class="muted" style="font-size: 14px; font-weight: normal">latest first</span></h1>

<div class="table-wrap">
    <table>
        <thead><tr><th>ID</th><th>Occurred at (UTC)</th><th>Customer</th><th>Event</th><th>Properties</th></tr></thead>
        <tbody>
        <?php foreach ($events as $event): ?>
            <?php $ev = $event->jsonSerialize(); ?>
            <tr>
                <td class="muted"><?= $e($event->id) ?></td>
                <td class="muted"><?= $e(str_replace(['T', 'Z'], [' ', ''], $ev['occurred_at'])) ?></td>
                <td><a href="/customers/<?= $e($event->customerId) ?>"><?= $e($emails[$event->customerId] ?? '#' . $event->customerId) ?></a></td>
                <td><span class="badge"><?= $e($event->type) ?></span></td>
                <td class="props"><code><?= $e(json_encode($ev['properties'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></code></td>
            </tr>
        <?php endforeach ?>
        <?php if ($events === []): ?>
            <tr><td colspan="5" class="muted">No events yet. Send one to <code>POST /api/events</code> or run the seed.</td></tr>
        <?php endif ?>
        </tbody>
    </table>
</div>

<div class="pager">
    <span><?php if ($before > 0): ?><a href="/events">« Latest</a><?php endif ?></span>
    <span><?php if ($nextBefore !== null): ?><a href="<?= $e($url('/events', ['before' => $nextBefore])) ?>">Older »</a><?php endif ?></span>
</div>
