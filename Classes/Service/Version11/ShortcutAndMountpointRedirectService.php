<?php

declare(strict_types=1);

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

namespace StefanBuerk\ShortcutRedirectStatuscodes\Service\Version11;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use StefanBuerk\ShortcutRedirectStatuscodes\Service\AbstractShortcutAndMountpointRedirectService;

/**
 * TYPO3 v11 specific implementations.
 */
abstract class ShortcutAndMountpointRedirectService extends AbstractShortcutAndMountpointRedirectService
{
    protected function createPageAccessFailureReasons(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ?ResponseInterface {
        // noop - TYPO3 v11 did not return a specific error response.
        return null;
    }
}
