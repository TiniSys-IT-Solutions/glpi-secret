<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PluginIntegrationContractTest extends TestCase
{
    public function testGlpiEntryPointsArePackagedAndRegistered(): void
    {
        $root = dirname(__DIR__, 2);
        $setup = (string) file_get_contents($root . '/setup.php');

        self::assertFileExists($root . '/front/config.php');
        self::assertFileExists($root . '/logo.png');
        self::assertStringContainsString("[Hooks::CONFIG_PAGE]['secret'] = 'front/config.php'", $setup);
        self::assertStringContainsString("[Hooks::USE_MASSIVE_ACTION]['secret'] = true", $setup);
        self::assertStringContainsString('secret\\.form', $setup);
        self::assertStringContainsString("[Hooks::MENU_TOADD]['secret']", $setup);
        self::assertStringContainsString('AssetTypeProvider', $setup);
        self::assertStringContainsString('AssetRelationLifecycle::beforePurge', $setup);
        self::assertStringContainsString("[Hooks::TIMELINE_ANSWER_ACTIONS]['secret']", $setup);
        self::assertStringContainsString("[Hooks::TIMELINE_ITEMS]['secret']", $setup);
        self::assertStringContainsString("registerJavascriptFile('js/secret.js')", $setup);
        self::assertStringContainsString("registerCSSFile('css/secret.css')", $setup);
        self::assertStringContainsString('TimelineActionProvider::actions(...)', $setup);
        self::assertStringContainsString('\\Profile::$helpdesk_rights', $setup);
        self::assertStringContainsString("array_column(Profile::rights(), 'field')", $setup);
        self::assertStringContainsString('refreshActiveProfileRights()', $setup);

        $legacyConfig = (string) file_get_contents($root . '/front/config.php');
        self::assertStringNotContainsString('Session::checkCSRF(', $legacyConfig);
        self::assertStringContainsString('SecretConfig::save($_POST)', $legacyConfig);
        self::assertStringNotContainsString('ProfileRightsPresetService', $legacyConfig);
        self::assertStringNotContainsString('preview_profile_rights', $legacyConfig);

        foreach (['CreateItilSecretController.php', 'CreateAssetSecretController.php', 'RevealSecretController.php', 'MutateItilSecretController.php', 'MutateAssetSecretController.php', 'MutateCentralSecretController.php', 'AuditSecretController.php'] as $controller) {
            $source = (string) file_get_contents($root . '/src/Controller/' . $controller);
            self::assertStringContainsString('#[SecurityStrategy(Firewall::STRATEGY_AUTHENTICATED)]', $source);
        }

        $provider = (string) file_get_contents($root . '/src/Service/TimelineActionProvider.php');
        self::assertStringContainsString("\$actions['PluginSecretSecret']", $provider);
        self::assertStringContainsString("'type' => 'ITILFollowup'", $provider);
        self::assertStringContainsString("'class' => 'PluginSecretSecret'", $provider);
    }

    public function testPluginTemplatesUseCanonicalGlpiPluginPaths(): void
    {
        $root = dirname(__DIR__, 2);
        $timeline = (string) file_get_contents($root . '/templates/timeline_form.html.twig');
        $tab = (string) file_get_contents($root . '/templates/itil_tab.html.twig');

        self::assertStringContainsString("path('plugins/secret/Itil/Secret')", $timeline);
        self::assertStringContainsString("path('plugins/secret/Secret/' ~ secret.id ~ '/Reveal')", $tab);
        self::assertStringNotContainsString("path('@secret:", $timeline . $tab);

        $javascript = (string) file_get_contents($root . '/public/js/secret.js');
        self::assertStringContainsString('/plugins/secret/Itil/Secret', $javascript);
        self::assertStringContainsString("form.method = 'post'", $javascript);
        self::assertStringContainsString('reportActionFailure', $javascript);
        self::assertStringContainsString('csrfInput.value = csrf', $javascript);
        self::assertStringContainsString("document.execCommand('copy')", $javascript);
        self::assertStringContainsString('entry.user_login', $javascript);
        self::assertSame(2, substr_count($javascript, "'X-Glpi-Csrf-Token': csrf"));
        self::assertSame(2, substr_count($javascript, "'X-Requested-With': 'XMLHttpRequest'"));

        $relation = (string) file_get_contents($root . '/src/SecretItem.php');
        self::assertStringContainsString("return 'ti ti-key'", $relation);

        $profiles = (string) file_get_contents($root . '/src/Install/ProfileRightSynchronizer.php');
        self::assertStringContainsString("\$interface === 'helpdesk'", $profiles);
        self::assertStringContainsString("['Hotliner', 'Observer', 'Technician', 'Supervisor']", $profiles);

        $timelineCard = (string) file_get_contents($root . '/templates/timeline_secret.html.twig');
        self::assertStringContainsString('plugin-secret-timeline-content', $timelineCard);
        self::assertStringContainsString('data-action="view"', $timelineCard);
        self::assertStringContainsString('csrf_token(true)', $timelineCard);
        self::assertStringNotContainsString('encrypted_value', $timelineCard);

        $stylesheet = (string) file_get_contents($root . '/public/css/secret.css');
        self::assertStringContainsString('.timeline-item.PluginSecretSecret .timeline-content', $stylesheet);
        self::assertStringContainsString('.timeline-item.plugin-secret-timeline-item .timeline-content', $stylesheet);
    }

    public function testProfileRightsAreNormalizedToBooleanValues(): void
    {
        $profile = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Profile.php');

        self::assertSame(7, substr_count($profile, 'return (bool) Session::haveRight('));
    }

    public function testAssetAccessUsesNativeRightsWithoutProfileSelector(): void
    {
        $root = dirname(__DIR__, 2);
        $config = (string) file_get_contents($root . '/src/Config.php');
        $template = (string) file_get_contents($root . '/templates/config_form.html.twig');

        self::assertStringContainsString('activeProfileCanAccessAssets', $config);
        self::assertStringContainsString('return Profile::canReadMetadata()', $config);
        self::assertStringNotContainsString('asset_technical_profile_ids', $config . $template);
        self::assertStringNotContainsString('preview_profile_rights', $template);
    }

    public function testRevealFailsWhenItsAuditCannotBeRecorded(): void
    {
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Service/SecretValueService.php');

        self::assertStringContainsString("if (!\$this->audit->record(", $service);
        self::assertStringContainsString('The secret access could not be audited.', $service);
    }

    public function testRevealControllerUsesOneClosedFailureResponse(): void
    {
        $controller = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Controller/RevealSecretController.php');

        self::assertStringContainsString('catch (\\RuntimeException)', $controller);
        self::assertStringNotContainsString('$exception->getMessage()', $controller);
        self::assertGreaterThanOrEqual(3, substr_count($controller, 'AccessDeniedHttpException'));
    }

    public function testSecretHistoryAndUninstallRetentionAreExplicit(): void
    {
        $root = dirname(__DIR__, 2);
        $secret = (string) file_get_contents($root . '/src/Secret.php');
        $installer = (string) file_get_contents($root . '/src/Install/Installer.php');

        self::assertStringContainsString('public $dohistory = false', $secret);
        self::assertStringContainsString("CronTask::unregister('Secret')", $installer);
        self::assertStringNotContainsString("DROP TABLE", $installer);
    }

    public function testAllSecretActionsUseGlpiEntityScope(): void
    {
        $root = dirname(__DIR__, 2);
        $access = (string) file_get_contents($root . '/src/Service/SecretAccessService.php');
        $creation = (string) file_get_contents($root . '/src/Service/CreateSecretService.php');

        self::assertStringContainsString('Session::haveAccessToEntity(', $access);
        self::assertStringContainsString("!\$context->assetItemAccess", $access);
        self::assertStringContainsString('canCreateForAsset($item)', $creation);
        self::assertStringContainsString('canCreateForItil($item)', $creation);
        self::assertStringContainsString("'is_recursive' => 0", $creation);

        $relation = (string) file_get_contents($root . '/src/SecretItem.php');
        self::assertStringContainsString("Session::getCurrentInterface() === 'helpdesk'", $relation);
    }

    public function testAdministratorOverrideKeepsLayeredAuthorization(): void
    {
        $root = dirname(__DIR__, 2);
        $config = (string) file_get_contents($root . '/src/Config.php');
        $access = (string) file_get_contents($root . '/src/Service/SecretAccessService.php');
        $template = (string) file_get_contents($root . '/templates/config_form.html.twig');

        self::assertStringContainsString("'admin_acl_bypass' => '0'", $config);
        self::assertStringContainsString("'admin_acl_bypass' => !empty(\$input['admin_acl_bypass'])", $config);
        self::assertStringContainsString('Profile::canAdminister()', $access);
        self::assertStringContainsString("Config::values()['admin_acl_bypass']", $access);
        self::assertStringContainsString('admin_acl_bypass', $template);
    }

    public function testSecretTypesDriveDedicatedSafeInterfaces(): void
    {
        $root = dirname(__DIR__, 2);
        $secret = (string) file_get_contents($root . '/src/Secret.php');
        $form = (string) file_get_contents($root . '/templates/timeline_form.html.twig');
        $javascript = (string) file_get_contents($root . '/public/js/secret.js');

        self::assertStringContainsString("self::TYPE_OTHER => __('Sensitive information', 'secret')", $secret);
        self::assertStringContainsString('plugin-secret-username-field', $form);
        self::assertStringContainsString('plugin-secret-sensitive-value', $form);
        self::assertStringContainsString("type === 'other'", $javascript);
        self::assertStringNotContainsString('value="{{ secret.', $form);
    }

    public function testItilMutationsAuditAndSafeNotificationAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $tab = (string) file_get_contents($root . '/templates/itil_tab.html.twig');
        $detail = (string) file_get_contents($root . '/templates/secret_form.html.twig');
        $notifier = (string) file_get_contents($root . '/src/Service/SecretAvailabilityNotifier.php');
        $mutation = (string) file_get_contents($root . '/src/Service/SecretMutationService.php');

        self::assertStringNotContainsString("/Mutate')", $tab);
        self::assertStringContainsString('plugins/secret/Central/Secret/', $detail);
        self::assertStringContainsString('available for this ticket. Sign in to GLPI to view it.', $notifier);
        self::assertStringContainsString('available for this change. Sign in to GLPI to view it.', $notifier);
        self::assertStringContainsString('available for this problem. Sign in to GLPI to view it.', $notifier);
        self::assertStringNotContainsString('secret_value', $notifier);
        self::assertStringContainsString('AuditLogger::UPDATE', $mutation);
        self::assertStringContainsString('AuditLogger::DELETE', $mutation);
    }

    public function testTimelineCardsUseARealNotificationSafeItemType(): void
    {
        $root = dirname(__DIR__, 2);
        $actions = (string) file_get_contents($root . '/src/Service/TimelineActionProvider.php');
        $items = (string) file_get_contents($root . '/src/Service/TimelineItemProvider.php');
        $type = (string) file_get_contents($root . '/src/TimelineSecret.php');

        self::assertStringContainsString("'type' => TimelineSecret::class", $actions);
        self::assertStringContainsString("'type' => TimelineSecret::class", $items);
        self::assertStringContainsString("'content' => ''", $items);
        self::assertStringContainsString('final class TimelineSecret extends \\CommonGLPI', $type);
        self::assertStringNotContainsString("'type' => 'PluginSecretTimelineSecret'", $actions . $items);
    }

    public function testAssetAndCentralSurfacesStayDedicatedAndMetadataOnly(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['front/secret.php', 'front/secret.form.php', 'front/category.php', 'front/category.form.php', 'templates/asset_tab.html.twig', 'templates/secret_form.html.twig', 'templates/secret_relations.html.twig', 'templates/secret_history.html.twig'] as $path) {
            self::assertFileExists($root . '/' . $path);
        }

        $assetTypes = (string) file_get_contents($root . '/src/Service/AssetTypeProvider.php');
        $assetRepository = (string) file_get_contents($root . '/src/Service/AssetSecretRepository.php');
        $centralRepository = (string) file_get_contents($root . '/src/Service/CentralSecretRepository.php');
        $links = (string) file_get_contents($root . '/src/Service/SecretLinkService.php');
        $assetTemplate = (string) file_get_contents($root . '/templates/asset_tab.html.twig');
        $centralTemplates = (string) file_get_contents($root . '/templates/secret_form.html.twig')
            . (string) file_get_contents($root . '/templates/secret_relations.html.twig')
            . (string) file_get_contents($root . '/templates/secret_history.html.twig');

        self::assertStringContainsString("\$CFG_GLPI['asset_types']", $assetTypes);
        self::assertStringNotContainsString('encrypted_value', $assetRepository . $centralRepository . $assetTemplate . $centralTemplates);
        self::assertStringContainsString('Search::show(Secret::class)', (string) file_get_contents($root . '/front/secret.php'));
        self::assertStringNotContainsString('forcetoview', (string) file_get_contents($root . '/front/secret.php'));
        self::assertStringContainsString('getSpecificMassiveActions', (string) file_get_contents($root . '/src/Secret.php'));
        self::assertStringContainsString("'link_asset'", (string) file_get_contents($root . '/src/Secret.php'));
        self::assertStringContainsString('defaultDisplayPreferenceRows', (string) file_get_contents($root . '/src/Secret.php'));
        self::assertStringContainsString('Hooks::DEFAULT_DISPLAY_PREFS', (string) file_get_contents($root . '/setup.php'));
        self::assertStringContainsString('metadataCriteriaForAsset', $assetRepository);
        self::assertStringContainsString('AuditLogger::LINK', $links);
        self::assertStringContainsString('AuditLogger::UNLINK', $links);
        self::assertStringContainsString("countElementsInTable(SecretItem::getTable()", $links);
        self::assertStringNotContainsString("'asset_technical_profile_ids'", (string) file_get_contents($root . '/src/Config.php'));
    }
}
