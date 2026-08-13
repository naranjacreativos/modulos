<?php

declare(strict_types=1);

namespace NaranjaCreativos\FabricSamples\Migration;

use NaranjaCreativos\FabricSamples\Configuration\ModuleConfiguration;

final class LegacyCleanupService
{
    public function __construct(private \Fabricssamples $module)
    {
    }

    /** @return array{configurations:int,hooks:int} */
    public function cleanup(): array
    {
        return [
            'configurations' => $this->deleteObsoleteConfiguration(),
            'hooks' => $this->deleteObsoleteHookRegistrations(),
        ];
    }

    private function deleteObsoleteConfiguration(): int
    {
        $names = array_map(
            static fn (string $key): string => ModuleConfiguration::PREFIX . $key,
            ModuleConfiguration::obsoleteKeys()
        );
        if ($names === []) {
            return 0;
        }

        $quoted = implode(',', array_map(static fn (string $name): string => "'" . pSQL($name) . "'", $names));
        $db = \Db::getInstance();
        $rows = $db->executeS(
            'SELECT id_configuration FROM `' . _DB_PREFIX_ . 'configuration` WHERE name IN (' . $quoted . ')'
        );
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            array_column(is_array($rows) ? $rows : [], 'id_configuration')
        ))));
        if ($ids === []) {
            return 0;
        }

        $in = implode(',', $ids);
        if ($this->tableExists(_DB_PREFIX_ . 'configuration_lang')) {
            $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'configuration_lang` WHERE id_configuration IN (' . $in . ')');
        }
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'configuration` WHERE id_configuration IN (' . $in . ')');

        return count($ids);
    }

    private function deleteObsoleteHookRegistrations(): int
    {
        $obsoleteHooks = ['displayHeader'];
        $quoted = implode(',', array_map(static fn (string $name): string => "'" . pSQL($name) . "'", $obsoleteHooks));
        $db = \Db::getInstance();
        $rows = $db->executeS(
            'SELECT hm.id_hook_module FROM `' . _DB_PREFIX_ . 'hook_module` hm'
            . ' INNER JOIN `' . _DB_PREFIX_ . 'hook` h ON h.id_hook=hm.id_hook'
            . ' WHERE hm.id_module=' . (int) $this->module->id . ' AND h.name IN (' . $quoted . ')'
        );
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            array_column(is_array($rows) ? $rows : [], 'id_hook_module')
        ))));
        if ($ids === []) {
            return 0;
        }

        $in = implode(',', $ids);
        if ($this->tableExists(_DB_PREFIX_ . 'hook_module_exceptions')) {
            $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'hook_module_exceptions` WHERE id_hook_module IN (' . $in . ')');
        }
        $db->execute('DELETE FROM `' . _DB_PREFIX_ . 'hook_module` WHERE id_hook_module IN (' . $in . ')');

        return count($ids);
    }

    private function tableExists(string $table): bool
    {
        $escaped = str_replace(['\\', '_', '%'], ['\\\\', '\\_', '\\%'], $table);
        $rows = \Db::getInstance()->executeS("SHOW TABLES LIKE '" . pSQL($escaped) . "'");
        return is_array($rows) && $rows !== [];
    }
}
