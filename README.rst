TCA migration helper tool
=========================

Run migration
-------------

``php typo3/cli_dispatch.php extbase tcamigration:migratetable extension_key table1,table2``

Check syntax with ``php typo3/cli_dispatch.phpsh extbase help tcamigration:migratetable``.

After migration
---------------

* Delete **old** files in ``Configuration/TCA``
* Check for absolute paths and convert to relative paths if needed (``EXT:``)
* Remove third parameter on all ``\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns`` calls
* Check the contents of the files ``ext_localconf.php`` and ``ext_tables.php`` and move stuff as needed (e.g. to ``Configuration/TCA/Overrides``)
* Restore ``\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::getFileFieldTCAConfig`` on all file and image fields
* Rewrite all ``EXT:cms/locallang.ttc_xlf`` to ``EXT:frontend/Resources/Private/Language/locallang.ttc_xlf``
