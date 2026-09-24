<?php

declare(strict_types=1);

namespace GlpiPlugin\Secret;

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Secret\Security\ClosedGenericAccess;
use GlpiPlugin\Secret\Security\GlpiKeyCipher;
use GlpiPlugin\Secret\Service\AssetTypeProvider;
use GlpiPlugin\Secret\Service\CentralSecretRepository;
use GlpiPlugin\Secret\Service\SecretAccessService;
use GlpiPlugin\Secret\Service\SecretInputValidator;
use GlpiPlugin\Secret\Service\SecretLinkService;
use Session;

final class Secret extends \CommonDBTM
{
    use ClosedGenericAccess;
    /** @var string */
    public static $rightname = Profile::RIGHT_METADATA;
    /** @var list<string> */
    public static $undisclosedFields = ['encrypted_value'];
    /** @var bool */
    public $dohistory = false;
    /** @var list<string> */
    public $history_blacklist = ['encrypted_value'];

    public const TYPE_PASSWORD = 'password';
    public const TYPE_CREDENTIAL = 'credential';
    public const TYPE_TOKEN = 'token';
    public const TYPE_OTHER = 'other';

    private static bool $centralUiEnabled = false;

    public static function enableCentralUi(): void
    {
        self::$centralUiEnabled = true;
    }

    public static function canView(): bool
    {
        return (self::$centralUiEnabled || self::isCentralTabRequest()) && Profile::canReadMetadata();
    }

    private static function isCentralTabRequest(): bool
    {
        $target = str_replace('\\', '/', (string) ($_GET['_target'] ?? ''));
        return (string) ($_GET['_itemtype'] ?? '') === self::class
            && str_ends_with($target, '/plugins/secret/front/secret.form.php');
    }

    /** @return array<string, int|list<int>> */
    public static function getSystemSQLCriteria(?string $tablename = null): array
    {
        $table = $tablename ?? self::getTable();
        if (!self::$centralUiEnabled) {
            return ["$table.id" => -1];
        }
        $ids = (new CentralSecretRepository())->visibleIds();
        return ["$table.id" => $ids !== [] ? $ids : -1];
    }

    /** @return list<array<string, mixed>> */
    public function rawSearchOptions(): array
    {
        $table = self::getTable();
        return [
            ['id' => 'common', 'name' => self::getTypeName(2)],
            ['id' => 1, 'table' => $table, 'field' => 'name', 'name' => __('Name'), 'datatype' => 'itemlink', 'massiveaction' => false],
            [
                'id' => 8,
                'table' => SecretItem::getTable(),
                'field' => 'itemtype',
                'name' => __('Linked item type', 'secret'),
                'datatype' => 'itemtypename',
                'forcegroupby' => true,
                'massiveaction' => false,
                'joinparams' => ['jointype' => 'child'],
            ],
            ['id' => 4, 'table' => 'glpi_entities', 'field' => 'completename', 'name' => __('Entity'), 'datatype' => 'dropdown', 'massiveaction' => false],
            ['id' => 2, 'table' => $table, 'field' => 'type', 'name' => __('Type'), 'massiveaction' => false],
            ['id' => 3, 'table' => Category::getTable(), 'field' => 'name', 'name' => __('Category'), 'datatype' => 'dropdown', 'massiveaction' => false],
            ['id' => 5, 'table' => $table, 'field' => 'expiration', 'name' => __('Expiration'), 'datatype' => 'datetime', 'massiveaction' => false],
            ['id' => 6, 'table' => $table, 'field' => 'date_creation', 'name' => __('Creation date'), 'datatype' => 'datetime', 'massiveaction' => false],
            ['id' => 7, 'table' => $table, 'field' => 'date_mod', 'name' => __('Last update'), 'datatype' => 'datetime', 'massiveaction' => false],
        ];
    }

    /**
     * @param array{itemtype?: string, prefs?: list<array<string, int|string>>} $options
     * @return array{itemtype?: string, prefs?: list<array<string, int|string>>}
     */
    public static function defaultDisplayPreferences(array $options): array
    {
        if (($options['itemtype'] ?? '') !== self::class) {
            return $options;
        }
        $options['prefs'] = self::defaultDisplayPreferenceRows();
        return $options;
    }

