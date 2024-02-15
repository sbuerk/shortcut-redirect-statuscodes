<?php

/*
 * This file is part of the "shortcut_redirect_statuscodes" Extension for TYPO3 CMS.
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

defined('TYPO3') or die;

call_user_func(
    static function (): void {
        $typo3version = new \TYPO3\CMS\Core\Information\Typo3Version();
        $majorVersion = $typo3version->getMajorVersion();
        $useAssociativeItemKeys = ($majorVersion >= 12);
        $keyLabel = $useAssociativeItemKeys ? 'label' : 0;
        $keyValue = $useAssociativeItemKeys ? 'value' : 1;
        $keyGroup = $useAssociativeItemKeys ? 'group' : 3;
        $items = [
            [
                $keyLabel => 'LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.statuscode_default',
                $keyValue => 0,
                $keyGroup => 'default',
            ],
            [
                $keyLabel => 'LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.statuscode_301',
                $keyValue => 301,
                $keyGroup => 'change',
            ],
            [
                $keyLabel => 'LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.statuscode_302',
                $keyValue => 302,
                $keyGroup => 'change',
            ],
            [
                $keyLabel => 'LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.statuscode_303',
                $keyValue => 303,
                $keyGroup => 'change',
            ],
            [
                $keyLabel => 'LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.statuscode_307',
                $keyValue => 307,
                $keyGroup => 'keep',
            ],
            [
                $keyLabel => 'LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.statuscode_308',
                $keyValue => 308,
                $keyGroup => 'keep',
            ],
        ];
        $columns = [
            'redirect_code' => [
                'exclude' => 0,
                'label' => 'LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.label',
                'config' => [
                    'type' => 'select',
                    'renderType' => 'selectSingle',
                    'size' => 1,
                    'default' => 0,
                    'items' => $items,
                    'itemGroups' => [
                        'default' => 'LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.shortcut.statuscode_group_default',
                        'keep' => 'LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.shortcut.statuscode_group_keep',
                        'change' => 'LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.shortcut.statuscode_group_change',
                    ],
                ],
            ],
        ];
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('pages', $columns);
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
            'pages',
            'shortcut',
            'redirect_code;LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.shortcutandmountpoint_label',
            'after:shortcut_mode'
        );
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
            'pages',
            'mountpage',
            'redirect_code;LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.shortcutandmountpoint_label',
            'after:mount_pid'
        );
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addFieldsToPalette(
            'pages',
            'external',
            'redirect_code;LLL:EXT:shortcut_redirect_statuscodes/Resources/Private/Language/locallang_db.xlf:redirect_code.external_label',
            'after:url'
        );
    }
);
