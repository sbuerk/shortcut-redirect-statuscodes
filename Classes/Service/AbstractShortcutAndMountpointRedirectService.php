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

namespace StefanBuerk\ShortcutRedirectStatuscodes\Service;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Dedicated shortcut and mountpoint redirect service which is used in the simplified middleware, to ease
 * the implementation of TYPO3 core version based adoption in the future switched within DependencyInjection
 * configuration.
 *
 * @see \StefanBuerk\ShortcutRedirectStatuscodes\Http\Middleware\ShortcutAndMountPointRedirectMiddleware
 */
abstract class AbstractShortcutAndMountpointRedirectService implements ShortcutAndMountPointRedirectServiceInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    protected Typo3Version $typo3Version;

    public function __construct(Typo3Version $typo3Version)
    {
        $this->typo3Version = $typo3Version;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $exposeInformation = $GLOBALS['TYPO3_CONF_VARS']['FE']['exposeRedirectInformation'] ?? false;

        // Check for shortcut page and mount point redirect
        try {
            $redirectToUri = $this->getRedirectUri($request);
        } catch (ImmediateResponseException $e) {
            return $e->getResponse();
        }
        if ($redirectToUri !== null && $redirectToUri !== (string)$request->getUri()) {
            /** @var PageArguments $pageArguments */
            $pageArguments = $request->getAttribute('routing', null);
            $message = 'SBUERK Shortcut/Mountpoint' . ($exposeInformation ? ' at page with ID ' . $pageArguments->getPageId() : '');
            return new RedirectResponse(
                $redirectToUri,
                307,
                ['X-Redirect-By' => $message]
            );
        }

        // See if the current page is of doktype "External URL", if so, do a redirect as well.
        /** @var TypoScriptFrontendController $controller */
        $controller = $request->getAttribute('frontend.controller');
        if (!$this->disablePageExternalUrl($controller)
            && (int)$controller->page['doktype'] === PageRepository::DOKTYPE_LINK
        ) {
            /** @var NormalizedParams $normalizedParams */
            $normalizedParams = $request->getAttribute('normalizedParams');
            $externalUrl = $this->prefixExternalPageUrl(
                $controller->page['url'],
                $normalizedParams->getSiteUrl()
            );
            $message = 'SBUERK External URL' . ($exposeInformation ? ' at page with ID ' . $controller->page['uid'] : '');
            if (!empty($externalUrl)) {
                return new RedirectResponse(
                    $externalUrl,
                    303,
                    ['X-Redirect-By' => $message]
                );
            }
            if ($this->logger instanceof LoggerInterface) {
                $this->logger->error(
                    'Page of type "External URL" could not be resolved properly',
                    [
                        'page' => $controller->page,
                    ]
                );
            }
            if ($pageAccessFailureResponse = $this->createPageAccessFailureReasons($request, $handler)) {
                return $pageAccessFailureResponse;
            }
        }
        return $handler->handle($request);
    }

    /**
     * @deprecated since 1.0, will be removed with 2.0.
     * @see https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/12.0/Breaking-96522-ConfigdisablePageExternalUrlRemoved.html
     *
     * @return bool
     */
    protected function disablePageExternalUrl(TypoScriptFrontendController $controller): bool
    {
        if ($this->typo3Version->getMajorVersion() < 12) {
            return !empty($controller->config['config']['disablePageExternalUrl'] ?? null);
        }
        return true;
    }

    /**
     * Create a response. If no repsonse is returned, normal stack is executed.
     */
    abstract protected function createPageAccessFailureReasons(ServerRequestInterface $request, RequestHandlerInterface $handler): ?ResponseInterface;

    protected function getRedirectUri(ServerRequestInterface $request): ?string
    {
        /** @var TypoScriptFrontendController $controller */
        $controller = $request->getAttribute('frontend.controller');
        $redirectToUri = $controller->getRedirectUriForShortcut($request);
        if ($redirectToUri !== null) {
            return $redirectToUri;
        }
        return $controller->getRedirectUriForMountPoint($request);
    }

    protected function prefixExternalPageUrl(string $redirectTo, string $sitePrefix): string
    {
        $uI = parse_url($redirectTo);
        // If relative path, prefix Site URL
        // If it's a valid email without protocol, add "mailto:"
        if (!($uI['scheme'] ?? false)) {
            if (GeneralUtility::validEmail($redirectTo)) {
                $redirectTo = 'mailto:' . $redirectTo;
            } elseif (!str_starts_with($redirectTo, '/')) {
                $redirectTo = $sitePrefix . $redirectTo;
            }
        }
        return $redirectTo;
    }
}
