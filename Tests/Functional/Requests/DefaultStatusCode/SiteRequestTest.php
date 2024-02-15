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

namespace StefanBuerk\ShortcutRedirectStatuscodes\Tests\Functional\Requests\DefaultStatusCode;

use Psr\Http\Message\UriInterface;
use StefanBuerk\ShortcutRedirectStatuscodes\Tests\Functional\Fixtures\Traits\PermutationMethodsTrait;
use StefanBuerk\ShortcutRedirectStatuscodes\Tests\Functional\Fixtures\Traits\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\Bootstrap;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\PermutationUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Scenario\DataHandlerFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\DataHandling\Scenario\DataHandlerWriter;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\ResponseContent;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class SiteRequestTest extends FunctionalTestCase
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
            $scenarioFile = sprintf(__DIR__ . '/Fixtures/PlainScenario%s.yaml', $majorVersion);
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
    public static function shortcutsAreRedirectedDataProvider(): array
    {
        $domainPaths = [
            // @todo Implicit strict mode handling when calling non-existent site
            // '/',
            // 'https://localhost/',
            'https://website.local/',
        ];
        $queries = [
            '',
        ];
        return self::wrapInArray(
            self::keysFromValues(
                PermutationUtility::meltStringItems([$domainPaths, $queries])
            )
        );
    }

    /**
     * @test
     * @dataProvider shortcutsAreRedirectedDataProvider
     */
    public function shortcutsAreRedirectedToFirstSubPage(string $uri): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [
                $this->buildDefaultLanguageConfiguration('EN', '/en-en/'),
            ]
        );

        $expectedStatusCode = 307;
        $expectedHeaders = ['location' => ['https://website.local/en-en/']];

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
    public static function pageIsRenderedWithPathsDataProvider(): array
    {
        $domainPaths = [
            // @todo currently base needs to be defined with domain
            // '/',
            'https://website.local/',
        ];
        $languagePaths = [
            'en-en/',
            'fr-fr/',
            'fr-ca/',
            '简/',
        ];
        $queries = [
            '?id=1100',
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
            self::keysFromValues(
                PermutationUtility::meltStringItems([$domainPaths, $languagePaths, $queries])
            )
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
    public function pageIsRenderedWithPathsAndChineseDefaultLanguageDataProvider(): array
    {
        $domainPaths = [
            // @todo currently base needs to be defined with domain
            // '/',
            'https://website.local/',
        ];

        $languagePaths = [
            '简/',
            'fr-fr/',
            'fr-ca/',
        ];

        $queries = [
            '?id=1110',
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
            $this->keysFromValues(
                PermutationUtility::meltStringItems([$domainPaths, $languagePaths, $queries])
            )
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
    public function pageIsRenderedWithPathsAndChineseBaseDataProvider(): array
    {
        return [
            ['https://website.local/简/简/?id=1110', 'ZH-CN: Welcome Default'],
        ];
    }

    /**
     * @test
     * @dataProvider pageIsRenderedWithPathsAndChineseBaseDataProvider
     */
    public function pageIsRenderedWithPathsAndChineseBase(string $uri, string $expectedPageTitle): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/简/'),
            [
                $this->buildDefaultLanguageConfiguration('ZH-CN', '/简/'),
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
    public function restrictedPageIsRenderedDataProvider(): array
    {
        $instructions = [
            // frontend user 1
            ['https://website.local/?id=1510', 1, 'Whitepapers'],
            ['https://website.local/?id=1511', 1, 'Products'],
            ['https://website.local/?id=1512', 1, 'Solutions'],
            // frontend user 2
            ['https://website.local/?id=1510', 2, 'Whitepapers'],
            ['https://website.local/?id=1511', 2, 'Products'],
            ['https://website.local/?id=1515', 2, 'Research'],
            ['https://website.local/?id=1520', 2, 'Forecasts'],
            ['https://website.local/?id=1521', 2, 'Current Year'],
            // frontend user 3
            ['https://website.local/?id=1510', 3, 'Whitepapers'],
            ['https://website.local/?id=1511', 3, 'Products'],
            ['https://website.local/?id=1512', 3, 'Solutions'],
            ['https://website.local/?id=1515', 3, 'Research'],
            ['https://website.local/?id=1520', 3, 'Forecasts'],
            ['https://website.local/?id=1521', 3, 'Current Year'],
            // frontend user 1 with index
            ['https://website.local/index.php?id=1510', 1, 'Whitepapers'],
            ['https://website.local/index.php?id=1511', 1, 'Products'],
            ['https://website.local/index.php?id=1512', 1, 'Solutions'],
            // frontend user 2
            ['https://website.local/index.php?id=1510', 2, 'Whitepapers'],
            ['https://website.local/index.php?id=1511', 2, 'Products'],
            ['https://website.local/index.php?id=1515', 2, 'Research'],
            ['https://website.local/index.php?id=1520', 2, 'Forecasts'],
            ['https://website.local/index.php?id=1521', 2, 'Current Year'],
            // frontend user 3
            ['https://website.local/index.php?id=1510', 3, 'Whitepapers'],
            ['https://website.local/index.php?id=1511', 3, 'Products'],
            ['https://website.local/index.php?id=1512', 3, 'Solutions'],
            ['https://website.local/index.php?id=1515', 3, 'Research'],
            ['https://website.local/index.php?id=1520', 3, 'Forecasts'],
            ['https://website.local/index.php?id=1521', 3, 'Current Year'],
        ];

        return $this->keysFromTemplate($instructions, '%1$s (user:%2$s)');
    }

    /**
     * @param string $uri
     * @param int $frontendUserId
     * @param string $expectedPageTitle
     *
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
    public static function restrictedPageSendsForbiddenResponseWithUnauthorizedVisitorDataProvider(): array
    {
        $instructions = [
            // no frontend user given
            ['https://website.local/?id=1510', 0],
            ['https://website.local/?id=1511', 0],
            ['https://website.local/?id=1512', 0],
            ['https://website.local/?id=1515', 0],
            ['https://website.local/?id=1520', 0],
            ['https://website.local/?id=1521', 0],
            ['https://website.local/?id=2021', 0],
            // frontend user 1
            ['https://website.local/?id=1515', 1],
            ['https://website.local/?id=1520', 1],
            ['https://website.local/?id=1521', 1],
            ['https://website.local/?id=2021', 1],
            // frontend user 2
            ['https://website.local/?id=1512', 2],
            ['https://website.local/?id=2021', 2],
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
    public static function restrictedPageWithParentSysFolderIsRenderedDataProvider(): array
    {
        $instructions = [
            // frontend user 4
            ['https://website.local/?id=2021', 4, 'FEGroups Restricted'],
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
    public static function restrictedPageWithParentSysFolderSendsForbiddenResponseWithUnauthorizedVisitorDataProvider(): array
    {
        $instructions = [
            // no frontend user given
            ['https://website.local/?id=2021', 0],
            // frontend user 1
            ['https://website.local/?id=2021', 1],
            // frontend user 2
            ['https://website.local/?id=2021', 2],
            // frontend user 3
            ['https://website.local/?id=2021', 3],
        ];
        return self::keysFromTemplate($instructions, '%1$s (user:%2$s)');
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
            ['https://website.local/?id=1800', 0],
            ['https://website.local/?id=1800', 1],
            // hidden fe group restricted and fegroup generally okay
            ['https://website.local/?id=2022', 4],
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

    /**
     * @return array<int|string, mixed>
     */
    public static function checkIfIndexPhpReturnsShortcutRedirectWithPageIdAndTypeNumProvidedDataProvider(): array
    {
        $domainPaths = [
            'https://website.local/',
            'https://website.local/index.php',
        ];
        $queries = [
            '',
            '?id=1000',
            '?type=0',
            '?id=1000&type=0',
        ];
        return self::wrapInArray(
            self::keysFromValues(
                PermutationUtility::meltStringItems([$domainPaths, $queries])
            )
        );
    }

    /**
     * @test
     * @dataProvider checkIfIndexPhpReturnsShortcutRedirectWithPageIdAndTypeNumProvidedDataProvider
     */
    public function checkIfIndexPhpReturnsShortcutRedirectWithPageIdAndTypeNumProvided(string $uri): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/')
        );

        $expectedStatusCode = 307;
        $expectedHeaders = ['X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'], 'location' => ['https://website.local/en-welcome']];

        $response = $this->executeFrontendSubRequest(new InternalRequest($uri));
        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame($expectedHeaders, $response->getHeaders());
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function crossSiteShortcutsAreRedirectedDataProvider(): array
    {
        return [
            'shortcut is redirected #1' => [
                'https://website.local/index.php?id=2030',
                307,
                [
                    'X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'],
                    'location' => ['https://blog.local/authors'],
                ],
            ],
            'shortcut is redirected #2' => [
                'https://website.local/?id=2030',
                307,
                [
                    'X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'],
                    'location' => ['https://blog.local/authors'],
                ],
            ],
            'shortcut is redirected #3' => [
                'https://website.local/index.php?id=2030&type=0',
                307,
                [
                    'X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'],
                    'location' => ['https://blog.local/authors'],
                ],
            ],
            'shortcut is redirected #4' => [
                'https://website.local/?id=2030&type=0',
                307,
                [
                    'X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'],
                    'location' => ['https://blog.local/authors'],
                ],
            ],
            'shortcut is redirected #5' => [
                'https://website.local/?id=2030&type=1',
                307,
                [
                    'X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'],
                    'location' => ['https://blog.local/authors?type=1'],
                ],
            ],
            // @todo check why this is not working - albeit in core it works
