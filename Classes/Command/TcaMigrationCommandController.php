<?php
namespace TYPO3\tcamigration\Command;

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
     * Migrates the TCA of tables of a given extension
     *
     * 1. Unset all TCA of given tables (save backup in $this->oldTca)
     * 2. Scan for potential TCA files and reevaluate them
     * 3. Migrate each table
     * 4. Write new TCA file to Configuration/TCA/<table>.php
     * 5. Dump collected messages
     *
     * @param string $extension The extension key of the extension to work on
     * @param string $tables Comma-separated list of tables to migrate
     * @cli
     */
    public function migrateTable($extension, $tables)
    {

    }

}
