<section>
    <h3><?= __d('app', 'Trashed') ?></h3>
    <div class="trashed-records">
        <strong><?= __d('app', 'Total Trashed Records:') ?></strong>
        <?= $this->Number->format($totalTrashed) ?>
    </div>

    <table cellspacing="0" cellpadding="0">
        <thead>
            <tr>
                <th><?= __d('app', 'Table') ?></th>
                <th class="right-text"><?= __d('app', 'Trashed') ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($trashed as $key => $value): ?>
            <tr>
                <td><?= h($key) ?></td>
                <td class="right-text"><?= $this->Number->format($value) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>