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

namespace StefanBuerk\ShortcutRedirectStatuscodes\Service\Version12;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use StefanBuerk\ShortcutRedirectStatuscodes\Service\AbstractShortcutAndMountpointRedirectService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\ErrorController;
use TYPO3\CMS\Frontend\Page\PageAccessFailureReasons;

/**
 * TYPO3 v11 specific implementations.
 */
abstract class ShortcutAndMountpointRedirectService extends AbstractShortcutAndMountpointRedirectService
{
    protected function createPageAccessFailureReasons(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ?ResponseInterface {
        $controller = $request->getAttribute('frontend.controller');
        return GeneralUtility::makeInstance(ErrorController::class)->pageNotFoundAction(
            $request,
            'Page of type "External URL" could not be resolved properly',
            /** @phpstan-ignore-next-line */
            $controller->getPageAccessFailureReasons(PageAccessFailureReasons::INVALID_EXTERNAL_URL)
        );
    }
}
