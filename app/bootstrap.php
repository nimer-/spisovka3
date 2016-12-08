<?php

use Tracy\Debugger;

if (!defined('KLIENT')) {
    echo "<h1>Chyba aplikace. Nebyl zjisten klient!</h1>";
    exit;
}

if (file_exists(APP_DIR . "/configs/servicemode")) {
    readfile(APP_DIR . "/configs/servicemode");
    exit;
}

try {

// Step 1: Configure automatic loading
    if (!defined('LIBS_DIR'))
        define('LIBS_DIR', dirname(APP_DIR) . '/libs');
    define('VENDOR_DIR', dirname(APP_DIR) . '/vendor');

// prikaz nastavi loading pouze pro balicky instalovane Composerem
    require VENDOR_DIR . '/autoload.php';

    define('TEMP_DIR', CLIENT_DIR . '/temp');

// check if temp directory is writable
    if (file_put_contents(TEMP_DIR . '/_check', '') === FALSE) {
        throw new Exception("Nelze zapisovat do adresare '" . TEMP_DIR . "'");
    }

// Toto kontroluje standardni umisteni, prestoze uzivatel muze nadefinovat
// ukladani session do jineho adresare
    $session_dir = CLIENT_DIR . '/sessions';
    if (file_put_contents("$session_dir/_check", '') === FALSE) {
        throw new Exception("Nelze zapisovat do adresare '$session_dir'");
    }

// enable RobotLoader - this allows load all classes automatically
    $loader = new Nette\Loaders\RobotLoader();
    $loader->addDirectory(APP_DIR);
    $loader->addDirectory(LIBS_DIR);
    $cache_dir = TEMP_DIR . '/cache';
    if (!is_dir($cache_dir))
        mkdir($cache_dir);
    $loader->setCacheStorage(new Nette\Caching\Storages\FileStorage($cache_dir));
    $loader->register();


// Step 2: Configure environment

    register_shutdown_function(array('ShutdownHandler', '_handler'));

// 2a) enable Nette\Debug for better exception and error visualisation

    define('LOG_DIR', dirname(APP_DIR) . '/log');

    if (!defined('DEBUG_ENABLE'))
        define('DEBUG_ENABLE', 0);
    Debugger::enable(DEBUG_ENABLE ? Debugger::DEVELOPMENT : Debugger::PRODUCTION, LOG_DIR);
// '%logDir%/php_error_'.date('Ym').'.log' - stary nazev souboru s logy
    Nette\Bridges\Framework\TracyBridge::initialize();
    Debugger::$maxDepth = 5;

// 2b) vytvor DI kontejner

    createIniFiles();

    $cookie_path = str_replace('index.php', '', $_SERVER['PHP_SELF']);

    $configurator = new Nette\Configurator;
    $configurator
            ->setDebugMode((bool) DEBUG_ENABLE)
            ->setTempDirectory(TEMP_DIR)
            ->addParameters(['clientDir' => CLIENT_DIR,
                'cookiePath' => $cookie_path])
            ->addConfig(APP_DIR . '/configs/system.neon')
            ->addConfig(CLIENT_DIR . '/configs/database.neon');
    if (is_file(CLIENT_DIR . '/configs/system.neon'))
        $configurator->addConfig(CLIENT_DIR . '/configs/system.neon');

    $container = $configurator->createContainer();

// zjisti public URL
    $http_request = $container->getByType('Nette\Http\IRequest');
    if ($public_url) {
        if ($http_request->isSecured())
        // dynamicky uprav protokol v nastaveni PUBLIC_URL
            $public_url = str_replace('http:', 'https:', $public_url);
    }
    else {
        $public_url = $http_request->getUrl()->getBasePath() . 'public/';
    }
    GlobalVariables::set('publicUrl', $public_url);


// konfigurace spisovky
    GlobalVariables::set('client_config', (new Spisovka\ConfigClient())->get());

    $install_info = @file_get_contents(CLIENT_DIR . '/configs/install');
    if ($install_info === FALSE) {
        define('APPLICATION_INSTALL', 1);
        @ini_set('memory_limit', '128M');
    } else {
        $app_id = substr($install_info, 0, strpos($install_info, '#'));
        GlobalVariables::set('app_id', $app_id);
    }

    setupPdfExport();

// 3b) Load database
    try {
        $db_config = $container->parameters['database'];
        GlobalVariables::set('database', Nette\Utils\ArrayHash::from($db_config));

        // oprava chybne konfigurace na hostingu
        // profiler je bez DEBUG modu k nicemu, jen plytva pameti (memory leak)
        if (!$configurator->isDebugMode())
            $db_config['profiler'] = false;
        else if ($db_config['profiler']) {
            $db_config['profiler'] = array(
                'run' => true,
                'file' => LOG_DIR . '/mysql_' . KLIENT . '_' . date('Ymd') . '.log');
        }

        $connection = Spisovka\dibi::connect($db_config);
        if ($configurator->isDebugMode()) {
            // false - Neni treba explain SELECT dotazu
            $panel = new Dibi\Bridges\Tracy\Panel(false, DibiEvent::ALL);
            $panel->register($connection);
        }

        dibi::getSubstitutes()->{'PREFIX'} = null;
    } catch (DibiDriverException $e) {
        echo 'Aplikaci se nepodarilo pripojit do databaze.<br>';
        throw $e;
    }

// 3c) Konfiguruj e-podatelnu - musí být provedeno po připojení do databáze
    if (!defined('APPLICATION_INSTALL'))
        createEpodatelnaConfig();

// Step 4: Setup application router
// 
    $router = $container->getByType('Nette\Application\IRouter');
    setupRouting($http_request, $router);
} catch (Exception $e) {
    echo 'Behem inicializace aplikace doslo k vyjimce. Podrobnejsi informace lze nalezt v aplikacnim logu.<br>'
    . 'Podrobnosti: ' . $e->getMessage();
    throw $e;
}

