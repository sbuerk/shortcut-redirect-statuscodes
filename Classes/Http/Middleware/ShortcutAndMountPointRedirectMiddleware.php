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

namespace StefanBuerk\ShortcutRedirectStatuscodes\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use StefanBuerk\ShortcutRedirectStatuscodes\Service\ShortcutAndMountPointRedirectServiceInterface;

/**
 * Simple middleware as replacement for `\TYPO3\CMS\Frontend\Middleware\ShortcutAndMountPointRedirect`
 * which dispatches to a service and uses only minimal dispatching code in this implementation.
 *
 * @see \TYPO3\CMS\Frontend\Middleware\ShortcutAndMountPointRedirect
 * @internal not part of public extension API.
 */
final class ShortcutAndMountPointRedirectMiddleware implements MiddlewareInterface
{
    private ShortcutAndMountPointRedirectServiceInterface $shortcutAndMountPointRedirectService;

    public function __construct(ShortcutAndMountPointRedirectServiceInterface $shortcutAndMountPointRedirectService)
    {
        $this->shortcutAndMountPointRedirectService = $shortcutAndMountPointRedirectService;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $this->shortcutAndMountPointRedirectService->process($request, $handler);
    }
}
