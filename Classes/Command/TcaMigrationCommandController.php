<?php
namespace Z3\tcamigration\Command;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Package\Package;
use TYPO3\CMS\Core\Package\PackageManager;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * CommandController for working with extension management through CLI/scheduler
 */
class TcaMigrationCommandController extends CommandController
{

    /**
     * @var bool
     */
    protected $requestAdminPermissions = true;

    /**
     * @var array
     */
    protected $tcaBackup = [];

    /**
     * @var array
     */
    protected $collectedMessages = [];

    /**
     * @var array
     */
    protected $filesWritten = [];

    /**
     * Migrates the TCA of tables of a given extension
     *
     * 1. Unset all TCA of given tables (save backup in $this->oldTca)
     * 2. Scan for potential TCA files and reevaluate them
     * 3. Migrate each table
     * 4. Write new TCA file to Configuration/TCA/<table>.php
     * 5. Restore TCA from $this->oldTCA
     * 6. Dump collected messages
     *
     * @param string $extension The extension key of the extension to work on
     * @param string $tables Comma-separated list of tables to migrate
     * @return string The list of applied migrations
     * @cli
     */
    public function migrateTableCommand($extension, $tables)
    {
        $tables = GeneralUtility::trimExplode(',', $tables);
        foreach ($tables as $key => $table) {
            try {
                $this->backupTcaForTable($table);
                $this->unsetTcaForTable($table);
            } catch (\UnexpectedValueException $e) {
                if ($e->getCode() === 1468586344) {
                    unset($tables[$key]);
                    $this->collectedMessages[] = $e->getMessage();
                    continue;
                }
            }
        }
        $this->scanAndEvaluateExtensionTcaFiles($extension, $tables);

        foreach ($tables as $table) {
            $newTCA = $this->migrateTcaForTable($table);
            $this->writeNewTcaFileForTableInExtension($extension, $table, $newTCA);
        }
        $this->restoreOriginalTcaForTables($tables);

        return $this->prepareMessages();
    }

    /**
     * Backup of a given tables TCA to $this->tcaBackup
     *
     * @param string $table The table to backup
     * @throws \UnexpectedValueException
     */
    protected function backupTcaForTable($table)
    {
        if (isset($GLOBALS['TCA'][$table]) && is_array($GLOBALS['TCA'][$table])) {
            $this->tcaBackup[$table] = $GLOBALS['TCA'][$table];
        } else {
            throw new \UnexpectedValueException('No such table in tca: ' . $table, 1468586344);
        }
    }

    /**
     * Unsets the TCA for a given table
     *
     * @param string $table The table for which to unset the TCA
     */
    protected function unsetTcaForTable($table)
    {
        unset($GLOBALS['TCA'][$table]);
    }

    /**
     * Scans the extension for TCA related files and reevaluates them
     *
     * @param string $extension The name of the extension
     * @param array $tables The array of tables to consider
     */
    protected function scanAndEvaluateExtensionTcaFiles($extension, array $tables)
    {
        $this->loadBaseTcaOfExtension($extension, $tables);
        $this->loadExtTablesOfExtension($extension, $tables);
    }

    /**
     * Loads the base TCA of an extension
     *
     * This is a copy from the core (bootstrap), limited to one extension
     * and the given tables.
     *
     * @param string $extension The name of the extension
     * @param array $tables The array of tables to consider
     */
    protected function loadBaseTcaOfExtension($extension, array $tables)
    {
        /** @var Package[] $activePackages */
        $package = GeneralUtility::makeInstance(PackageManager::class)->getPackage($extension);

        // First load "full table" files from Configuration/TCA
        $tcaConfigurationDirectory = $package->getPackagePath() . 'Configuration/TCA';
        if (is_dir($tcaConfigurationDirectory)) {
            $files = scandir($tcaConfigurationDirectory);
            foreach ($files as $file) {
                if (
                    is_file($tcaConfigurationDirectory . '/' . $file)
                    && ($file !== '.')
                    && ($file !== '..')
                    && (substr($file, -4, 4) === '.php')
                ) {
                    $tcaOfTable = require($tcaConfigurationDirectory . '/' . $file);
                    if (is_array($tcaOfTable)) {
                        // TCA table name is filename without .php suffix, eg 'sys_notes', not 'sys_notes.php'
                        $tcaTableName = substr($file, 0, -4);
                        // Limit to given tables
                        if (in_array($tcaTableName, $tables)) {
                            $GLOBALS['TCA'][$tcaTableName] = $tcaOfTable;
                        }
                    }
                }
            }
        }
    }