// Step 5: Perform application upgrade if needed

if (!defined('APPLICATION_INSTALL')) {
    $upgrade = new Upgrade();
    $upgrade->check();
}

// Step 6: Run the application!
// unset all global variables except PHP superglobals
$application = $container->getByType('Nette\Application\Application');

$vars = array_keys(get_defined_vars());
foreach ($vars as $var)
    if ($var != 'application' && $var[0] != '_')
        unset($$var);
unset($vars, $var);

$application->run();

function createIniFiles()
{
    $dir = CLIENT_DIR . '/configs';
    createIniFile("$dir/klient.ini");

    if (!is_file("$dir/database.neon") && is_file("$dir/system.ini"))
        migrateSystemIni();
}

function createIniFile($filename)
{
    if (file_exists($filename))
        return;

    $template = substr($filename, 0, -1);
    if (!copy($template, $filename))
        throw new Exception("Nemohu vytvorit soubor $filename.");

    $perm = 0640;
    @chmod($filename, $perm);
}

// Migrace na 3.5.0
// Prenese se to nejdulezitejsi - prihlasovaci udaje do databaze
// Ostatni pripadne upravy musi uzivatel provest rucne,
// konfigurace autentizace se tak jako tak zmenila
function migrateSystemIni()
{
    $dir = CLIENT_DIR . '/configs';
    $loader = new \Nette\DI\Config\Loader();
    $old_config = $loader->load("$dir/system.ini");

    $new_config = ['parameters' => ['database' => $old_config['common']['database']]];
    // Pokud uzivatel bude chtit nestandardni mod, bude jej muset zadat do konfiguracniho 
    // souboru znovu rucne.
    unset($new_config['parameters']['database']['sqlmode']);

    $loader->save($new_config, "$dir/database.neon");
    @chmod("$dir/database.neon", 0400);

    // Uklid. Prejmenovani pojisti, ze se konfigurace zmigruje jen jednou.
    unlink("$dir/system.in");
    rename("$dir/system.ini", "$dir/system.old");
}

function createEpodatelnaConfig()
{
    // nejprve zkontroluj, zda migrace uz byla provedena
    if (Settings::get('epodatelna'))
        return;

    // potom zjisti, zda se jedna o novou instalaci ci nikoliv
    $dir = CLIENT_DIR . '/configs';
    if (!is_file("$dir/epodatelna.ini"))
        createIniFile("$dir/epodatelna.ini");

    $config = (new Spisovka\ConfigEpodatelnaOld())->get();
    (new Spisovka\ConfigEpodatelna())->save($config);

    rename("$dir/epodatelna.ini", "$dir/epodatelna.old");
    @chmod("$dir/epodatelna.old", 0400);
}

