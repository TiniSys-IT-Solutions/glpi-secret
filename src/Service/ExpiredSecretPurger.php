<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Service;

use CronTask;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use RuntimeException;

final class ExpiredSecretPurger
{
    public function run(?CronTask $task): int
    {
        global $DB;
        $count = 0;
        $failed = false;
        $task?->setVolume(0);
        try {
            (new ExpirationLifecycle())->synchronize();
            $days = max(0, (int) ($task->fields['param'] ?? 0));
            if ($days === 0) {
                return 0;
            }
            $settings = \Config::getConfigurationValues('plugin:secret');
            $cursor = max(0, (int) ($settings['purge_cursor'] ?? 0));
            $query = [
                'SELECT' => ['id'], 'FROM' => Secret::getTable(),
                'WHERE' => ['expiration' => ['<', date('Y-m-d H:i:s', strtotime('-' . $days . ' days'))]],
                'ORDER' => ['id ASC'], 'LIMIT' => 500,
            ];
            if ($cursor > 0) {
                $query['WHERE']['id'] = ['>', $cursor];
            }
            $rows = iterator_to_array($DB->request($query), false);
            if ($rows === [] && $cursor > 0) {
                unset($query['WHERE']['id']);
                $rows = iterator_to_array($DB->request($query), false);
            }
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                $cursor = $id;
                $DB->beginTransaction();
                try {
                    if (!(new AuditLogger())->record($id, AuditLogger::PURGE, ['source' => 'automatic_action'])
                        || !$DB->delete(SecretItem::getTable(), ['plugin_secret_secrets_id' => $id])
                        || !$DB->delete(Secret::getTable(), ['id' => $id])) {
                        throw new RuntimeException('Expired secret purge failed.');
                    }
                    $DB->commit();
                    ++$count;
                } catch (\Throwable) {
                    $DB->rollBack();
                    $failed = true;
                }
            }
            \Config::setConfigurationValues('plugin:secret', ['purge_cursor' => (string) ($rows === [] ? 0 : $cursor)]);
        } catch (\Throwable) {
            $failed = true;
        }
        $task?->setVolume($count);
        if ($failed) {
            $task?->log(__('Secret maintenance failed. Some records were retained; check database availability.', 'secret'));
        }
        return $failed ? -1 : ($count > 0 ? 1 : 0);
    }
}