    /**
     * Loads the ext_tables.php file from an extension and any additional files
     *
     * This is a copy from the core (bootstrap), limited to one extension
     * and the given tables.
     *
     * @param string $extension The name of the extension
     * @param array $tables The array of tables to consider
     */
    protected function loadExtTablesOfExtension($extension, array $tables)
    {
        $GLOBALS['_EXTKEY'] = $extension;
        global $_EXTKEY;

        global $T3_SERVICES, $T3_VAR, $TYPO3_CONF_VARS;
        global $TBE_MODULES, $TBE_MODULES_EXT, $TCA;
        global $PAGES_TYPES, $TBE_STYLES;

        $extensionInformation = $GLOBALS['TYPO3_LOADED_EXT'][$extension];
        // Load each ext_tables.php file of loaded extensions
        if ((is_array($extensionInformation) || $extensionInformation instanceof \ArrayAccess)
            && $extensionInformation['ext_tables.php']
        ) {
            // $_EXTKEY and $_EXTCONF are available in ext_tables.php
            // and are explicitly set in cached file as well
            $_EXTCONF = $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf'][$_EXTKEY];
            require $extensionInformation['ext_tables.php'];

            foreach ($tables as $tableName) {
                if (!isset($TCA[$tableName]['columns'])) {
                    $columnsConfigFile = $TCA[$tableName]['ctrl']['dynamicConfigFile'];
                    if ($columnsConfigFile) {
                        if (GeneralUtility::isAbsPath($columnsConfigFile)) {
                            include($columnsConfigFile);
                        } else {
                            throw new \RuntimeException(
                                'Columns configuration file not found',
                                1468241963
                            );
                        }
                    }
                }
            }
        }
    }

    /**
     * Migrates the TCA of a given table
     *
     * @param string $table The table to migrate
     * @return array The new TCA for given table
     */
    protected function migrateTcaForTable($table)
    {
        $fakeGlobals = ['TCA'];
        $fakeGlobals['TCA'][$table] = $GLOBALS['TCA'][$table];
        /** @var \TYPO3\CMS\Core\Migrations\TcaMigration $tcaMigration */
        $tcaMigration = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Migrations\TcaMigration::class);
        $fakeGlobals['TCA'] = $tcaMigration->migrate($fakeGlobals['TCA']);
        $this->collectedMessages = array_merge($this->collectedMessages, $tcaMigration->getMessages());
        return $fakeGlobals['TCA'][$table];
    }

    /**
     * Writes the new TCA file for a given table in a given extension
     *
     * @param string $extension The extension to work on
     * @param string $table The name of the table
     * @param array $newTca The array of the new TCA for the given table
     */
    protected function writeNewTcaFileForTableInExtension($extension, $table, array $newTca)
    {
        /** @var Package[] $activePackages */
        $package = GeneralUtility::makeInstance(PackageManager::class)->getPackage($extension);

        // Get tca configuration directory
        $tcaConfigurationDirectory = $package->getPackagePath() . 'Configuration/TCA';
        if (!is_dir($tcaConfigurationDirectory)) {
            $result = GeneralUtility::mkdir($tcaConfigurationDirectory);
            if (!$result) {
                $this->outputLine('Failed to create directory: \"' . $tcaConfigurationDirectory . '\"');
                $this->quit(99);
            }
        }

        $newFileContent = '<?php' . LF . 'return ';
        $newFileContent .= var_export($newTca, true);
        $newFileContent .= ';' . LF;
        $fileName = $tcaConfigurationDirectory . '/' . $table . '.php';
        GeneralUtility::writeFile($fileName, $newFileContent);
        $this->filesWritten[] = $fileName;
    }

    /**
     * Restores the original TCA from backup in $this->tcaBackup
     *
     * @param array $tables The list of tables to restore
     */
    protected function restoreOriginalTcaForTables(array $tables)
    {
        foreach ($tables as $table) {
            unset($GLOBALS['TCA'][$table]);
            $GLOBALS['TCA'][$table] = $this->tcaBackup[$table];
        }
    }

    /**
     * Creates the message string for the terminal
     *
     * @return string The string of messages concatenated with LF
     */
    protected function prepareMessages()
    {
        $this->collectedMessages[] = '';
        $this->collectedMessages[] = 'New files written:';
        $this->collectedMessages = array_merge($this->collectedMessages, $this->filesWritten);
        $messages = implode(LF, $this->collectedMessages);
        return $messages . LF;
    }

}
