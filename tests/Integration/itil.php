<?php

declare(strict_types=1);

// This runner mutates only an explicitly marked disposable GLPI installation.
$root = getenv('SECRET_TEST_GLPI_ROOT');
if (!$root || !is_file($root . '/.secret-disposable-test')) {
    fwrite(STDERR, "Set SECRET_TEST_GLPI_ROOT to a disposable GLPI installation with .secret-disposable-test.\n");
    exit(2);
}
session_save_path($root . '/files/_sessions');
require $root . '/vendor/autoload.php';
$kernel = new Glpi\Kernel\Kernel();
$kernel->boot();
set_exception_handler(static function (Throwable $error): void {
    $message = str_starts_with($error->getMessage(), 'FAIL:') ? $error->getMessage() : $error::class;
    fwrite(STDERR, $message . "\n");
    exit(1);
});
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
if (getenv('SECRET_TEST_KEY_REGISTRATION') !== false) {
    $registered = in_array('glpi_plugin_secret_secrets.encrypted_value', (new GLPIKey())->getFields(), true);
    $expected = getenv('SECRET_TEST_KEY_REGISTRATION') === '1';
    echo $registered === $expected ? "Key registration matches plugin state.\n" : "Key registration mismatch.\n";
    exit($registered === $expected ? 0 : 1);
}


use GlpiPlugin\Secret\Profile as SecretProfile;
use GlpiPlugin\Secret\Secret;
use GlpiPlugin\Secret\SecretItem;
use GlpiPlugin\Secret\SecretLog;
use GlpiPlugin\Secret\Security\ItilActorResolver;
use GlpiPlugin\Secret\Service\CreateSecretService;
use GlpiPlugin\Secret\Service\SecretAccessService;
use GlpiPlugin\Secret\Service\SecretAvailabilityNotifier;
use GlpiPlugin\Secret\Service\SecretMutationService;
use GlpiPlugin\Secret\Service\SecretValueService;
use GlpiPlugin\Secret\Service\TicketSecretRepository;

$checks = 0;
function verify(bool $condition, string $label): void
{
    global $checks;
    if (!$condition) {
        throw new RuntimeException('FAIL: ' . $label);
    }
    ++$checks;
    echo "PASS: $label\n";
}

