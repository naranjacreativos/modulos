<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Security;

/** Shared fixed-window limiter with automatic TTL cleanup. */
final class AjaxRateLimiter
{
    public function assertAllowed(string $key, int $perMinute = 30, int $perFiveSeconds = 8): void
    {
        $perMinute = min(120, max(1, $perMinute));
        $perFiveSeconds = min(30, max(1, $perFiveSeconds));
        $now = time();
        $minuteBucket = intdiv($now, 60);
        $burstBucket = intdiv($now, 5);
        $hash = hash('sha256', $key);
        $db = \Db::getInstance();

        $sql = 'INSERT INTO `' . _DB_PREFIX_ . 'fabricssamples_rate_limit`'
            . ' (`key_hash`,`minute_bucket`,`minute_hits`,`burst_bucket`,`burst_hits`,`date_upd`) VALUES ('
            . "'" . pSQL($hash) . "'," . $minuteBucket . ',1,' . $burstBucket . ',1,NOW())'
            . ' ON DUPLICATE KEY UPDATE'
            . ' minute_hits=IF(minute_bucket=VALUES(minute_bucket),minute_hits+1,1),'
            . ' minute_bucket=VALUES(minute_bucket),'
            . ' burst_hits=IF(burst_bucket=VALUES(burst_bucket),burst_hits+1,1),'
            . ' burst_bucket=VALUES(burst_bucket),date_upd=NOW()';
        if (!$db->execute($sql)) {
            throw new \RuntimeException('No se pudo comprobar la frecuencia de solicitudes.');
        }

        $row = $db->getRow(
            'SELECT minute_hits,burst_hits FROM `' . _DB_PREFIX_ . 'fabricssamples_rate_limit`'
            . " WHERE key_hash='" . pSQL($hash) . "'"
        );
        if (!is_array($row)) {
            throw new \RuntimeException('No se pudo comprobar la frecuencia de solicitudes.');
        }
        if ((int) $row['minute_hits'] > $perMinute || (int) $row['burst_hits'] > $perFiveSeconds) {
            throw new \RuntimeException('Demasiadas solicitudes. Espere unos segundos antes de intentarlo de nuevo.');
        }

        // About one cleanup per 100 requests; every node shares the same table.
        if (random_int(1, 100) === 1) {
            $db->delete('fabricssamples_rate_limit', "date_upd<'" . pSQL(date('Y-m-d H:i:s', $now - 600)) . "'");
        }
    }
}
