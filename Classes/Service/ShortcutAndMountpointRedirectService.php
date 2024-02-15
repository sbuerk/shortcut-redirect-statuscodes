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

namespace StefanBuerk\ShortcutRedirectStatuscodes\Service;

// NOTE: Using a workaround to generate a class alias based on the TYPO3 version, which itself is than used
// as parent (extend) class definition for the class defined in this file.
// @todo Find a way to handle this more nicely in `Services.yaml` directly.

if ((new \TYPO3\CMS\Core\Information\Typo3Version())->getMajorVersion() < 12) {
    class_alias(\StefanBuerk\ShortcutRedirectStatuscodes\Service\Version11\ShortcutAndMountpointRedirectService::class, 'StefanBuerk\\ShortcutRedirectStatuscodes\\Service\\VersionedShortcutAndMountpointRedirectService');
} else {
    class_alias(\StefanBuerk\ShortcutRedirectStatuscodes\Service\Version12\ShortcutAndMountpointRedirectService::class, 'StefanBuerk\\ShortcutRedirectStatuscodes\\Service\\VersionedShortcutAndMountpointRedirectService');
}

final class ShortcutAndMountpointRedirectService extends VersionedShortcutAndMountpointRedirectService {}