//            'shortcut is redirected #6' => [
//                'https://website.local/?id=2030&type=1&additional=value',
//                307,
//                [
//                    'X-Redirect-By' => ['SBUERK Shortcut/Mountpoint'],
//                    'location' => ['https://blog.local/authors?additional=value&type=1&cHash=9a534a0ab3d092ac113a3d8b5ea577ba'],
//                ],
//            ],
        ];
    }

    /**
     * @test
     * @dataProvider crossSiteShortcutsAreRedirectedDataProvider
     *
     * @param array<string, string[]> $expectedHeaders
     */
    public function crossSiteShortcutsAreRedirected(string $uri, int $expectedStatusCode, array $expectedHeaders): void
    {
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
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/')
        );
        $this->writeSiteConfiguration(
            'blog-local',
            $this->buildSiteConfiguration(2000, 'https://blog.local/')
        );

        $response = $this->executeFrontendSubRequest(new InternalRequest($uri));
        self::assertSame($expectedStatusCode, $response->getStatusCode());
        self::assertSame($expectedHeaders, $response->getHeaders());
    }

    /**
     * @return array<int|string, mixed>
     */
    public static function crossSiteShortcutsWithWrongSiteHostSendsPageNotFoundWithoutHavingErrorHandlingDataProvider(): array
    {
        return [
            'shortcut requested by id on wrong site #1' => [
                'https://blog.local/index.php?id=2030',
            ],
            'shortcut requested by id on wrong site #2' => [
                'https://blog.local/?id=2030',
            ],
            'shortcut requested by id on wrong site #3' => [
                'https://blog.local/index.php?id=2030&type=0',
            ],
            'shortcut requested by id on wrong site #4' => [
                'https://blog.local/?id=2030&type=0',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider crossSiteShortcutsWithWrongSiteHostSendsPageNotFoundWithoutHavingErrorHandlingDataProvider
     */
    public function crossSiteShortcutsWithWrongSiteHostSendsPageNotFoundWithoutHavingErrorHandling(string $uri): void
    {
        $this->writeSiteConfiguration(
            'website-local',
            $this->buildSiteConfiguration(1000, 'https://website.local/'),
            [],
            $this->buildErrorHandlingConfiguration('PHP', [404])
        );
        $this->writeSiteConfiguration(
            'blog-local',
            $this->buildSiteConfiguration(2000, 'https://blog.local/'),
            [],
            $this->buildErrorHandlingConfiguration('PHP', [404])
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
        /** @var array<string, string|null> $json */
        $json = json_decode((string)$response->getBody(), true);
        self::assertSame(404, $response->getStatusCode());
        self::assertThat(
            $json['message'] ?? null,
            self::stringContains('ID was outside the domain')
        );
    }
    public static function getUrisWithInvalidLegacyQueryParameters(): \Generator
    {
        $uri = new Uri('https://website.local/');
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
     * @see https://review.typo3.org/c/Packages/TYPO3.CMS/+/82814
     */
    public function requestWithInvalidLegacyQueryParametersDisplayPageNotFoundPage(UriInterface $uri): void
    {
        if ((new Typo3Version())->getMajorVersion() < 12) {
            // @todo Remove conditional skip if TYPO3 v12 is minimal supported version.
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
