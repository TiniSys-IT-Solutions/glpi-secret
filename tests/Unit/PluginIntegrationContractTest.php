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
        self::assertStringContainsString("registerJavascriptFile('js/secret.js')", $setup);
        self::assertStringContainsString("registerCSSFile('css/secret.css')", $setup);
        self::assertStringContainsString('TimelineActionProvider::actions(...)', $setup);

        $provider = (string) file_get_contents($root . '/src/Service/TimelineActionProvider.php');
        self::assertStringContainsString("'PluginSecretSecret' => [", $provider);
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
