<?php
/**
 * @var list<array<string, string>>     $rows
 * @var string                          $match
 * @var list<string>                    $operators
 * @var list<string>                    $eventNames
 * @var list<string>                    $keyNames
 * @var array<string, mixed>            $query     raw query string (to build the next-page link)
 * @var array<string, mixed>            $apiBody   equivalent POST /api/segments/query body
 * @var App\Model\SegmentResult|null    $result
 * @var array<string, string>           $errors
 * @var float|null                      $ms
 * @var callable                        $e
 * @var callable                        $url
 */
$field = static fn (int $i, string $name): string => "conditions[$i][$name]";
?>
<h1>Segments</h1>

<?php if ($errors !== []): ?>
    <div class="errors">
        <strong>The query is invalid</strong>
        <ul><?php foreach ($errors as $path => $message): ?><li><code><?= $e($path) ?></code> — <?= $e($message) ?></li><?php endforeach ?></ul>
    </div>
<?php endif ?>

<form method="get" action="/segments" class="panel" id="segment-form">
    <div class="row" style="margin-bottom: 12px">
        Customers matching
        <select name="match">
            <option value="all" <?= $match === 'all' ? 'selected' : '' ?>>all conditions (AND)</option>
            <option value="any" <?= $match === 'any' ? 'selected' : '' ?>>any condition (OR)</option>
        </select>
    </div>

    <div id="conditions">
        <?php foreach ($rows as $i => $row): ?>
            <div class="row condition" style="margin-bottom: 8px">
                <select name="<?= $field($i, 'kind') ?>" data-role="kind">
                    <option value="property" <?= $row['kind'] === 'property' ? 'selected' : '' ?>>has an event where</option>
                    <option value="aggregate" <?= $row['kind'] === 'aggregate' ? 'selected' : '' ?>>aggregate of event</option>
                    <option value="exists" <?= $row['kind'] === 'exists' ? 'selected' : '' ?>>did event</option>
                </select>
                <input name="<?= $field($i, 'event') ?>" value="<?= $e($row['event']) ?>" placeholder="event" list="event-names" size="14" required>
                <input name="<?= $field($i, 'property') ?>" value="<?= $e($row['property']) ?>" placeholder="property" list="key-names" size="12" data-show="property">
                <select name="<?= $field($i, 'aggregate') ?>" data-show="aggregate">
                    <option value="count" <?= $row['aggregate'] === 'count' ? 'selected' : '' ?>>count</option>
                    <option value="total_amount" <?= $row['aggregate'] === 'total_amount' ? 'selected' : '' ?>>total_amount</option>
                </select>
                <select name="<?= $field($i, 'operator') ?>" data-show="property aggregate">
                    <?php foreach ($operators as $op): ?>
                        <option value="<?= $e($op) ?>" <?= $row['operator'] === $op ? 'selected' : '' ?>><?= $e($op) ?></option>
                    <?php endforeach ?>
                </select>
                <input name="<?= $field($i, 'value') ?>" value="<?= $e($row['value']) ?>" placeholder="value" size="16" data-show="property aggregate">
                <button type="button" class="secondary" data-role="remove" title="Remove">✕</button>
            </div>
        <?php endforeach ?>
    </div>

    <p class="muted" style="margin: 8px 0 12px">
        Values are typed like JSON: <code>120</code>, <code>9.99</code>, <code>true</code>, text.
        <code>in</code> takes a comma-separated list. Wrap in quotes to force text: <code>"123"</code>.
    </p>

    <div class="row">
        <button type="button" class="secondary" id="add-condition">+ Add condition</button>
        <button type="submit">Run segment</button>
    </div>

    <datalist id="event-names"><?php foreach ($eventNames as $n): ?><option value="<?= $e($n) ?>"><?php endforeach ?></datalist>
    <datalist id="key-names"><?php foreach ($keyNames as $n): ?><option value="<?= $e($n) ?>"><?php endforeach ?></datalist>
</form>

<?php if ($result !== null): ?>
    <h2>
        <?= $e(number_format($result->total)) ?> matching customer<?= $result->total === 1 ? '' : 's' ?>
        <span class="muted" style="font-weight: normal">· showing <?= $e(count($result->customers)) ?> · <?= $e(number_format($ms, 1)) ?> ms</span>
    </h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Email</th><th>Name</th><th>Created</th></tr></thead>
            <tbody>
            <?php foreach ($result->customers as $customer): ?>
                <tr>
                    <td class="muted"><?= $e($customer->id) ?></td>
                    <td><a href="/customers/<?= $e($customer->id) ?>"><?= $e($customer->email) ?></a></td>
                    <td><?= $e($customer->name ?? '—') ?></td>
                    <td class="muted"><?= $e($customer->createdAt->format('Y-m-d')) ?></td>
                </tr>
            <?php endforeach ?>
            <?php if ($result->customers === []): ?>
                <tr><td colspan="4" class="muted">No customer matches.</td></tr>
            <?php endif ?>
            </tbody>
        </table>
    </div>
    <div class="pager">
        <span><?php if (!empty($query['cursor'])): ?><a href="<?= $e($url('/segments', ['cursor' => null] + $query)) ?>">« First page</a><?php endif ?></span>
        <span><?php if ($result->nextCursor !== null): ?><a href="<?= $e($url('/segments', ['cursor' => $result->nextCursor] + $query)) ?>">Next »</a><?php endif ?></span>
    </div>

    <h2>Same query through the API</h2>
    <pre>curl -X POST http://localhost:8080/api/segments/query \
  -H 'Content-Type: application/json' \
  -d '<?= $e(json_encode($apiBody, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) ?>'</pre>
<?php endif ?>

<script>
    // Progressive enhancement: show only the fields of each row's kind, add/remove rows.
    (function () {
        const container = document.getElementById('conditions');

        function refresh(row) {
            const kind = row.querySelector('[data-role=kind]').value;
            row.querySelectorAll('[data-show]').forEach(function (el) {
                const visible = el.dataset.show.split(' ').includes(kind);
                el.hidden = !visible;
                el.disabled = !visible;
            });
        }

        function renumber() {
            container.querySelectorAll('.condition').forEach(function (row, i) {
                row.querySelectorAll('[name]').forEach(function (el) {
                    el.name = el.name.replace(/conditions\[\d+]/, 'conditions[' + i + ']');
                });
            });
        }

        container.addEventListener('change', function (e) {
            if (e.target.dataset.role === 'kind') refresh(e.target.closest('.condition'));
        });
        container.addEventListener('click', function (e) {
            if (e.target.dataset.role !== 'remove') return;
            if (container.querySelectorAll('.condition').length > 1) {
                e.target.closest('.condition').remove();
                renumber();
            }
        });
        document.getElementById('add-condition').addEventListener('click', function () {
            const rows = container.querySelectorAll('.condition');
            const clone = rows[rows.length - 1].cloneNode(true);
            clone.querySelectorAll('input').forEach(function (el) { el.value = ''; });
            container.appendChild(clone);
            renumber();
            refresh(clone);
        });
        container.querySelectorAll('.condition').forEach(refresh);
    })();
</script>
