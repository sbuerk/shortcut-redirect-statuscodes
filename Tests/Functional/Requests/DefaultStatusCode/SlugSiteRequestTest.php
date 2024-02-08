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

namespace StefanBuerk\ShortcutRedirectStatuscodes\Tests\Functional\Requests\DefaultStatusCode;

use Psr\Http\Message\UriInterface;
use StefanBuerk\ShortcutRedirectStatuscodes\Tests\Functional\Fixtures\Traits\PermutationMethodsTrait;
use StefanBuerk\ShortcutRedirectStatuscodes\Tests\Functional\Fixtures\Traits\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Scenario\DataHandlerFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Scenario\DataHandlerWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\ResponseContent;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SlugSiteRequestTest extends FunctionalTestCase
{
    use SiteBasedTestTrait;
    use PermutationMethodsTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8', 'iso' => 'en', 'hrefLang' => 'en-US', 'direction' => ''],
        'FR' => ['id' => 1, 'title' => 'French', 'locale' => 'fr_FR.UTF8', 'iso' => 'fr', 'hrefLang' => 'fr-FR', 'direction' => ''],
        'FR-CA' => ['id' => 2, 'title' => 'Franco-Canadian', 'locale' => 'fr_CA.UTF8', 'iso' => 'fr', 'hrefLang' => 'fr-CA', 'direction' => ''],
        'ES' => ['id' => 3, 'title' => 'Spanish', 'locale' => 'es_ES.UTF8', 'iso' => 'es', 'hrefLang' => 'es-ES', 'direction' => ''],
        'ZH-CN' => ['id' => 0, 'title' => 'Simplified Chinese', 'locale' => 'zh_CN.UTF-8', 'iso' => 'zh', 'hrefLang' => 'zh-Hans', 'direction' => ''],
        'ZH' => ['id' => 4, 'title' => 'Simplified Chinese', 'locale' => 'zh_CN.UTF-8', 'iso' => 'zh', 'hrefLang' => 'zh-Hans', 'direction' => ''],
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'devIPmask' => '123.123.123.123',
            'encryptionKey' => '4408d27a916d51e624b69af3554f516dbab61037a9f7b9fd6f81b4d3bedeccb6',
        ],
        'FE' => [
            'cacheHash' => [
                'requireCacheHashPresenceParameters' => ['value', 'testing[value]', 'tx_testing_link[value]'],
                'excludedParameters' => ['L', 'tx_testing_link[excludedValue]'],
                'enforceValidation' => true,
            ],
            'debug' => false,
        ],
    ];

    protected array $coreExtensionsToLoad = ['workspaces'];
    protected array $testExtensionsToLoad = ['sbuerk/shortcut-redirect-statuscodes'];

    protected function setUp(): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            $this->configurationToUseInTestInstance['SC_OPTIONS'] = [
                'Core/TypoScript/TemplateService' => [
                    'runThroughTemplatesPostProcessing' => [
                        'FunctionalTest' => \TYPO3\TestingFramework\Core\Functional\Framework\Frontend\Hook\TypoScriptInstructionModifier::class . '->apply',
                    ],
                ],
            ];
        }
        parent::setUp();
        $this->withDatabaseSnapshot(function () {
            $majorVersion = (new Typo3Version())->getMajorVersion();
            $this->importCSVDataSet(__DIR__ . '/Fixtures/be_users.csv');
            $backendUser = $this->setUpBackendUser(1);
            Bootstrap::initializeLanguageObject();
            $scenarioFile = sprintf(__DIR__ . '/Fixtures/SlugScenario%s.yaml', $majorVersion);
            $factory = DataHandlerFactory::fromYamlFile($scenarioFile);
            $writer = DataHandlerWriter::withBackendUser($backendUser);
            $writer->invokeFactory($factory);
            static::failIfArrayIsNotEmpty($writer->getErrors());
            $this->setUpFrontendRootPage(
                1000,
                [
                    'typo3conf/ext/shortcut_redirect_statuscodes/Tests/Functional/Fixtures/Frontend/JsonRenderer.typoscript',
                    'typo3conf/ext/shortcut_redirect_statuscodes/Tests/Functional/Requests/Fixtures/Frontend/JsonRenderer.typoscript',
                ],
                [
                    'title' => 'ACME Root',
                ]
            );
        });
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function requestsAreRedirectedWithoutHavingDefaultSiteLanguageDataProvider(): array
    {
        $domainPaths = [
            'https://website.local/',
            'https://website.local/?',
            // @todo: See how core should act here and activate this or have an own test for this scenario
            // 'https://website.local//',
        ];
        return self::wrapInArray(
            self::keysFromValues($domainPaths)
        );
    }

    /**
     * @test
     * @dataProvider requestsAreRedirectedWithoutHavingDefaultSiteLanguageDataProvider
     */
    public function requestsAreRedirectedWithoutHavingDefaultSiteLanguage(string $uri): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/en-en/'),
            ]
        );
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/')
        );

        $expectedStatusCode = 307;
        $expectedHeaders = [
            'X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'],
            'location' => ['https://website.local/welcome'],
        ];

        $response = $this->executeFrontendSubRequest(new InternalRequest($uri));
        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame($expectedHeaders, $response->getHeaders());
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function shortcutsAreRedirectedDataProvider(): array
    {
        $domainPaths = [
            'https://website.local/',
            'https://website.local/?',
            // @todo: See how core should act here and activate this or have an own test for this scenario
            // 'https://website.local//',
        ];
        return self::wrapInArray(
            self::keysFromValues($domainPaths)
        );
    }

    /**
     * @test
     * @dataProvider shortcutsAreRedirectedDataProvider
     */
    public function shortcutsAreRedirectedToDefaultSiteLanguage(string $uri): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/en-en/'),
            ]
        );

        $expectedStatusCode = 307;
        $expectedHeaders = [
            'location' => ['https://website.local/en-en/'],
        ];

        $response = $this->executeFrontendSubRequest(new InternalRequest($uri));
        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame($expectedHeaders, $response->getHeaders());
    }

    /**
     * @test
     * @dataProvider shortcutsAreRedirectedDataProvider
     */
    public function shortcutsAreRedirectedAndRenderFirstSubPage(string $uri): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/en-en/'),
            ]
        );

        $expectedStatusCode = 200;
        $expectedPageTitle = 'EN: Welcome';

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            null,
            true
        );
        $responseStructure = ResponseContent::fromString(
            (string)$response->getBody()
        );

        self::assertSame(
            $expectedStatusCode,
            $response->getStatusCode()
        );
        self::assertSame(
            $expectedPageTitle,
            $responseStructure->getScopePath('page/title')
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function shortcutsAreRedirectedDataProviderWithChineseCharacterInBase(): array
    {
        $domainPaths = [
            'https://website.local/简',
            'https://website.local/简?',
            'https://website.local/简/',
            'https://website.local/简/?',
        ];
        return self::wrapInArray(
            self::keysFromValues($domainPaths)
        );
    }

    /**
     * @test
     * @dataProvider shortcutsAreRedirectedDataProviderWithChineseCharacterInBase
     */
    public function shortcutsAreRedirectedToDefaultSiteLanguageWithChineseCharacterInBase(string $uri): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/简/'),
            [
                $this->buildDefaultLanguageConfiguration('ZH-CN', '/'),
            ]
        );

        $expectedStatusCode = 307;
        $expectedHeaders = [
            'X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'],
            // We cannot expect 简 here directly, as they are rawurlencoded() in the used Symfony UrlGenerator.
            'location' => ['https://website.local/%E7%AE%80/welcome'],
        ];

        $response = $this->executeFrontendSubRequest(new InternalRequest($uri));
        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame($expectedHeaders, $response->getHeaders());
    }

    /**
     * @test
     * @dataProvider shortcutsAreRedirectedDataProviderWithChineseCharacterInBase
     */
    public function shortcutsAreRedirectedAndRenderFirstSubPageWithChineseCharacterInBase(string $uri): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/简/'),
            [
                $this->buildDefaultLanguageConfiguration('ZH-CN', '/'),
            ]
        );

        $expectedStatusCode = 200;
        $expectedPageTitle = 'EN: Welcome';

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            null,
            true
        );
        $responseStructure = ResponseContent::fromString(
            (string)$response->getBody()
        );

        self::assertSame(
            $expectedStatusCode,
            $response->getStatusCode()
        );
        self::assertSame(
            $expectedPageTitle,
            $responseStructure->getScopePath('page/title')
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function pageIsRenderedWithPathsDataProvider(): array
    {
        $domainPaths = [
            'https://website.local/en-en/welcome',
            'https://website.local/fr-fr/bienvenue',
            'https://website.local/fr-ca/bienvenue',
            'https://website.local/简/简-bienvenue',
        ];
        return array_map(
            /** @param int|string $uri */
            static function ($uri) {
                $uri = (string)$uri;
                if (str_contains($uri, '/fr-fr/')) {
                    $expectedPageTitle = 'FR: Welcome';
                } elseif (str_contains($uri, '/fr-ca/')) {
                    $expectedPageTitle = 'FR-CA: Welcome';
                } elseif (str_contains($uri, '/简/')) {
                    $expectedPageTitle = 'ZH-CN: Welcome';
                } else {
                    $expectedPageTitle = 'EN: Welcome';
                }
                return [$uri, $expectedPageTitle];
            },
            self::keysFromValues($domainPaths)
        );
    }

    /**
     * @test
     * @dataProvider pageIsRenderedWithPathsDataProvider
     */
    public function pageIsRenderedWithPaths(string $uri, string $expectedPageTitle): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/en-en/'),
                $this->buildLanguageConfiguration('FR', '/fr-fr/', ['EN']),
                $this->buildLanguageConfiguration('FR-CA', '/fr-ca/', ['FR', 'EN']),
                $this->buildLanguageConfiguration('ZH', '/简/', ['EN']),
            ]
        );

        $response = $this->executeFrontendSubRequest(new InternalRequest($uri));
        $responseStructure = ResponseContent::fromString(
            (string)$response->getBody()
        );

        self::assertSame(
            200,
            $response->getStatusCode()
        );
        self::assertSame(
            $expectedPageTitle,
            $responseStructure->getScopePath('page/title')
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function pageIsRenderedWithPathsAndChineseDefaultLanguageDataProvider(): array
    {
        $domainPaths = [
            'https://website.local/简/简-bienvenue',
            'https://website.local/fr-fr/zh-bienvenue',
            'https://website.local/fr-ca/zh-bienvenue',
        ];
        return array_map(
            /** @param int|string $uri */
            static function ($uri) {
                $uri = (string)$uri;
                if (str_contains($uri, '/fr-fr/')) {
                    $expectedPageTitle = 'FR: Welcome ZH Default';
                } elseif (str_contains($uri, '/fr-ca/')) {
                    $expectedPageTitle = 'FR-CA: Welcome ZH Default';
                } else {
                    $expectedPageTitle = 'ZH-CN: Welcome Default';
                }
                return [$uri, $expectedPageTitle];
            },
            self::keysFromValues($domainPaths)
        );
    }

    /**
     * @test
     * @dataProvider pageIsRenderedWithPathsAndChineseDefaultLanguageDataProvider
     */
    public function pageIsRenderedWithPathsAndChineseDefaultLanguage(string $uri, string $expectedPageTitle): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [
                $this->buildDefaultLanguageConfiguration('ZH-CN', '/简/'),
                $this->buildLanguageConfiguration('FR', '/fr-fr/', ['EN']),
                $this->buildLanguageConfiguration('FR-CA', '/fr-ca/', ['FR', 'EN']),
            ]
        );

        $response = $this->executeFrontendSubRequest(new InternalRequest($uri));
        $responseStructure = ResponseContent::fromString(
            (string)$response->getBody()
        );

        self::assertSame(
            200,
            $response->getStatusCode()
        );
        self::assertSame(
            $expectedPageTitle,
            $responseStructure->getScopePath('page/title')
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function pageIsRenderedWithDomainsDataProvider(): array
    {
        $domainPaths = [
            'https://website.us/welcome',
            'https://website.fr/bienvenue',
            'https://website.ca/bienvenue',
            // Explicitly testing chinese character domains
            'https://website.简/简-bienvenue',
        ];
        return array_map(
            /** @param int|string $uri */
            static function ($uri) {
                $uri = (string)$uri;
                if (str_contains($uri, '.fr/')) {
                    $expectedPageTitle = 'FR: Welcome';
                } elseif (str_contains($uri, '.ca/')) {
                    $expectedPageTitle = 'FR-CA: Welcome';
                } elseif (str_contains($uri, '.简/')) {
                    $expectedPageTitle = 'ZH-CN: Welcome';
                } else {
                    $expectedPageTitle = 'EN: Welcome';
                }
                return [$uri, $expectedPageTitle];
            },
            self::keysFromValues($domainPaths)
        );
    }

    /**
     * @test
     * @dataProvider pageIsRenderedWithDomainsDataProvider
     */
    public function pageIsRenderedWithDomains(string $uri, string $expectedPageTitle): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', 'https://website.us/'),
                $this->buildLanguageConfiguration('FR', 'https://website.fr/', ['EN']),
                $this->buildLanguageConfiguration('FR-CA', 'https://website.ca/', ['FR', 'EN']),
                $this->buildLanguageConfiguration('ZH', 'https://website.简/', ['EN']),
            ]
        );

        $response = $this->executeFrontendSubRequest(new InternalRequest($uri));
        $responseStructure = ResponseContent::fromString(
            (string)$response->getBody()
        );

        self::assertSame(
            200,
            $response->getStatusCode()
        );
        self::assertSame(
            $expectedPageTitle,
            $responseStructure->getScopePath('page/title')
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function restrictedPageIsRenderedDataProvider(): array
    {
        $instructions = [
            // frontend user 1
            ['https://website.local/my-acme/whitepapers', 1, 'Whitepapers'],
            ['https://website.local/my-acme/whitepapers/products', 1, 'Products'],
            ['https://website.local/my-acme/whitepapers/solutions', 1, 'Solutions'],
            // frontend user 2
            ['https://website.local/my-acme/whitepapers', 2, 'Whitepapers'],
            ['https://website.local/my-acme/whitepapers/products', 2, 'Products'],
            ['https://website.local/my-acme/whitepapers/research', 2, 'Research'],
            ['https://website.local/my-acme/forecasts', 2, 'Forecasts'],
            ['https://website.local/my-acme/forecasts/current-year', 2, 'Current Year'],
            // frontend user 3
            ['https://website.local/my-acme/whitepapers', 3, 'Whitepapers'],
            ['https://website.local/my-acme/whitepapers/products', 3, 'Products'],
            ['https://website.local/my-acme/whitepapers/solutions', 3, 'Solutions'],
            ['https://website.local/my-acme/whitepapers/research', 3, 'Research'],
            ['https://website.local/my-acme/forecasts', 3, 'Forecasts'],
            ['https://website.local/my-acme/forecasts/current-year', 3, 'Current Year'],
        ];
        return self::keysFromTemplate($instructions, '%1$s (user:%2$s)');
    }

    /**
     * @test
     * @dataProvider restrictedPageIsRenderedDataProvider
     */
    public function restrictedPageIsRendered(string $uri, int $frontendUserId, string $expectedPageTitle): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/')
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );
        $responseStructure = ResponseContent::fromString(
            (string)$response->getBody()
        );

        self::assertSame(
            200,
            $response->getStatusCode()
        );
        self::assertSame(
            $expectedPageTitle,
            $responseStructure->getScopePath('page/title')
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function restrictedPageWithParentSysFolderIsRenderedDataProvider(): array
    {
        $instructions = [
            // frontend user 4
            ['https://website.local/sysfolder-restricted', 4, 'FEGroups Restricted'],
        ];
        return self::keysFromTemplate($instructions, '%1$s (user:%2$s)');
    }

    /**
     * @test
     * @dataProvider restrictedPageWithParentSysFolderIsRenderedDataProvider
     */
    public function restrictedPageWithParentSysFolderIsRendered(string $uri, int $frontendUserId, string $expectedPageTitle): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/')
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );
        $responseStructure = ResponseContent::fromString(
            (string)$response->getBody()
        );

        self::assertSame(
            200,
            $response->getStatusCode()
        );
        self::assertSame(
            $expectedPageTitle,
            $responseStructure->getScopePath('page/title')
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function restrictedPageSendsForbiddenResponseWithUnauthorizedVisitorDataProvider(): array
    {
        $instructions = [
            // no frontend user given
            ['https://website.local/my-acme/whitepapers', 0],
            // ['https://website.local/my-acme/whitepapers/products', 0], // @todo extendToSubpages currently missing
            ['https://website.local/my-acme/whitepapers/solutions', 0],
            ['https://website.local/my-acme/whitepapers/research', 0],
            ['https://website.local/my-acme/forecasts', 0],
            // ['https://website.local/my-acme/forecasts/current-year', 0], // @todo extendToSubpages currently missing
            // frontend user 1
            ['https://website.local/my-acme/whitepapers/research', 1],
            ['https://website.local/my-acme/forecasts', 1],
            // ['https://website.local/my-acme/forecasts/current-year', 1], // @todo extendToSubpages currently missing
            // frontend user 2
            ['https://website.local/my-acme/whitepapers/solutions', 2],
        ];
        return self::keysFromTemplate($instructions, '%1$s (user:%2$s)');
    }

    /**
     * @test
     * @dataProvider restrictedPageSendsForbiddenResponseWithUnauthorizedVisitorDataProvider
     */
    public function restrictedPageSendsForbiddenResponseWithUnauthorizedVisitorWithoutHavingErrorHandling(string $uri, int $frontendUserId): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/')
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );

        self::assertSame(
            403,
            $response->getStatusCode()
        );
        self::assertThat(
            (string)$response->getBody(),
            self::logicalOr(
                self::stringContains('Reason: ID was not an accessible page'),
                self::stringContains('Reason: Subsection was found and not accessible')
            )
        );
    }

    /**
     * @test
     * @dataProvider restrictedPageSendsForbiddenResponseWithUnauthorizedVisitorDataProvider
     */
    public function restrictedPageSendsForbiddenResponseWithUnauthorizedVisitorWithHavingFluidErrorHandling(string $uri, int $frontendUserId): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [],
            $this->buildErrorHandlingConfiguration('Fluid', [403])
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );

        self::assertSame(
            403,
            $response->getStatusCode()
        );
        self::assertStringContainsString(
            'reasons: code,fe_group',
            (string)$response->getBody()
        );
        self::assertThat(
            (string)$response->getBody(),
            self::logicalOr(
                self::stringContains('message: ID was not an accessible page'),
                self::stringContains('message: Subsection was found and not accessible')
            )
        );
    }

    /**
     * @test
     * @dataProvider restrictedPageSendsForbiddenResponseWithUnauthorizedVisitorDataProvider
     */
    public function restrictedPageSendsForbiddenResponseWithUnauthorizedVisitorWithHavingPageErrorHandling(string $uri, int $frontendUserId): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped('Skipped until PageContentErrorHandler::handlePageError does not use HTTP anymore');
        }
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [],
            $this->buildErrorHandlingConfiguration('Page', [403])
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );

        self::assertSame(
            403,
            $response->getStatusCode()
        );
        self::assertThat(
            (string)$response->getBody(),
            self::logicalOr(
                self::stringContains('That page is forbidden to you'),
                self::stringContains('ID was not an accessible page'),
                self::stringContains('Subsection was found and not accessible')
            )
        );
    }

    /**
     * @test
     * @dataProvider restrictedPageSendsForbiddenResponseWithUnauthorizedVisitorDataProvider
     */
    public function restrictedPageSendsForbiddenResponseWithUnauthorizedVisitorWithHavingPhpErrorHandling(string $uri, int $frontendUserId): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [],
            $this->buildErrorHandlingConfiguration('PHP', [403])
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );
        /** @var array<string, string|null> $json */
        $json = json_decode((string)$response->getBody(), true);

        self::assertSame(
            403,
            $response->getStatusCode()
        );
        self::assertThat(
            $json['message'] ?? null,
            self::logicalOr(
                self::identicalTo('ID was not an accessible page'),
                self::identicalTo('Subsection was found and not accessible')
            )
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function restrictedPageWithParentSysFolderSendsForbiddenResponseWithUnauthorizedVisitorDataProvider(): array
    {
        $instructions = [
            // no frontend user given
            ['https://website.local/sysfolder-restricted', 0],
            // frontend user 1
            ['https://website.local/sysfolder-restricted', 1],
            // frontend user 2
            ['https://website.local/sysfolder-restricted', 2],
            // frontend user 3
            ['https://website.local/sysfolder-restricted', 3],
        ];
        return self::keysFromTemplate($instructions, '%1$s (user:%2$s)');
    }

    /**
     * @test
     * @dataProvider restrictedPageWithParentSysFolderSendsForbiddenResponseWithUnauthorizedVisitorDataProvider
     */
    public function restrictedPageWithParentSysFolderSendsForbiddenResponseWithUnauthorizedVisitorWithoutHavingErrorHandling(string $uri, int $frontendUserId): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/')
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );

        self::assertSame(
            403,
            $response->getStatusCode()
        );
        self::assertThat(
            (string)$response->getBody(),
            self::logicalOr(
                self::stringContains('Reason: ID was not an accessible page'),
                self::stringContains('Reason: Subsection was found and not accessible')
            )
        );
    }

    /**
     * @test
     * @dataProvider restrictedPageWithParentSysFolderSendsForbiddenResponseWithUnauthorizedVisitorDataProvider
     */
    public function restrictedPageWithParentSysFolderSendsForbiddenResponseWithUnauthorizedVisitorWithHavingFluidErrorHandling(string $uri, int $frontendUserId): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [],
            $this->buildErrorHandlingConfiguration('Fluid', [403])
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );

        self::assertSame(
            403,
            $response->getStatusCode()
        );
        self::assertStringContainsString(
            'reasons: code,fe_group',
            (string)$response->getBody()
        );
        self::assertThat(
            (string)$response->getBody(),
            self::logicalOr(
                self::stringContains('message: ID was not an accessible page'),
                self::stringContains('message: Subsection was found and not accessible')
            )
        );
    }

    /**
     * @test
     * @dataProvider restrictedPageWithParentSysFolderSendsForbiddenResponseWithUnauthorizedVisitorDataProvider
     */
    public function restrictedPageWithParentSysFolderSendsForbiddenResponseWithUnauthorizedVisitorWithHavingPageErrorHandling(string $uri, int $frontendUserId): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped('Skipped until PageContentErrorHandler::handlePageError does not use HTTP anymore');
        }
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [],
            $this->buildErrorHandlingConfiguration('Page', [403])
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );

        self::assertSame(
            403,
            $response->getStatusCode()
        );
        self::assertThat(
            (string)$response->getBody(),
            self::logicalOr(
                self::stringContains('That page is forbidden to you'),
                self::stringContains('ID was not an accessible page'),
                self::stringContains('Subsection was found and not accessible')
            )
        );
    }

    /**
     * @test
     * @dataProvider restrictedPageWithParentSysFolderSendsForbiddenResponseWithUnauthorizedVisitorDataProvider
     */
    public function restrictedPageWithParentSysFolderSendsForbiddenResponseWithUnauthorizedVisitorWithHavingPhpErrorHandling(string $uri, int $frontendUserId): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [],
            $this->buildErrorHandlingConfiguration('PHP', [403])
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );
        /** @var array<string, string|null> $json */
        $json = json_decode((string)$response->getBody(), true);

        self::assertSame(
            403,
            $response->getStatusCode()
        );
        self::assertThat(
            $json['message'] ?? null,
            self::logicalOr(
                self::identicalTo('ID was not an accessible page'),
                self::identicalTo('Subsection was found and not accessible')
            )
        );
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function hiddenPageSends404ResponseRegardlessOfVisitorGroupDataProvider(): array
    {
        $instructions = [
            // hidden page, always 404
            ['https://website.local/never-visible-working-on-it', 0],
            ['https://website.local/never-visible-working-on-it', 1],
            // hidden fe group restricted and fegroup generally okay
            ['https://website.local/sysfolder-restricted-hidden', 4],
        ];
        return self::keysFromTemplate($instructions, '%1$s (user:%2$s)');
    }

    /**
     * @test
     * @dataProvider hiddenPageSends404ResponseRegardlessOfVisitorGroupDataProvider
     */
    public function hiddenPageSends404ResponseRegardlessOfVisitorGroup(string $uri, int $frontendUserId): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [],
            $this->buildErrorHandlingConfiguration('PHP', [404])
        );

        $response = $this->executeFrontendSubRequest(
            new InternalRequest($uri),
            (new InternalRequestContext())->withFrontendUserId($frontendUserId)
        );
        /** @var array<string, string|null> $json */
        $json = json_decode((string)$response->getBody(), true);

        self::assertSame(
            404,
            $response->getStatusCode()
        );
        self::assertThat(
            $json['message'] ?? null,
            self::identicalTo('The requested page does not exist!')
        );
    }

    public static function crossSiteShortcutsAreRedirectedDataProvider(): \Generator
    {
        yield 'shortcut is redirected' => [
            'https://website.local/cross-site-shortcut',
            307,
            [
                'X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'],
                'location' => ['https://blog.local/authors'],
            ],
        ];
        if ((new Typo3Version())->getMajorVersion() >= 12) {
            yield 'shortcut of translated page is redirected to a different page than the original page' => [
                'https://website.local/fr/other-cross-site-shortcut',
                307,
                [
                    'X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'],
                    'location' => ['https://website.local/fr/acme-dans-votre-region'],
                ],
            ];
        }
    }

    /**
     * @test
     * @dataProvider crossSiteShortcutsAreRedirectedDataProvider
     * @param array<non-empty-string, string[]> $expectedHeaders
     */
    public function crossSiteShortcutsAreRedirected(string $uri, int $expectedStatusCode, array $expectedHeaders): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
                $this->buildLanguageConfiguration('FR', '/fr/', ['EN']),
            ]
        );
        $this->writeSiteConfiguration(
            'blog-local',
            $this->buildSiteConfiguration(2000, 'https://blog.local/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/'),
                $this->buildLanguageConfiguration('FR', '/fr/', ['EN']),
            ]
        );
        $this->setUpFrontendRootPage(
            2000,
            [
                'typo3conf/ext/shortcut_redirect_statuscodes/Tests/Functional/Fixtures/Frontend/JsonRenderer.typoscript',
                'typo3conf/ext/shortcut_redirect_statuscodes/Tests/Functional/Requests/Fixtures/Frontend/JsonRenderer.typoscript',
            ],
            [
                'title' => 'ACME Blog',
            ]
        );

        $response = $this->executeFrontendSubRequest(new InternalRequest($uri));
        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame($expectedHeaders, $response->getHeaders());
    }

    public static function pageIsRenderedForVersionedPageDataProvider(): \Generator
    {
        yield 'Live page with logged-in user' => [
            'url' => 'https://website.local/en-en/welcome',
            'pageTitle' => 'EN: Welcome',
            'Online Page ID' => 1100,
            'Workspace ID' => 0,
            'Backend User ID' => 1,
            'statusCode' => 200,
        ];
        yield 'Live page with logged-in user accessed even though versioned page slug was changed' => [
            'url' => 'https://website.local/en-en/welcome',
            'pageTitle' => 'EN: Welcome to ACME Inc',
            'Online Page ID' => 1100,
            'Workspace ID' => 1,
            'Backend User ID' => 1,
            'statusCode' => 200,
        ];
        yield 'Versioned page with logged-in user and modified slug' => [
            'url' => 'https://website.local/en-en/welcome-modified',
            'pageTitle' => 'EN: Welcome to ACME Inc',
            'Online Page ID' => 1100,
            'Workspace ID' => 1,
            'Backend User ID' => 1,
            'statusCode' => 200,
        ];
        yield 'Versioned page without logged-in user renders 404' => [
            'url' => 'https://website.local/en-en/welcome-modified',
            'pageTitle' => null,
            'Online Page ID' => null,
            'Workspace ID' => 1,
            'Backend User ID' => 0,
            'statusCode' => 404,
        ];
    }

    /**
     * @test
     * @dataProvider pageIsRenderedForVersionedPageDataProvider
     */
    public function pageIsRenderedForVersionedPage(string $url, ?string $expectedPageTitle, ?int $expectedPageId, int $workspaceId, int $backendUserId, int $expectedStatusCode): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            // @todo Remove conditional skip if TYPO3 v12 is minimal supported version.
            self::markTestSkipped('Skipped as tested behaviour is only valid or TYPO3 v12 and newer');
        }
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/en-en/'),
                $this->buildLanguageConfiguration('FR', '/fr-fr/', ['EN']),
                $this->buildLanguageConfiguration('FR-CA', '/fr-ca/', ['FR', 'EN']),
            ]
        );
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest($url)),
            (new InternalRequestContext())
                ->withWorkspaceId($backendUserId !== 0 ? $workspaceId : 0)
                ->withBackendUserId($backendUserId)
        );
        $responseStructure = ResponseContent::fromString(
            (string)$response->getBody()
        );

        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame($expectedPageId, $responseStructure->getScopePath('page/uid'));
        self::assertSame($expectedPageTitle, $responseStructure->getScopePath('page/title'));
    }
    public static function getUrisWithInvalidLegacyQueryParameters(): \Generator
    {
        $uri = new Uri('https://website.local/welcome/');
        yield '#0 id with float value having a zero decimal' => [
            'uri' => $uri->withQuery(HttpUtility::buildQueryString(['id' => '1110.0'])),
        ];
        yield '#1 id string value with tailing numbers' => [
            'uri' => $uri->withQuery(HttpUtility::buildQueryString(['id' => 'step1110'])),
        ];
        yield '#2 id string value with leading numbers' => [
            'uri' => $uri->withQuery(HttpUtility::buildQueryString(['id' => '1110step'])),
        ];
        yield '#3 id string value without numbers' => [
            'uri' => $uri->withQuery(HttpUtility::buildQueryString(['id' => 'foobar'])),
        ];
        yield '#4 id string value with a exponent' => [
            'uri' => $uri->withQuery(HttpUtility::buildQueryString(['id' => '11e10'])),
        ];
        yield '#5 id with a zero as value' => [
            'uri' => $uri->withQuery(HttpUtility::buildQueryString(['id' => 0])),
        ];
    }

    /**
     * @test
     * @dataProvider getUrisWithInvalidLegacyQueryParameters
     */
    public function requestWithInvalidLegacyQueryParametersDisplayPageNotFoundPage(UriInterface $uri): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            self::markTestSkipped('Skipped as tested behaviour is only valid or TYPO3 v12 and newer');
        }
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [],
            $this->buildErrorHandlingConfiguration('PHP', [404])
        );
        $response = $this->executeFrontendSubRequest(
            new InternalRequest((string)$uri),
            new InternalRequestContext()
        );
        /** @var array<string, string|null> $json */
        $json = json_decode((string)$response->getBody(), true);
        self::assertSame(404, $response->getStatusCode());
        self::assertThat(
            $json['message'] ?? null,
            self::stringContains('The requested page does not exist')
        );
    }
}
