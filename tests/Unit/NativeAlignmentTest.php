<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret\Tests\Unit;

use GlpiPlugin\Secret\Category;
use GlpiPlugin\Secret\Profile;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\Security\AclContext;
use GlpiPlugin\Secret\Security\Visibility;
use GlpiPlugin\Secret\Service\CentralSecretRepository;
use GlpiPlugin\Secret\Service\SecretMutationService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class NativeAlignmentTest extends TestCase
{
    protected function setUp(): void
    {
        require dirname(__DIR__) . '/Fixtures/glpi-runtime.php';
        $_SESSION = ['glpigroups' => []];
    }

    public function testMassiveActionsRespectIndependentUpdateAndDeleteRights(): void
    {
        $secret = new Secret();
        $prefix = Secret::class . \MassiveAction::CLASS_ACTION_SEPARATOR;
        \Session::$rights = [Profile::RIGHT_DELETE => DELETE];
        self::assertSame([$prefix . 'delete_secret'], array_keys($secret->getSpecificMassiveActions()));
        \Session::$rights = [Profile::RIGHT_UPDATE => UPDATE];
        self::assertSame([$prefix . 'link_asset'], array_keys($secret->getSpecificMassiveActions()));
        \Session::$rights = [];
        self::assertSame([], $secret->getSpecificMassiveActions());
    }

    public function testCentralListBatchesRelationsAndKeepsAclAndNativeObjectChecks(): void
    {
        global $DB, $CFG_GLPI;
        \Session::$rights = [Profile::RIGHT_METADATA => READ];
        $CFG_GLPI = ['asset_types' => [\Computer::class]];
        \CommonDBTM::$records[\Computer::class] = [
            10 => ['id' => 10, 'viewable' => false],
            11 => ['id' => 11, 'viewable' => true],
        ];
        $row = [
            'id' => 1, 'visibility' => Visibility::OWNER, 'users_id_creator' => 7,
            'entities_id' => 2, 'itemtype' => \Computer::class, 'items_id' => 10,
        ];
        $DB = new class ([
            $row,
            array_replace($row, ['items_id' => 11]),
            array_replace($row, ['id' => 2, 'items_id' => 11, 'users_id_creator' => 8]),
            array_replace($row, ['id' => 3, 'items_id' => 11]),
            array_replace($row, ['id' => 3, 'items_id' => 10]),
        ]) {
            public array $queries = [];
            public function __construct(private array $rows) {}
            public function request(array $query): \ArrayIterator
            {
                $this->queries[] = $query;
                return new \ArrayIterator($this->rows);
            }
        };
        $repository = new CentralSecretRepository();
        self::assertSame([1, 3], $repository->visibleIds());
        self::assertCount(1, $DB->queries);
        self::assertArrayHasKey('JOIN', $DB->queries[0]);
        self::assertSame([Secret::class . '.entities_id' => 2], $DB->queries[0]['WHERE']);
        self::assertStringNotContainsString('encrypted_value', json_encode($DB->queries[0], JSON_THROW_ON_ERROR));
        self::assertSame([[\Computer::class, 10], [\Computer::class, 11]], \CommonDBTM::$loads);

        // Permission changes must never inherit previously resolved contexts.
        \CommonDBTM::$records[\Computer::class][11]['viewable'] = false;
        self::assertSame([], $repository->visibleIds());
        \Session::$rights = [];
        self::assertSame([], $repository->visibleIds());
        self::assertCount(2, $DB->queries);
    }

    public function testSharedSecretCategoryUsesItsOwnEntity(): void
    {
        global $DB;
        \Session::$rights = [Profile::RIGHT_UPDATE => UPDATE];
        \CommonDBTM::$records[Category::class][20] = ['id' => 20, 'entities_id' => 2];
        $DB = new class {
            public function beginTransaction(): void {}
            public function commit(): void {}
            public function insert(string $table, array $input): bool
            {
                return true;
            }
        };
        $secret = new Secret();
        $secret->fields = ['id' => 1, 'entities_id' => 2, 'visibility' => Visibility::OWNER, 'users_id_creator' => 7];
        (new SecretMutationService())->update(
            $secret,
            ['plugin_secret_categories_id' => 20],
            \Computer::class,
            10,
            new AclContext(userId: 7, assetItemAccess: true),
        );
        self::assertSame(20, \CommonDBTM::$updates[0]['plugin_secret_categories_id']);
        self::assertSame([[Category::class, 20]], \CommonDBTM::$loads);
    }

    public function testCategoryFromUnrelatedEntityIsRejectedBeforeWrite(): void
    {
        \Session::$rights = [Profile::RIGHT_UPDATE => UPDATE];
        \CommonDBTM::$records[Category::class][20] = ['id' => 20, 'entities_id' => 3];
        $secret = new Secret();
        $secret->fields = ['id' => 1, 'entities_id' => 2, 'visibility' => Visibility::OWNER, 'users_id_creator' => 7];
        try {
            (new SecretMutationService())->update($secret, ['plugin_secret_categories_id' => 20], \Computer::class, 10);
            self::fail('An unrelated category must be refused.');
        } catch (\RuntimeException $exception) {
            self::assertSame('Invalid category.', $exception->getMessage());
        }
        self::assertSame([], \CommonDBTM::$updates);
    }
}
