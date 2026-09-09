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
        self::assertStringNotContainsString("[Hooks::MENU_TOADD]['secret']", $setup);
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

        foreach (['CreateItilSecretController.php', 'RevealSecretController.php', 'MutateItilSecretController.php', 'AuditSecretController.php'] as $controller) {
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
        self::assertStringContainsString('.timeline-item.PluginSecretTimelineSecret .timeline-content', $stylesheet);
    }

    public function testProfileRightsAreNormalizedToBooleanValues(): void
    {
        $profile = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Profile.php');

        self::assertSame(7, substr_count($profile, 'return (bool) Session::haveRight('));
    }

    public function testRevealFailsWhenItsAuditCannotBeRecorded(): void
    {
        $service = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Service/SecretValueService.php');

        self::assertStringContainsString("if (!\$this->audit->record(", $service);
        self::assertStringContainsString('The secret access could not be audited.', $service);
    }

    public function testAllSecretActionsUseGlpiEntityScope(): void
    {
        $root = dirname(__DIR__, 2);
        $access = (string) file_get_contents($root . '/src/Service/SecretAccessService.php');
        $creation = (string) file_get_contents($root . '/src/Service/CreateSecretService.php');

        self::assertStringContainsString('Session::haveAccessToEntity(', $access);
        self::assertStringContainsString('if (!$entityAllowed && !($context->itilItemAccess ?? false))', $access);
        self::assertStringContainsString('canCreateForItil($item)', $creation);
        self::assertStringContainsString("'is_recursive' => 0", $creation);

        $relation = (string) file_get_contents($root . '/src/SecretItem.php');
        self::assertStringContainsString("Session::getCurrentInterface() === 'helpdesk'", $relation);
    }

    public function testItilMutationsAuditAndSafeNotificationAreWired(): void
    {
        $root = dirname(__DIR__, 2);
        $tab = (string) file_get_contents($root . '/templates/itil_tab.html.twig');
        $notifier = (string) file_get_contents($root . '/src/Service/SecretAvailabilityNotifier.php');
        $mutation = (string) file_get_contents($root . '/src/Service/SecretMutationService.php');

        self::assertStringContainsString("/Mutate')", $tab);
        self::assertStringContainsString("/Audit')", $tab);
        self::assertStringContainsString('available for this ticket. Sign in to GLPI to view it.', $notifier);
        self::assertStringContainsString('available for this change. Sign in to GLPI to view it.', $notifier);
        self::assertStringContainsString('available for this problem. Sign in to GLPI to view it.', $notifier);
        self::assertStringNotContainsString('secret_value', $notifier);
        self::assertStringContainsString('AuditLogger::UPDATE', $mutation);
        self::assertStringContainsString('AuditLogger::DELETE', $mutation);
    }
}
