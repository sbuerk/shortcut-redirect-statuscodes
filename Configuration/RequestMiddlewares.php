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

return [
    'frontend' => [
        // Note: The TYPO3 core identifier is here used to replace the class, but keep additional configuraiton like
        // disable or after/before configuration. TYPO3 declares this as @internal, therefore it is likely that it is
        // moved or removed in the future without any notice !!!.
        'typo3/cms-frontend/shortcut-and-mountpoint-redirect' => [
            'target' => \StefanBuerk\ShortcutRedirectStatuscodes\Http\Middleware\ShortcutAndMountPointRedirectMiddleware::class,
        ],
    ],
];