// Synthetic session, initialized through native GLPI, never a production login.
$auth = new Auth();
$auth->user = new User();
$auth->user->getFromDB(2);
$auth->auth_succeded = true;
Session::init($auth);
Config::setConfigurationValues('core', ['use_notifications' => 0, 'notifications_mailing' => 0]);
$CFG_GLPI['use_notifications'] = 0;
$CFG_GLPI['notifications_mailing'] = 0;
(new GlpiPlugin\Secret\Install\ProfileRightSynchronizer())->refreshActiveProfileRights();
verify(SecretProfile::canCreateSecret(), 'installation supplies native profile rights');
foreach ([Secret::class, SecretItem::class, SecretLog::class] as $model) {
    foreach (['canView', 'canCreate', 'canUpdate', 'canDelete', 'canPurge'] as $method) {
        verify(!$model::$method(), "$model rejects generic $method");
    }
}
// Intercept only the API response transport; execute GLPI's actual API methods.
$api = new class extends Glpi\Api\APIRest {
    public function returnError($message = 'Bad Request', $httpcode = 400, $statuscode = 'ERROR', $docmessage = true, $return_response = true)
    {
        throw new RuntimeException('api-denied');
    }
    public function listing(string $type)
    {
        return $this->getItems($type);
    }
    public function creation(string $type, array $input)
    {
        return $this->createItems($type, ['input' => (object) $input]);
    }
};
foreach ([Secret::class, SecretItem::class, SecretLog::class] as $model) {
    try {
        $api->listing($model);
        verify(false, 'API list must fail');
    } catch (RuntimeException $e) {
        verify($e->getMessage() === 'api-denied', "$model native API listing denied");
    }
    try {
        $api->creation($model, ['name' => 'synthetic']);
        verify(false, 'API create must fail');
    } catch (RuntimeException $e) {
        verify($e->getMessage() === 'api-denied', "$model native API creation denied");
    }
}
$csrfListener = new Glpi\Kernel\Listener\ControllerListener\CheckCsrfListener(new Glpi\Http\SessionManager());
$csrfRequest = Symfony\Component\HttpFoundation\Request::create('/plugins/secret/Itil/Secret', 'POST');
$csrfEvent = new Symfony\Component\HttpKernel\Event\ControllerEvent($kernel, static fn() => null, $csrfRequest, Symfony\Component\HttpKernel\HttpKernelInterface::MAIN_REQUEST);
$denied = false;
try {
    $csrfListener->onKernelController($csrfEvent);
} catch (Glpi\Exception\Http\AccessDeniedHttpException) {
    $denied = true;
}
verify($denied, 'native CSRF listener rejects a missing token');
$csrfRequest->request->set('_glpi_csrf_token', Session::getNewCSRFToken());
$csrfListener->onKernelController($csrfEvent);
verify(true, 'native CSRF listener accepts a valid form token');
$csrfRequest->headers->set('X-Requested-With', 'XMLHttpRequest');
$csrfRequest->server->set('HTTP_X_GLPI_CSRF_TOKEN', Session::getNewCSRFToken(true));
$csrfListener->onKernelController($csrfEvent);
$csrfListener->onKernelController($csrfEvent);
verify(true, 'native AJAX CSRF token remains valid for repeated actions');
$originalKey = file_get_contents(GLPI_CONFIG_DIR . '/glpicrypt.key');
$DB->beginTransaction();
try {
    // Explicit zeroes must survive even an old database without the v3 marker.
    $profileId = (int) $_SESSION['glpiactiveprofile']['id'];
    $DB->update(ProfileRight::getTable(), ['rights' => 0], ['profiles_id' => $profileId, 'name' => SecretProfile::RIGHT_REVEAL]);
    $DB->delete('glpi_plugin_secret_configs', ['name' => 'profile_rights_defaults_v3']);
    verify((new GlpiPlugin\Secret\Install\ProfileRightSynchronizer())->synchronize(), 'rights migration succeeds');
    verify(!SecretProfile::canRevealSecret(), 'upgrade preserves explicitly revoked zero');
    $DB->update(ProfileRight::getTable(), ['rights' => READ], ['profiles_id' => $profileId, 'name' => SecretProfile::RIGHT_REVEAL]);
    (new GlpiPlugin\Secret\Install\ProfileRightSynchronizer())->refreshActiveProfileRights();
    verify(!(new GlpiPlugin\Secret\Service\SecretInputValidator())->groupIsValid(2147483647, 0), 'nonexistent group rejected');
    foreach ([Ticket::class, Change::class, Problem::class] as $type) {
        $item = new $type();
        $id = $item->add(['name' => 'Secret integration fixture', 'content' => 'Synthetic', 'entities_id' => 0, 'status' => 1]);
        verify((bool) $id, "$type fixture created");
        $item->getFromDB((int) $id);
        $input = ['name' => 'Synthetic credential', 'type' => 'password', 'secret_value' => 'Pässwörd-Été-ß-€-🔐', 'visibility' => 'owner', 'expiration_policy' => 'ticket_closed'];
        $secretId = (new CreateSecretService())->createForItil($item, $input);
        $secret = new Secret();
        verify($secret->getFromDB($secretId), "$type service persists secret");
        $context = (new ItilActorResolver())->forItem($item);
        verify((new SecretValueService())->reveal($secret, false, $context) === $input['secret_value'], "$type native GLPIKey roundtrip");
        verify((new SecretValueService())->reveal($secret, true, $context) === $input['secret_value'], "$type copy is independently authorized");
        verify(countElementsInTable(SecretLog::getTable(), ['plugin_secret_secrets_id' => $secretId]) === 3, "$type CREATE/VIEW/COPY audited");
        verify((new TicketSecretRepository())->countVisibleForItem($item) === 1, "$type native SQL count");
        verify(count((new TicketSecretRepository())->visibleMetadataForItem($item, 50)) === 1, "$type paginated metadata");
        $request = new Symfony\Component\HttpFoundation\Request([], ['itemtype' => $type, 'items_id' => $id]);
        $response = (new GlpiPlugin\Secret\Controller\AuditSecretController())($request, $secretId);
        verify(count(json_decode($response->getContent(), true)['entries']) === 3, "$type native audit controller");
        verify(str_contains($response->headers->get('Cache-Control'), 'no-store'), "$type audit response is not cacheable");
        $response = (new GlpiPlugin\Secret\Controller\RevealSecretController())($request, $secretId);
        verify(json_decode($response->getContent(), true)['value'] === $input['secret_value'], "$type native reveal controller");
        verify(str_contains($response->headers->get('Cache-Control'), 'no-store'), "$type reveal response is not cacheable");
        $badRequest = new Symfony\Component\HttpFoundation\Request([], ['itemtype' => $type, 'items_id' => 2147483647]);
        $denied = false;
        try {
            (new GlpiPlugin\Secret\Controller\AuditSecretController())($badRequest, $secretId);
        } catch (Glpi\Exception\Http\AccessDeniedHttpException) {
            $denied = true;
        }
        verify($denied, "$type forged audit relation denied");
        $oldRights = $_SESSION['glpiactiveprofile'];
        $_SESSION['glpiactiveprofile']['followup'] = 0;
        verify(!(new SecretAvailabilityNotifier())->notify($item), "$type native followup denial respected");
        $_SESSION['glpiactiveprofile'] = $oldRights;
        $invalidInput = $input;
        $invalidInput['visibility'] = 'entity_technicians';
        $rejected = false;
        try {
            (new CreateSecretService())->createForItil($item, $invalidInput);
        } catch (RuntimeException) {
            $rejected = true;
        }
        verify($rejected, "$type rejects asset-only visibility");
        $owner = $_SESSION['glpiID'];
        $_SESSION['glpiID'] = 3;
        verify(!(new SecretAccessService())->canReveal($secret, (new ItilActorResolver())->forItem($item)), "$type owner ACL denies another administrator");
        verify((new TicketSecretRepository())->countVisibleForItem($item) === 0, "$type count hides another owner's secret");
        Config::setConfigurationValues('plugin:secret', ['admin_acl_bypass' => '1']);
        verify((new SecretAccessService())->canReveal($secret, (new ItilActorResolver())->forItem($item)), "$type configured Secret administrator bypasses actor ACL");
        verify((new TicketSecretRepository())->countVisibleForItem($item) === 1, "$type administrator bypass is applied to SQL metadata criteria");
        Config::setConfigurationValues('plugin:secret', ['admin_acl_bypass' => '0']);
        verify(!$secret->can($secretId, PURGE), "$type generic PURGE denied");
        $_SESSION['glpiID'] = $owner;
        (new SecretMutationService())->update($secret, ['secret_value' => 'synthetic replacement'], $type, (int) $id, $context);
        $secret->getFromDB($secretId);
        verify((new SecretValueService())->reveal($secret, false, $context) === 'synthetic replacement', "$type audited replacement");
        $originalFields = $secret->fields;
        foreach (['owner', 'group', 'ticket_technicians', 'requesters_and_technicians', 'entity_technicians'] as $visibility) {
            $DB->update(Secret::getTable(), ['visibility' => $visibility, 'groups_id' => 123], ['id' => $secretId]);
            $secret->getFromDB($secretId);
            foreach ([[2, [], false, false], [3, [123], false, false], [3, [], true, false], [3, [], false, true], [3, [], false, false]] as [$userId, $groups, $technician, $requester]) {
                $actor = new GlpiPlugin\Secret\Security\AclContext($userId, $groups, $technician, $requester, false, true);
                $access = new SecretAccessService();
                $sql = countElementsInTable(Secret::getTable(), ['id' => $secretId, $access->metadataCriteriaForItil($actor)]) > 0;
                verify($sql === $access->canSeeMetadata($secret, $actor), "$type SQL ACL agrees with $visibility policy");
            }
        }
        $DB->update(Secret::getTable(), ['visibility' => $originalFields['visibility'], 'groups_id' => $originalFields['groups_id']], ['id' => $secretId]);
        $secret->getFromDB($secretId);
        $before = countElementsInTable(SecretLog::getTable(), ['plugin_secret_secrets_id' => $secretId]);
        try {
            (new SecretMutationService())->update($secret, ['secret_value' => str_repeat('x', 1048577)], $type, (int) $id, $context);
            verify(false, 'oversize must throw');
        } catch (RuntimeException) {
            verify(countElementsInTable(SecretLog::getTable(), ['plugin_secret_secrets_id' => $secretId]) === $before, "$type invalid replacement rolls back audit");
        }
        // DB proxy injects only a failed audit insert, without SQL errors or leaked values.
        $realDb = $DB;
        $DB = new class ($realDb) {
            public function __construct(private object $db) {}
            public function __get(string $name)
            {
                return $this->db->$name;
            }
            public function __call(string $name, array $args)
            {
                return $this->db->$name(...$args);
            }
            public function insert($table, $params)
            {
                return $table === SecretLog::getTable() ? false : $this->db->insert($table, $params);
            }
        };
        try {
            $denied = false;
            try {
                (new SecretValueService())->reveal($secret, false, $context);
            } catch (RuntimeException) {
                $denied = true;
            }
            verify($denied, "$type failed audit prevents revealing plaintext");
            $denied = false;
            try {
                (new SecretMutationService())->update($secret, ['secret_value' => 'must roll back'], $type, (int) $id, $context);
            } catch (RuntimeException) {
                $denied = true;
            }
            verify($denied, "$type failed audit rejects replacement");
        } finally {
            $DB = $realDb;
        }
        $secret->getFromDB($secretId);
        verify((new SecretValueService())->reveal($secret, false, $context) === 'synthetic replacement', "$type failed audit rolls back ciphertext");
        verify(in_array(Secret::getTable() . '.encrypted_value', (new GLPIKey())->getFields(), true), "$type registered for native key rotation");
        verify((new GLPIKey())->generate(), "$type native key rotation succeeds");
        $secret->getFromDB($secretId);
        verify((new SecretValueService())->reveal($secret, false, $context) === 'synthetic replacement', "$type value survives native key rotation");
        $status = $type::getClosedStatusArray()[0];
        verify($item->update(['id' => $id, 'status' => $status]), "$type closes natively");
        $secret->getFromDB($secretId);
        verify(!empty($secret->fields['expiration']), "$type closure hook persists expiration");
        $item->update(['id' => $id, 'status' => 1]);
        $secret->getFromDB($secretId);
        verify(!(new SecretAccessService())->canReveal($secret, (new ItilActorResolver())->forItem($item)), "$type reopening cannot reactivate value");
        $DB->update(Secret::getTable(), ['expiration' => date('Y-m-d H:i:s', strtotime('-40 days'))], ['id' => $secretId]);
        $task = new CronTask();
        $task->fields['param'] = 30;
        verify(Secret::cronPurgeExpired($task) === 1, "$type native purge succeeds");
        verify(!$secret->getFromDB($secretId), "$type expired ciphertext removed");
        verify(countElementsInTable(SecretLog::getTable(), ['plugin_secret_secrets_id' => $secretId, 'action' => 'PURGE']) === 1, "$type purge retains audit");
    }
} finally {
    $DB->rollBack();
    file_put_contents(GLPI_CONFIG_DIR . '/glpicrypt.key', $originalKey);
}
echo "Completed $checks native GLPI checks.\n";