function setupPdfExport()
{
    // bohuzel musime nakonfigurovat PDF export zde, protoze pro nej neexistuje spolecna funkce
    define('PDF_MEMORY_LIMIT', '512M');
    $mpdf_dir = TEMP_DIR . '/mpdf/';
    $mpdf_tmp_dir = $mpdf_dir . 'tmp/';
    $mpdf_fontdata_dir = $mpdf_dir . 'ttfontdata/';
    define('_MPDF_TEMP_PATH', $mpdf_tmp_dir);
    define('_MPDF_TTFONTDATAPATH', $mpdf_fontdata_dir);

    if (!is_dir($mpdf_dir))
        mkdir($mpdf_dir);
    if (!is_dir($mpdf_tmp_dir))
        mkdir($mpdf_tmp_dir);
    if (!is_dir($mpdf_fontdata_dir))
        mkdir($mpdf_fontdata_dir);
}

function setupRouting(Nette\Http\IRequest $httpRequest, Nette\Application\IRouter $router)
{
    $force_https = false;
    try {
        // Nasledujici prikaz funguje az pote, co je provedena instalace
        $force_https = Settings::get('router_force_https', false);
    } catch (DibiException $e) {
        // ignoruj
    }

    if ($force_https || $httpRequest->isSecured())
        Nette\Application\Routers\Route::$defaultFlags |= Nette\Application\Routers\Route::SECURED;

    $router[] = new Nette\Application\Routers\Route('index.php',
            array(
        'module' => 'Spisovka',
        'presenter' => 'Default',
        'action' => 'default',
            ), Nette\Application\Routers\Route::ONE_WAY);

    $router[] = new Nette\Application\Routers\Route('uzivatel/<action>/<id>',
            array(
        'module' => 'Spisovka',
        'presenter' => 'Uzivatel',
        'action' => 'default',
        'id' => NULL,
    ));

    $router[] = new Nette\Application\Routers\Route('napoveda/<param1>/<param2>/<param3>',
            array(
        'module' => 'Spisovka',
        'presenter' => 'Napoveda',
        'action' => 'default',
        'param1' => 'obsah',
        'param2' => 'param2',
        'param3' => 'param3'
    ));

    $router[] = new Nette\Application\Routers\Route('admin/<presenter>/<action>/<id>/<params>',
            array(
        'module' => 'Admin',
        'presenter' => 'Default',
        'action' => 'default',
        'id' => null,
        'params' => null
    ));

    $router[] = new Nette\Application\Routers\Route('epodatelna/<presenter>/<action>/<id>',
            array(
        'module' => 'Epodatelna',
        'presenter' => 'Default',
        'action' => 'default',
        'id' => null
    ));

    $router[] = new Nette\Application\Routers\Route('spisovna/<presenter>/<action>',
            array(
        'module' => 'Spisovna',
        'presenter' => 'Default',
        'action' => 'default',
        'id' => NULL,
    ));
    $router[] = new Nette\Application\Routers\Route('spisovna/<presenter>/<id>/<action>',
            array(
        'module' => 'Spisovna',
        'presenter' => 'Default',
        'action' => 'detail',
        'id' => null
    ));

    $router[] = new Nette\Application\Routers\Route('install/<action>/<id>/<params>',
            array(
        'module' => 'Install',
        'presenter' => 'Default',
        'action' => 'default',
        'id' => null,
        'params' => null
    ));

    $router[] = new Nette\Application\Routers\Route('zpravy/<action>/<id>',
            array(
        'module' => 'Spisovka',
        'presenter' => 'Zpravy',
        'action' => 'default',
        'id' => NULL,
    ));

    $router[] = new Nette\Application\Routers\Route('test/<action>', 'Test:Default:');

    $router[] = new Nette\Application\Routers\Route('<presenter>/<action>',
            array(
        'module' => 'Spisovka',
        'presenter' => 'Default',
        'action' => 'default',
        'id' => NULL,
    ));

    $router[] = new Nette\Application\Routers\Route('<presenter>/<id>/<action>',
            array(
        'module' => 'Spisovka',
        'presenter' => 'Default',
        'action' => 'detail',
        'id' => NULL,
    ));
}
