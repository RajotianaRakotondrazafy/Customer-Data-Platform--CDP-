<?php
/**
 * @var list<App\Model\Customer>                            $customers
 * @var array<int, array{events: int, spend: string}>       $totals
 * @var string                                              $search
 * @var int                                                 $cursor
 * @var int|null                                            $nextCursor
 * @var callable                                            $e
 * @var callable                                            $url
 */
?>
<h1>Customers</h1>

<form method="get" action="/customers" class="row" style="margin-bottom: 16px">
    <input type="search" name="q" value="<?= $e($search) ?>" placeholder="Search by email" style="min-width: 280px">
    <button type="submit">Search</button>
    <?php if ($search !== ''): ?><a href="/customers">Clear</a><?php endif ?>
</form>

<div class="table-wrap">
    <table>
        <thead>
        <tr>
            <th>ID</th><th>Email</th><th>Name</th>
            <th class="num">Events</th><th class="num">Total spend</th><th>Created</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($customers as $customer): ?>
            <?php $t = $totals[$customer->id] ?? ['events' => 0, 'spend' => '0']; ?>
            <tr>
                <td class="muted"><?= $e($customer->id) ?></td>
                <td><a href="/customers/<?= $e($customer->id) ?>"><?= $e($customer->email) ?></a></td>
                <td><?= $e($customer->name ?? '—') ?></td>
                <td class="num"><?= $e(number_format($t['events'])) ?></td>
                <td class="num"><?= $e(number_format((float) $t['spend'], 2)) ?> €</td>
                <td class="muted"><?= $e($customer->createdAt->format('Y-m-d')) ?></td>
            </tr>
        <?php endforeach ?>
        <?php if ($customers === []): ?>
            <tr><td colspan="6" class="muted">No customers<?= $search !== '' ? ' matching “' . $e($search) . '”' : '' ?>.</td></tr>
        <?php endif ?>
        </tbody>
    </table>
</div>

<div class="pager">
    <span><?php if ($cursor > 0): ?><a href="<?= $e($url('/customers', ['q' => $search])) ?>">« First page</a><?php endif ?></span>
    <span><?php if ($nextCursor !== null): ?><a href="<?= $e($url('/customers', ['q' => $search, 'cursor' => $nextCursor])) ?>">Next »</a><?php endif ?></span>
</div>
