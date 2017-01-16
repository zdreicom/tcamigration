TCA migration helper tool
=========================

Run migration
-------------

``php typo3/cli_dispatch.php extbase tcamigration:migratetable extension_key table1,table2``

Check syntax with ``php typo3/cli_dispatch.phpsh extbase help tcamigration:migratetable``.

After migration
---------------

* Delete ``ctrl|dynamicConfigFile`` in each TCA file
* Delete **old** files in ``Configuration/TCA``
* Change the timestamps on key ``lower`` back to ``mktime(0, 0, 0, date('m'), date('d'), date('Y'))``
* Check for absolute paths and convert to relative paths if needed (``EXT:``)
* Remove third parameter on all ``\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns`` calls
* Check the contents of the files ``ext_localconf.php`` and ``ext_tables.php`` and move stuff as needed (e.g. to ``Configuration/TCA/Overrides``)
* Restore ``\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig`` on all file and image fields
