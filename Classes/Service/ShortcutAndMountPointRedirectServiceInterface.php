<?php

declare(strict_types=1);

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

use Psr\Http\Server\MiddlewareInterface;

/**
 * Using a dedicated interface to have easier test stubbing/mocking abilities at hand. Not meant
 * as a public replacement method, albeit it can be used.
 *
 * For a simple passthrough from the simplified middleware, the original MiddlewareInterface is
 * extended to keep the compat for the service - which basically is a middleware.
 *
 * @internal not part of public extension API.
 */
interface ShortcutAndMountPointRedirectServiceInterface extends MiddlewareInterface {}