    /** @return list<array{itemtype: class-string<self>, num: int, rank: int, users_id: int, interface: string}> */
    public static function defaultDisplayPreferenceRows(): array
    {
        $rows = [];
        foreach ([1, 8, 4, 2, 3, 5, 6, 7] as $rank => $num) {
            $rows[] = [
                'itemtype' => self::class,
                'num' => $num,
                'rank' => $rank + 1,
                'users_id' => 0,
                'interface' => 'central',
            ];
        }
        return $rows;
    }

    /**
     * @param mixed $options
     * @return array<string, string|bool>
     */
    public function defineTabs($options = []): array
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(SecretRelationTab::class, $tabs, $options);
        $this->addStandardTab(SecretHistoryTab::class, $tabs, $options);
        return $tabs;
    }

    /**
     * @param mixed $id
     * @param array<string, mixed> $options
     */
    public function showForm($id, array $options = []): bool
    {
        $relation = (new CentralSecretRepository())->authorizedRelation($this);
        if ($relation === null) {
            return false;
        }
        TemplateRenderer::getInstance()->display('@secret/secret_form.html.twig', [
            'secret' => $this,
            'relation' => ['itemtype' => $relation[0], 'items_id' => $relation[1]],
            'type_label' => self::typeLabel((string) $this->fields['type']),
            'can_reveal' => (new SecretAccessService())->canReveal($this, $relation[2]),
            'can_update' => (new SecretAccessService())->canUpdate($this, $relation[2]),
            'can_delete' => (new SecretAccessService())->canDelete($this, $relation[2]),
            'generator' => Config::values(),
        ]);
        return true;
    }

    /**
     * @param mixed $checkitem
     * @return array<string, string>
     */
    public function getSpecificMassiveActions($checkitem = null): array
    {
        $actions = parent::getSpecificMassiveActions($checkitem);
        if (!Profile::canUpdateSecret()) {
            return $actions;
        }
        $actions[self::class . \MassiveAction::CLASS_ACTION_SEPARATOR . 'link_asset'] = __('Link to an asset', 'secret');
        if (Profile::canDeleteSecret()) {
            $actions[self::class . \MassiveAction::CLASS_ACTION_SEPARATOR . 'delete_secret'] = __('Delete permanently');
        }
        return $actions;
    }

    public static function showMassiveActionsSubForm(\MassiveAction $ma): bool
    {
        if ($ma->getAction() === 'delete_secret' && Profile::canDeleteSecret()) {
            echo "<div class='alert alert-warning'>" . \Html::entities_deep(__('Deleting a secret permanently breaks all its links. This action cannot be undone.', 'secret')) . '</div>';
            echo \Html::submit(_x('button', 'Delete permanently'), ['name' => 'massiveaction', 'class' => 'btn btn-danger']);
            return true;
        }
        if ($ma->getAction() !== 'link_asset' || !Profile::canUpdateSecret()) {
            return parent::showMassiveActionsSubForm($ma);
        }
        \Dropdown::showSelectItemFromItemtypes([
            'items_id_name' => 'items_id',
            'itemtype_name' => 'itemtype',
            'itemtypes' => (new AssetTypeProvider())->all(),
            'checkright' => true,
        ]);
        echo \Html::submit(_x('button', 'Post'), ['name' => 'massiveaction', 'class' => 'btn btn-primary']);
        return true;
    }

    /**
     * @param array<int, int> $ids
     */
    public static function processMassiveActionsForOneItemtype(\MassiveAction $ma, \CommonDBTM $item, array $ids): void
    {
        if ($ma->getAction() === 'delete_secret') {
            foreach ($ids as $id) {
                $secret = new self();
                try {
                    if (!$secret->getFromDB((int) $id)
                        || ($relation = (new CentralSecretRepository())->authorizedRelation($secret)) === null) {
                        throw new \RuntimeException('Access denied.');
                    }
                    (new Service\SecretMutationService())->delete($secret, $relation[0], $relation[1], $relation[2], 'central_massive_action');
                    $ma->itemDone($item->getType(), (int) $id, \MassiveAction::ACTION_OK);
                } catch (\Throwable) {
                    $ma->itemDone($item->getType(), (int) $id, \MassiveAction::ACTION_KO);
                }
            }
            return;
        }
        if ($ma->getAction() !== 'link_asset') {
            parent::processMassiveActionsForOneItemtype($ma, $item, $ids);
            return;
        }
        $input = $ma->getInput();
        $itemtype = is_string($input['itemtype'] ?? null) ? $input['itemtype'] : '';
        $itemsId = (int) ($input['items_id'] ?? 0);
        $asset = getItemForItemtype($itemtype);
        $targetIsValid = Profile::canUpdateSecret()
            && (new AssetTypeProvider())->supports($itemtype)
            && $asset instanceof \CommonDBTM
            && $asset->getFromDB($itemsId)
            && $asset->canViewItem();

        foreach ($ids as $id) {
            $secret = new self();
            try {
                if (!$targetIsValid || !$secret->getFromDB((int) $id)
                    || (new CentralSecretRepository())->authorizedRelation($secret) === null) {
                    throw new \RuntimeException('Access denied.');
                }
                (new SecretLinkService())->link($secret, $asset, (new Security\AssetActorResolver())->forItem($asset));
                $ma->itemDone($item->getType(), (int) $id, \MassiveAction::ACTION_OK);
            } catch (\Throwable) {
                $ma->itemDone($item->getType(), (int) $id, \MassiveAction::ACTION_KO);
            }
        }
    }

    /** @return array<string, string> */
    public static function cronInfo(string $name): array
    {
        return $name === 'purgeExpired' ? [
            'description' => __('Purge expired encrypted secrets after the configured retention', 'secret'),
            'parameter' => __('Retention (days)', 'secret'),
        ] : [];
    }

    public static function cronPurgeExpired(?\CronTask $task = null): int
    {
        return (new Service\ExpiredSecretPurger())->run($task);
    }

    /** @param int $nb */
    public static function getTypeName($nb = 0): string
    {
        return _n('Secret', 'Secrets', $nb, 'secret');
    }

    public static function getIcon(): string
    {
        return 'ti ti-key';
    }

    /** @return array<string, mixed> */
    public static function getMenuContent()
    {
        if (!Profile::canReadMetadata()) {
            return [];
        }
        $url = Config::pluginUrl() . '/front/secret.php';
        return [
            'title' => self::getTypeName(2),
            'page' => $url,
            'icon' => self::getIcon(),
            'links' => ['search' => $url],
        ];
    }

    /** @return list<string> */
    public static function types(): array
    {
        return [self::TYPE_PASSWORD, self::TYPE_CREDENTIAL, self::TYPE_TOKEN, self::TYPE_OTHER];
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_PASSWORD => __('Password', 'secret'),
            self::TYPE_CREDENTIAL => __('Username and password', 'secret'),
            self::TYPE_TOKEN => __('Token', 'secret'),
            self::TYPE_OTHER => __('Sensitive information', 'secret'),
            default => __('Unknown'),
        };
    }

    public function canViewItem(): bool
    {
        if (self::$centralUiEnabled || self::isCentralTabRequest()) {
            return (new CentralSecretRepository())->authorizedRelation($this) !== null;
        }
        return false;
    }

    public function canUpdateItem(): bool
    {
        return (new SecretAccessService())->canUpdate($this);
    }

    public function canDeleteItem(): bool
    {
        return (new SecretAccessService())->canDelete($this);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForAdd($input)
    {
        $plaintext = array_key_exists('_secret_value', $input) ? (string) $input['_secret_value'] : null;
        unset($input['_secret_value'], $input['encrypted_value'], $input['users_id_creator']);
        if ($plaintext === null || !(new SecretInputValidator())->valueIsValid($plaintext) || !$this->isValidInput($input)) {
            return false;
        }

        $input['encrypted_value'] = (new GlpiKeyCipher())->encrypt($plaintext);
        $input['users_id_creator'] = (int) Session::getLoginUserID();

        return $input;
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|false
     */
    public function prepareInputForUpdate($input)
    {
        $hasPlaintext = array_key_exists('_secret_value', $input);
        $plaintext = $hasPlaintext ? (string) $input['_secret_value'] : '';
        unset($input['_secret_value'], $input['encrypted_value'], $input['users_id_creator']);
        if (!$this->isValidInput(array_merge($this->fields, $input))) {
            return false;
        }
        if ($hasPlaintext && !(new SecretInputValidator())->valueIsValid($plaintext)) {
            return false;
        }
        if ($hasPlaintext) {
            $input['encrypted_value'] = (new GlpiKeyCipher())->encrypt($plaintext);
        }

        return $input;
    }

    /** @param array<string, mixed> $input */
    private function isValidInput(array $input): bool
    {
        return (new SecretInputValidator())->metadataIsValid($input);
    }
}
